<?php

declare(strict_types=1);

namespace Universally\CraftCms;

use Craft;
use craft\base\Plugin as CraftPlugin;
use craft\helpers\App;
use craft\web\Application;
use craft\web\Request;
use craft\web\Response;
use craft\web\View;
use yii\base\Event;

/** Serves translated pages through the Craft site on language-prefixed URLs. */
final class Plugin extends CraftPlugin
{
    /** Bumps the database schema for installations upgrading from 0.1. */
    public string $schemaVersion = '1.1.0';

    /** Adds a Settings link in the Craft plugin list. */
    public bool $hasCpSettings = true;

    /** Shows configuration guidance when Craft has locked the control panel settings. */
    public bool $hasReadOnlyCpSettings = true;

    /** Whether Craft rendered a site page for the current response. */
    private bool $renderedSitePage = false;

    /** Registers the storefront hook that checks permission before using a project key. */
    public function init(): void
    {
        parent::init();

        $app = Craft::$app;
        if ($app instanceof Application) {
            $app->on(Application::EVENT_BEFORE_REQUEST, function () use ($app): void {
                $this->preparePage($app);
            });
        }
    }

    /** Renders the connection form with the installed plugin name and keeps the saved key server-side. */
    public function getSettingsResponse(): mixed
    {
        $environmentManaged = ConnectionSettings::usesEnvironment();
        $settings = $environmentManaged ? [] : ConnectionSettings::stored();
        $hasKey = ConnectionSettings::storedKey($settings) !== null;

        return Craft::$app->controller->renderTemplate('universally/settings', [
            'pluginName' => $this->name,
            'environmentManaged' => $environmentManaged,
            'readOnly' => false,
            'hasKey' => $hasKey,
            'allowed' => $hasKey && ConnectionSettings::isAllowed($settings),
        ]);
    }

    /** Shows the installed plugin name and environment setup guidance when settings are read-only. */
    public function getReadOnlySettingsResponse(): mixed
    {
        return Craft::$app->controller->renderTemplate('universally/settings', [
            'pluginName' => $this->name,
            'environmentManaged' => ConnectionSettings::usesEnvironment(),
            'readOnly' => true,
        ]);
    }

    /** Resolves a language prefix before Craft matches the original template or entry. */
    private function preparePage(Application $app): void
    {
        $request = $app->getRequest();
        if (!$this->isPublicRequest($app, $request)) {
            return;
        }

        $site = $app->getSites()->getCurrentSite();
        $origin = rtrim((string)$site->getBaseUrl(), '/');
        if (
            $app->getConfig()->getGeneral()->headlessMode ||
            $site->id !== $app->getSites()->getPrimarySite()->id ||
            !preg_match('~^https?://[^/?#@]+$~iD', $origin) ||
            strcasecmp($request->getHostInfo(), $origin) !== 0
        ) {
            return;
        }

        $key = ConnectionSettings::activeKey();
        if ($key === null) {
            return;
        }

        $client = new ApiClient($key);
        $publicKey = substr($key, 0, 32);
        $pathParts = explode('/', $request->getFullPath(), 2);
        [$prefix, $path] = [$pathParts[0], $pathParts[1] ?? ''];
        $variant = null;
        $sourceUrl = null;

        if (preg_match('/^[a-z][a-z0-9-]{1,31}$/D', $prefix)) {
            $variant = $client->locales()[$prefix] ?? null;
            if ($variant !== null) {
                // Craft computes path segments, pagination, and request type on initialization.
                // Give the new request the source path before that initialization runs. Keep the
                // actual browser URL and headers for Twig URL generation and request policies.
                $sourceRequest = Craft::createObject([
                    'class' => $request::class,
                    'pathInfo' => $path,
                    'url' => $request->getUrl(),
                ] + get_object_vars($request));
                $sourceRequest->getHeaders()->fromArray($request->getHeaders()->toArray());

                if (!$this->isPublicRequest($app, $sourceRequest)) {
                    return;
                }

                $sourceUrl = $origin . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
                $app->set('request', $sourceRequest);
            }
        }

        $this->renderedSitePage = false;
        Event::on(View::class, View::EVENT_AFTER_RENDER_PAGE_TEMPLATE, function (Event $event): void {
            if ($event->sender->getTemplateMode() === View::TEMPLATE_MODE_SITE) {
                $this->renderedSitePage = true;
            }
        });
        $app->getResponse()->on(Response::EVENT_AFTER_PREPARE, function (Event $event) use ($app, $client, $publicKey, $variant, $sourceUrl): void {
            $this->translateResponse($app, $event->sender, $client, $publicKey, $variant, $sourceUrl);
        });
    }

    /** Skips control panel, action, preview and visitor-specific requests. */
    private function isPublicRequest(Application $app, Request $request): bool
    {
        $headers = $request->getHeaders();
        if (!$request->getIsGet() || !$request->getIsSiteRequest() || $request->getIsAjax() ||
            $request->getQueryString() !== '' || $request->getHadToken() ||
            $headers->has('X-Craft-Preview-Token') || $request->getIsLivePreview() ||
            $headers->has('Authorization') || $headers->has('X-Craft-Authorization') ||
            $request->getIsActionRequest() || $request->getScriptUrl() === $request->getUrl()) {
            return false;
        }

        $user = $app->getUser();
        $cookies = $request->getRawCookies();
        return !$cookies->has($app->getSession()->getName()) &&
            !$cookies->has($user->identityCookie['name']) &&
            $user->getIsGuest();
    }

    /** Translates successful public HTML documents and appends the public switcher script. */
    private function translateResponse(
        Application $app,
        Response $response,
        ApiClient $client,
        string $publicKey,
        ?string $variant,
        ?string $sourceUrl,
    ): void {
        $headers = $response->getHeaders();
        $html = $response->content;
        if (!$this->renderedSitePage || $response->getStatusCode() !== 200 ||
            !str_starts_with(strtolower((string)$headers->get('Content-Type')), 'text/html') ||
            !is_string($html) || stripos($html, '</head>') === false) {
            return;
        }

        if (!$app->getUser()->getIsGuest() || $response->getCookies()->getCount() > 0 ||
            preg_match('/\b(?:private|no-cache|no-store)\b/i', (string)$headers->get('Cache-Control'))) {
            if ($variant !== null) {
                $this->preventIndexing($response);
            }
            return;
        }

        if ($variant !== null && $sourceUrl !== null) {
            $translated = $client->translate($html, $variant, $sourceUrl);
            if ($translated === null) {
                $this->preventIndexing($response);
            } else {
                $html = $translated;
            }
        }

        $scriptsUrl = rtrim((string)(App::env('UNIVERSALLY_SCRIPTS_URL') ?: 'https://scripts.universally.com'), '/');
        $src = htmlspecialchars("$scriptsUrl/p/$publicKey/s.js", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $response->content = preg_replace_callback(
            '~</head>~i',
            static fn(array $match): string => '<script src="' . $src . '" async></script>' . $match[0],
            $html,
            1,
        );
        $headers->remove('Content-Length');
        $headers->remove('ETag');
    }

    /** Prevents caches and search engines from keeping source HTML on a localized URL. */
    private function preventIndexing(Response $response): void
    {
        $response->setNoCacheHeaders();
        $response->getHeaders()->add('X-Robots-Tag', 'noindex');
    }
}
