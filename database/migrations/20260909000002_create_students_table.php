<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * students - a pre-seeded list of eligible interns.
 * Students self-register only when their educational_registration_no
 * and phone_no match a record in this table.
 */
final class CreateStudentsTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('students', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('educational_registration_no', 'string', ['limit' => 60])
            ->addColumn('phone_no', 'string', ['limit' => 20])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('batch_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('status', 'enum', ['values' => ['pending', 'registered', 'blocked'], 'default' => 'pending'])
            ->addColumn('registered_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['educational_registration_no'], ['unique' => true])
            ->addIndex(['phone_no'])
            ->addIndex(['status'])
            ->addForeignKey('batch_id', 'batches', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }

    public function down(): void
    {
        $this->table('students')->drop()->save();
    }
}