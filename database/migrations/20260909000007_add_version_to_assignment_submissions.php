<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddVersionToAssignmentSubmissions extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('assignment_submissions');
        $table->removeIndex(['assignment_id', 'user_id'])
            ->addColumn('version', 'integer', [
                'signed' => false,
                'null' => false,
                'default' => 1,
                'after' => 'user_id',
            ])
            ->addIndex(['assignment_id', 'user_id', 'version'], ['unique' => true])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('assignment_submissions');
        $table->removeIndex(['assignment_id', 'user_id', 'version'])
            ->removeColumn('version')
            ->addIndex(['assignment_id', 'user_id'], ['unique' => true])
            ->update();
    }
}