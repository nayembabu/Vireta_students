<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Link each user account to its matching students (pre-registration) record.
 */
final class AddStudentIdToUsers extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('users');
        $table
            ->addColumn('student_id', 'biginteger', ['signed' => false, 'null' => true, 'after' => 'batch_id'])
            ->addIndex(['student_id'], ['unique' => true])
            ->addForeignKey('student_id', 'students', 'id', ['delete' => 'SET_NULL'])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('users');
        $table
            ->dropForeignKey('student_id')
            ->removeIndex(['student_id'])
            ->removeColumn('student_id')
            ->update();
    }
}