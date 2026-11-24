<?php

declare(strict_types=1);

namespace Universally\CraftCms\migrations;

use craft\db\Migration;

/** Stores the encrypted Universally key and consent outside Craft project config. */
class m261124_120000_add_connection_settings extends Migration
{
    /** Creates the private settings table for existing installations. */
    public function safeUp(): bool
    {
        $this->createTable('{{%universally_connection}}', [
            'id' => $this->primaryKey(),
            'encryptedApiKey' => $this->text(),
            'allowContentTransfer' => $this->boolean()->notNull()->defaultValue(false),
        ]);

        return true;
    }

    /** Deletes the stored connection when the migration is reverted or the plugin is uninstalled. */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%universally_connection}}');
        return true;
    }
}
