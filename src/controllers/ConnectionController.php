<?php

declare(strict_types=1);

namespace Universally\CraftCms\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use Universally\CraftCms\ApiClient;
use Universally\CraftCms\ConnectionSettings;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/** Accepts connection updates from the Craft control panel. */
final class ConnectionController extends Controller
{
    /** Validates an administrator's submission and saves the key and consent together. */
    public function actionSave(): ?Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requireAdmin();

        if (ConnectionSettings::usesEnvironment()) {
            throw new ForbiddenHttpException('The Universally connection is managed by environment variables.');
        }

        $input = $this->request->getBodyParam('apiKey', '');
        if (!is_string($input)) {
            return $this->asFailure(Craft::t('universally', 'Enter a valid Universally project API key.'));
        }

        $newKey = ConnectionSettings::normalizeKey($input);
        if (trim($input) !== '' && $newKey === null) {
            return $this->asFailure(Craft::t('universally', 'Enter the full Universally project API key from Setup.'));
        }

        $allowed = $this->request->getBodyParam('allowContentTransfer') === '1';
        if ($allowed) {
            $key = $newKey ?? ConnectionSettings::storedKey(ConnectionSettings::stored());
            if ($key === null) {
                return $this->asFailure(Craft::t('universally', 'Enter your project API key before enabling translation.'));
            }

            $domain = (new ApiClient($key))->verifiedProjectDomain();
            if ($domain === null) {
                return $this->asFailure(Craft::t('universally', 'Universally could not verify the project key. Check the key in your project’s Setup page and try again.'));
            }

            $siteUrl = (string)Craft::$app->getSites()->getPrimarySite()->getBaseUrl();
            $host = strtolower((string)parse_url($siteUrl, PHP_URL_HOST));
            if ($host !== $domain && $host !== "www.$domain" && "www.$host" !== $domain) {
                return $this->asFailure(Craft::t('universally', 'This API key belongs to a different website. Use the key from this website’s Universally project.'));
            }
        }

        ConnectionSettings::save($newKey, $allowed);

        return $this->asSuccess(
            Craft::t('universally', 'Universally connection settings saved.'),
            redirect: UrlHelper::cpUrl('settings/plugins/universally'),
        );
    }
}
