<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Associate each batch with a course via `batches.course_id`.
 */
final class AddCourseIdToBatches extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('batches');
        $table
            ->addColumn('course_id', 'biginteger', ['signed' => false, 'null' => true, 'after' => 'name'])
            ->addIndex(['course_id'])
            ->addForeignKey('course_id', 'courses', 'id', ['delete' => 'SET_NULL'])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('batches');
        $table
            ->dropForeignKey('course_id')
            ->removeIndex(['course_id'])
            ->removeColumn('course_id')
            ->update();
    }
}
