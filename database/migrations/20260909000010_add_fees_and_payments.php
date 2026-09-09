<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddFeesAndPayments extends AbstractMigration
{
    public function up(): void
    {
        $this->table('courses')
            ->addColumn('fee', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'after' => 'duration_weeks'])
            ->update();

        $this->table('user_courses')
            ->addColumn('fee_deadline', 'date', ['null' => true, 'after' => 'status'])
            ->update();

        $table = $this->table('payments');
        $table->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('course_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('trx_id', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('sender_number', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('screenshot', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('note', 'text', ['null' => true])
            ->addColumn('status', 'enum', ['values' => ['pending', 'verified', 'rejected'], 'default' => 'pending'])
            ->addColumn('verified_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('verified_at', 'timestamp', ['null' => true])
            ->addTimestamps()
            ->addIndex(['trx_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('course_id', 'courses', 'id', ['delete' => 'SET NULL'])
            ->addForeignKey('verified_by', 'users', 'id', ['delete' => 'SET NULL'])
            ->create();
    }

    public function down(): void
    {
        $this->table('payments')->drop()->update();

        $this->table('user_courses')
            ->removeColumn('fee_deadline')
            ->update();

        $this->table('courses')
            ->removeColumn('fee')
            ->update();
    }
}