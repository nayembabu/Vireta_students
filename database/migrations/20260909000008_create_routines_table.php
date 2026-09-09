<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRoutinesTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('routines')
            ->addColumn('batch_id', 'biginteger', ['signed' => false])
            ->addColumn('course_id', 'biginteger', ['signed' => false])
            ->addColumn('mentor_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('session_date', 'date')
            ->addColumn('start_time', 'time')
            ->addColumn('end_time', 'time')
            ->addColumn('topic', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('room', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('meeting_link', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addTimestamps()
            ->addIndex(['batch_id', 'session_date'])
            ->addForeignKey('batch_id', 'batches', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('course_id', 'courses', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('mentor_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'SET NULL'])
            ->create();
    }

    public function down(): void
    {
        $this->table('routines')->drop()->save();
    }
}