<?php

declare(strict_types=1);

namespace Universally\CraftCms;

use Craft;
use craft\db\Query;
use craft\helpers\App;

/** Manages the connection for this Craft installation without writing credentials to project config. */
final class ConnectionSettings
{
    /** The table that holds the installation's control panel configuration. */
    private const TABLE = '{{%universally_connection}}';

    /** The only settings row in this installation. */
    private const ID = 1;

    /** Whether the installation has explicitly chosen environment-based configuration. */
    public static function usesEnvironment(): bool
    {
        return trim((string)App::env('UNIVERSALLY_API_KEY')) !== '' ||
            App::env('UNIVERSALLY_ALLOW_CONTENT_TRANSFER') !== null;
    }

    /** Normalizes a unified Universally project key if it has the expected format. */
    public static function normalizeKey(#[\SensitiveParameter] string $key): ?string
    {
        $key = trim($key);
        return preg_match('/^[a-f0-9]{64}$/Di', $key) ? strtolower($key) : null;
    }

    /** Returns the current database-backed settings, or an empty state before configuration. */
    public static function stored(): array
    {
        $db = Craft::$app->getDb();
        return (new Query())
            ->select(['encryptedApiKey', 'allowContentTransfer'])
            ->from(self::TABLE)
            ->where(['id' => self::ID])
            ->one($db) ?: [];
    }

    /** Decrypts a saved key for this origin, returning null for missing or unusable credentials. */
    public static function storedKey(array $settings): ?string
    {
        $encoded = $settings['encryptedApiKey'] ?? null;
        if ($encoded === null || ($encrypted = base64_decode($encoded, true)) === false) {
            return null;
        }

        $key = Craft::$app->getSecurity()->decryptByKey($encrypted, info: self::keyContext());
        return $key === false ? null : self::normalizeKey($key);
    }

    /** Reads PostgreSQL and MySQL representations of an explicitly checked consent checkbox. */
    public static function isAllowed(array $settings): bool
    {
        return in_array($settings['allowContentTransfer'] ?? null, [true, 1, '1', 't'], true);
    }

    /** Resolves an explicitly authorized key from the environment or control panel. */
    public static function activeKey(): ?string
    {
        if (self::usesEnvironment()) {
            return App::env('UNIVERSALLY_ALLOW_CONTENT_TRANSFER') === true
                ? self::normalizeKey((string)App::env('UNIVERSALLY_API_KEY'))
                : null;
        }

        $settings = self::stored();
        return self::isAllowed($settings) ? self::storedKey($settings) : null;
    }

    /** Saves consent and an optional replacement key atomically, preserving the key on a blank submission. */
    public static function save(#[\SensitiveParameter] ?string $newKey, bool $allowContentTransfer): void
    {
        $values = ['allowContentTransfer' => $allowContentTransfer];
        if ($newKey !== null) {
            $encrypted = Craft::$app->getSecurity()->encryptByKey($newKey, info: self::keyContext());
            $values['encryptedApiKey'] = base64_encode($encrypted);
        }

        Craft::$app->getDb()->createCommand()
            ->upsert(self::TABLE, ['id' => self::ID] + $values, $values)
            ->execute();
    }

    /** Binds encrypted credentials to the primary origin so copied databases cannot connect another site. */
    private static function keyContext(): string
    {
        return 'universally-craft:' . strtolower(rtrim((string)Craft::$app->getSites()->getPrimarySite()->getBaseUrl(), '/'));
    }
}
