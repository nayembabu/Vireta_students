<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Initial schema for ViretaDev Intern Student Portal.
 *
 * Tables:
 *  - users
 *  - batches
 *  - batch_user
 *  - courses
 *  - user_courses (enrollment)
 *  - modules
 *  - lessons
 *  - lesson_progress
 *  - attendance
 *  - assignments
 *  - assignment_submissions
 *  - notifications
 *  - password_resets
 */
final class CreateInitialSchema extends AbstractMigration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // users
        // ------------------------------------------------------------------
        $this->table('users', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('role', 'enum', ['values' => ['admin', 'mentor', 'student'], 'default' => 'student'])
            ->addColumn('status', 'enum', ['values' => ['active', 'inactive', 'suspended'], 'default' => 'active'])
            ->addColumn('photo', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('batch_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['batch_id'])
            ->create();

        // ------------------------------------------------------------------
        // batches
        // ------------------------------------------------------------------
        $this->table('batches', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('start_date', 'date', ['null' => true])
            ->addColumn('end_date', 'date', ['null' => true])
            ->addColumn('status', 'enum', ['values' => ['upcoming', 'active', 'completed', 'archived'], 'default' => 'upcoming'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // users.batch_id FK
        $this->table('users')
            ->addForeignKey('batch_id', 'batches', 'id', ['delete' => 'SET_NULL'])
            ->update();

        // ------------------------------------------------------------------
        // courses
        // ------------------------------------------------------------------
        $this->table('courses', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('title', 'string', ['limit' => 190])
            ->addColumn('slug', 'string', ['limit' => 190])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('thumbnail', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('duration_weeks', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('status', 'enum', ['values' => ['draft', 'published', 'archived'], 'default' => 'draft'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        // ------------------------------------------------------------------
        // user_courses (enrollment)
        // ------------------------------------------------------------------
        $this->table('user_courses', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('course_id', 'biginteger', ['signed' => false])
            ->addColumn('status', 'enum', ['values' => ['enrolled', 'in_progress', 'completed', 'dropped'], 'default' => 'enrolled'])
            ->addColumn('enrolled_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('completed_at', 'timestamp', ['null' => true])
            ->addIndex(['user_id', 'course_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('course_id', 'courses', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ------------------------------------------------------------------
        // modules
        // ------------------------------------------------------------------
        $this->table('modules', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('course_id', 'biginteger', ['signed' => false])
            ->addColumn('title', 'string', ['limit' => 190])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addIndex(['course_id', 'sort_order'])
            ->addForeignKey('course_id', 'courses', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ------------------------------------------------------------------
        // lessons
        // ------------------------------------------------------------------
        $this->table('lessons', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('module_id', 'biginteger', ['signed' => false])
            ->addColumn('title', 'string', ['limit' => 190])
            ->addColumn('content_url', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('content_type', 'enum', ['values' => ['video', 'pdf', 'text', 'link'], 'default' => 'text'])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addIndex(['module_id', 'sort_order'])
            ->addForeignKey('module_id', 'modules', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ------------------------------------------------------------------
        // lesson_progress
        // ------------------------------------------------------------------
        $this->table('lesson_progress', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('lesson_id', 'biginteger', ['signed' => false])
            ->addColumn('status', 'enum', ['values' => ['pending', 'in_progress', 'completed'], 'default' => 'pending'])
            ->addColumn('completed_at', 'timestamp', ['null' => true])
            ->addIndex(['user_id', 'lesson_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('lesson_id', 'lessons', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ------------------------------------------------------------------
        // attendance
        // ------------------------------------------------------------------
        $this->table('attendance', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('course_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('session_date', 'date')
            ->addColumn('status', 'enum', ['values' => ['present', 'absent', 'late', 'excused'], 'default' => 'present'])
            ->addColumn('check_in_time', 'time', ['null' => true])
            ->addColumn('marked_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addIndex(['user_id', 'session_date'])
            ->addIndex(['course_id', 'session_date'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('course_id', 'courses', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('marked_by', 'users', 'id', ['delete' => 'SET_NULL'])
            ->create();

        // ------------------------------------------------------------------
        // assignments
        // ------------------------------------------------------------------
        $this->table('assignments', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('course_id', 'biginteger', ['signed' => false])
            ->addColumn('title', 'string', ['limit' => 190])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('due_date', 'datetime', ['null' => true])
            ->addColumn('max_score', 'integer', ['signed' => false, 'default' => 100])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['course_id', 'due_date'])
            ->addForeignKey('course_id', 'courses', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ------------------------------------------------------------------
        // assignment_submissions
        // ------------------------------------------------------------------
        $this->table('assignment_submissions', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('assignment_id', 'biginteger', ['signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('submission_text', 'text', ['null' => true])
            ->addColumn('file_path', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('score', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('feedback', 'text', ['null' => true])
            ->addColumn('graded_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('status', 'enum', ['values' => ['submitted', 'graded', 'returned'], 'default' => 'submitted'])
            ->addColumn('submitted_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('graded_at', 'timestamp', ['null' => true])
            ->addIndex(['assignment_id', 'user_id'], ['unique' => true])
            ->addForeignKey('assignment_id', 'assignments', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('graded_by', 'users', 'id', ['delete' => 'SET_NULL'])
            ->create();

        // ------------------------------------------------------------------
        // notifications
        // ------------------------------------------------------------------
        $this->table('notifications', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('title', 'string', ['limit' => 190])
            ->addColumn('body', 'text', ['null' => true])
            ->addColumn('type', 'string', ['limit' => 50, 'default' => 'general'])
            ->addColumn('is_read', 'boolean', ['default' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'is_read'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        // ------------------------------------------------------------------
        // password_resets
        // ------------------------------------------------------------------
        $this->table('password_resets', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('token', 'string', ['limit' => 64])
            ->addColumn('expires_at', 'timestamp')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['email'])
            ->create();
    }

    public function down(): void
    {
        $this->table('password_resets')->drop()->save();
        $this->table('notifications')->drop()->save();
        $this->table('assignment_submissions')->drop()->save();
        $this->table('assignments')->drop()->save();
        $this->table('attendance')->drop()->save();
        $this->table('lesson_progress')->drop()->save();
        $this->table('lessons')->drop()->save();
        $this->table('modules')->drop()->save();
        $this->table('user_courses')->drop()->save();
        $this->table('courses')->drop()->save();
        $this->table('users')->drop()->save();
        $this->table('batches')->drop()->save();
    }
}