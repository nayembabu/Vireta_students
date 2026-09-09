<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateEmailVerificationsTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('email_verifications', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('new_email', 'string', ['limit' => 190])
            ->addColumn('token', 'string', ['limit' => 64])
            ->addColumn('expires_at', 'timestamp')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'])
            ->addIndex(['token'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('email_verifications')->drop()->save();
    }
}