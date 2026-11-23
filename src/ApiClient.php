<?php

declare(strict_types=1);

namespace Universally\CraftCms;

use Craft;
use craft\helpers\App;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

/** Calls the existing Universally language and HTML APIs from the Craft server. */
final class ApiClient
{
    /** Store successful language lookups briefly so every page need not call the API. */
    private const LANGUAGE_CACHE_SECONDS = 60;

    /** Creates a client authenticated with the Craft project's unified key. */
    public function __construct(
        /** The unified project key, kept in the server environment. */
        private readonly string $key,
    ) {
    }

    /** Returns configured, enabled language URLs mapped to their translator variants. */
    public function locales(): array
    {
        $base = rtrim((string)(App::env('UNIVERSALLY_API_URL') ?: 'https://api.universally.com'), '/');
        $cache = Craft::$app->getCache();
        $cacheKey = ['universally-craft-languages', $base, hash('sha256', $this->key)];
        $cached = $cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        // The cache stores just the routing map, including an intentionally empty map.
        $data = $this->request('GET', $base . '/connect/languages', 2);
        if (!is_array($data['targetLanguages'] ?? null)) {
            return [];
        }

        $locales = [];
        foreach ($data['targetLanguages'] as $language) {
            $prefix = $language['url'] ?? null;
            $variant = $language['variant'] ?? null;
            if (!($language['isDisabled'] ?? false) && is_string($prefix) && is_string($variant) &&
                preg_match('/^[a-z][a-z0-9-]{1,31}$/D', $prefix) &&
                preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/Di', $variant)) {
                $locales[$prefix] ??= $variant;
            }
        }

        $cache->set($cacheKey, $locales, self::LANGUAGE_CACHE_SECONDS);
        return $locales;
    }

    /** Translates a full page and returns null when a translated document is unavailable. */
    public function translate(string $html, string $variant, string $sourceUrl): ?string
    {
        $base = rtrim((string)(App::env('UNIVERSALLY_TRANSLATOR_URL') ?: 'https://translator.universally.com'), '/');
        $data = $this->request('POST', $base . '/v1/translate/html', 12, [
            'html' => $html,
            'targetLanguage' => $variant,
            'sourceUrl' => $sourceUrl,
            'parserMode' => 'auto',
        ]);

        $translated = $data['translatedHtml'] ?? null;
        if (!is_string($translated) || $translated === '' || ($data['metadata']['limitReached'] ?? false)) {
            return null;
        }

        return $translated;
    }

    /** Unwraps Universally's response without logging the credential or page contents. */
    private function request(string $method, string $url, int $timeout, ?array $json = null): ?array
    {
        try {
            $response = Craft::createGuzzleClient()->request($method, $url, [
                'headers' => ['X-API-Key' => $this->key, 'Accept' => 'application/json'],
                'allow_redirects' => false,
                'http_errors' => false,
                'connect_timeout' => 2,
                'timeout' => $timeout,
            ] + ($json === null ? [] : ['json' => $json]));

            if ($response->getStatusCode() !== 200) {
                Craft::warning('The Universally API returned HTTP ' . $response->getStatusCode(), __METHOD__);
                return null;
            }

            $envelope = json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);
            return ($envelope['success'] ?? false) === true && is_array($envelope['data'] ?? null)
                ? $envelope['data']
                : null;
        } catch (GuzzleException | JsonException) {
            Craft::warning('The Universally API could not return a valid response.', __METHOD__);
            return null;
        }
    }
}
