<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Role system: a `roles` table with four fixed records
 * (1=admin, 2=student, 3=trainer, 4=cashier) and a `users.role_id` FK,
 * replacing the old `users.role` enum (admin/mentor/student).
 */
final class CreateRolesTable extends AbstractMigration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // roles
        // ------------------------------------------------------------------
        $this->table('roles', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('slug', 'string', ['limit' => 50])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $now = date('Y-m-d H:i:s');
        $this->table('roles')
            ->insert([
                ['id' => 1, 'name' => 'Admin', 'slug' => 'admin', 'description' => 'Full system access', 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'name' => 'Student', 'slug' => 'student', 'description' => 'Student portal access', 'created_at' => $now, 'updated_at' => $now],
                ['id' => 3, 'name' => 'Trainer', 'slug' => 'trainer', 'description' => 'Conducts classes and grades work', 'created_at' => $now, 'updated_at' => $now],
                ['id' => 4, 'name' => 'Cashier', 'slug' => 'cashier', 'description' => 'Manages course fees and payments', 'created_at' => $now, 'updated_at' => $now],
            ])
            ->saveData();

        // ------------------------------------------------------------------
        // users: role enum -> role_id
        // ------------------------------------------------------------------
        $table = $this->table('users');
        $table
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'null' => true])
            ->update();

        $this->execute(
            "UPDATE users SET role_id = CASE role
                WHEN 'admin' THEN 1
                WHEN 'student' THEN 2
                WHEN 'mentor' THEN 3
                WHEN 'cashier' THEN 4
                ELSE 2 END"
        );

        $table
            ->removeColumn('role')
            ->changeColumn('role_id', 'biginteger', ['signed' => false, 'null' => false, 'default' => 2])
            ->addIndex(['role_id'])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'RESTRICT'])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('users');
        $table
            ->addColumn('role', 'enum', ['values' => ['admin', 'mentor', 'student'], 'default' => 'student'])
            ->update();

        $this->execute(
            "UPDATE users u
                JOIN roles r ON r.id = u.role_id
                SET u.role = CASE r.slug
                    WHEN 'admin' THEN 'admin'
                    WHEN 'trainer' THEN 'mentor'
                    ELSE 'student' END"
        );

        $table
            ->dropForeignKey('role_id')
            ->removeIndex(['role_id'])
            ->removeColumn('role_id')
            ->update();

        $this->table('roles')->drop()->save();
    }
}