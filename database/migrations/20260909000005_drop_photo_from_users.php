<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Profile pictures live only in students.pro_pic.
 * users has no picture column by design.
 */
final class DropPhotoFromUsers extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('users');
        if ($table->hasColumn('photo')) {
            $table->removeColumn('photo')->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('users');
        if (!$table->hasColumn('photo')) {
            $table->addColumn('photo', 'string', ['limit' => 255, 'null' => true])->update();
        }
    }
}