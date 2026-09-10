<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add `users.username` (unique, login identifier) and backfill existing
 * users from the local part of their email address.
 */
final class AddUsernameToUsers extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('users');
        $table
            ->addColumn('username', 'string', ['limit' => 50, 'null' => true, 'after' => 'email'])
            ->update();

        $used = [];
        foreach ($this->fetchAll("SELECT id, email FROM users WHERE email IS NOT NULL AND email <> ''") as $row) {
            $base = strtolower(trim(explode('@', (string)$row['email'])[0] ?? ''));
            $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?: 'user';

            $username = $base;
            $i = 1;
            while (isset($used[$username])) {
                $username = $base . $i;
                $i++;
            }
            $used[$username] = true;

            $this->execute(sprintf(
                'UPDATE users SET username = "%s" WHERE id = %d',
                $username,
                (int)$row['id']
            ));
        }

        $table
            ->addIndex(['username'], ['unique' => true])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('users');
        $table
            ->removeIndex(['username'])
            ->removeColumn('username')
            ->update();
    }
}