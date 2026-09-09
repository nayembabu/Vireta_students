<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Personal profile fields on the students (pre-registration) record.
 * Only reg_no + phone_no are pre-seeded; the rest are filled in
 * during the registration form after validation, before a user is created.
 */
final class AddPersonalInfoToStudents extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('students');
        $table
            ->addColumn('father_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('mother_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('address', 'text', ['null' => true])
            ->addColumn('pro_pic', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('ssc_roll', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('ssc_registration', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('whatsapp_number', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('emergency_phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('date_of_birth', 'date', ['null' => true])
            ->addColumn('gender', 'enum', ['values' => ['male', 'female', 'other'], 'null' => true, 'default' => null])
            ->addColumn('blood_group', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('nid_birth_no', 'string', ['limit' => 60, 'null' => true])
            ->addIndex(['email'], ['unique' => true])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('students');
        $table
            ->removeIndex(['email'])
            ->removeColumn('nid_birth_no')
            ->removeColumn('blood_group')
            ->removeColumn('gender')
            ->removeColumn('date_of_birth')
            ->removeColumn('emergency_phone')
            ->removeColumn('whatsapp_number')
            ->removeColumn('ssc_registration')
            ->removeColumn('ssc_roll')
            ->removeColumn('pro_pic')
            ->removeColumn('address')
            ->removeColumn('email')
            ->removeColumn('mother_name')
            ->removeColumn('father_name')
            ->update();
    }
}