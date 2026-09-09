<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddRoutineIdToAttendance extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('attendance');
        $table->addColumn('routine_id', 'integer', [
            'signed' => false,
            'null' => true,
            'after' => 'course_id',
        ])
        ->addIndex(['routine_id', 'user_id'], ['unique' => true])
        ->addForeignKey('routine_id', 'routines', 'id', ['delete' => 'CASCADE'])
        ->update();
    }

    public function down(): void
    {
        $table = $this->table('attendance');
        $table->dropForeignKey('routine_id')
            ->removeIndex(['routine_id', 'user_id'])
            ->removeColumn('routine_id')
            ->update();
    }
}