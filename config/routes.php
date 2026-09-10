<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Middleware\RedirectIfAuthenticatedMiddleware;
use App\Middleware\RequireAuthMiddleware;
use App\Middleware\RequireRoleMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {
    $container = $app->getContainer();

    $app->get('/', [\App\Controllers\DashboardController::class, 'index'])->setName('home');

    // Health check (useful for dev, uptime checks)
    $app->get('/ping', function (Request $request, Response $response) {
        $response->getBody()->write('pong');
        return $response;
    })->setName('ping');

    // Guest-only routes (register + login)
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/register', [AuthController::class, 'showRegister'])->setName('register');
        $app->post('/register', [AuthController::class, 'postRegister']);
        $app->get('/register/details', [AuthController::class, 'showDetails'])->setName('register.details');
        $app->post('/register/details', [AuthController::class, 'postDetails']);
        $app->get('/register/account', [AuthController::class, 'showAccount'])->setName('register.account');
        $app->post('/register/account', [AuthController::class, 'postAccount']);
        $app->get('/login', [AuthController::class, 'showLogin'])->setName('login');
        $app->post('/login', [AuthController::class, 'postLogin']);
    })->add($container->get(RedirectIfAuthenticatedMiddleware::class));

    // Authenticated-only routes
    $app->group('', function (RouteCollectorProxy $app) {
        $app->get('/profile', [\App\Controllers\ProfileController::class, 'index'])->setName('profile');
        $app->post('/profile', [\App\Controllers\ProfileController::class, 'update']);
        $app->post('/profile/photo', [\App\Controllers\ProfileController::class, 'photo']);
        $app->post('/profile/password', [\App\Controllers\ProfileController::class, 'password']);
        $app->post('/profile/email', [\App\Controllers\ProfileController::class, 'email']);
        $app->get('/profile/email/verify', [\App\Controllers\ProfileController::class, 'verifyEmail']);

        $app->get('/assignments', [\App\Controllers\AssignmentController::class, 'index'])->setName('assignments');
        $app->get('/assignments/{id:[0-9]+}', [\App\Controllers\AssignmentController::class, 'show']);
        $app->post('/assignments/{id:[0-9]+}/submit', [\App\Controllers\AssignmentController::class, 'submit']);

        $app->get('/routine', [\App\Controllers\RoutineController::class, 'index'])->setName('routine');

        $app->get('/attendance', [\App\Controllers\AttendanceController::class, 'index'])->setName('attendance');

        $app->get('/fees', [\App\Controllers\FeeController::class, 'index'])->setName('fees');
        $app->post('/fees/pay', [\App\Controllers\FeeController::class, 'pay']);
    })->add($container->get(RequireAuthMiddleware::class));

    // ================= Admin panel (staff only) =================
    $app->group('/admin', function (RouteCollectorProxy $app) {
        // Dashboard
        $app->get('', [\App\Controllers\AdminDashboardController::class, 'index'])->setName('admin.home');
        $app->get('/', [\App\Controllers\AdminDashboardController::class, 'index']);

        // Courses
        $app->get('/courses', [\App\Controllers\AdminCourseController::class, 'index'])->setName('admin.courses');
        $app->get('/courses/create', [\App\Controllers\AdminCourseController::class, 'createForm'])->setName('admin.courses.create');
        $app->post('/courses/create', [\App\Controllers\AdminCourseController::class, 'create']);
        $app->get('/courses/{id:[0-9]+}/edit', [\App\Controllers\AdminCourseController::class, 'editForm'])->setName('admin.courses.edit');
        $app->post('/courses/{id:[0-9]+}/edit', [\App\Controllers\AdminCourseController::class, 'edit']);
        $app->post('/courses/{id:[0-9]+}/delete', [\App\Controllers\AdminCourseController::class, 'delete'])->setName('admin.courses.delete');

        // Batches
        $app->get('/batches', [\App\Controllers\AdminBatchController::class, 'index'])->setName('admin.batches');
        $app->get('/batches/create', [\App\Controllers\AdminBatchController::class, 'createForm'])->setName('admin.batches.create');
        $app->post('/batches/create', [\App\Controllers\AdminBatchController::class, 'create']);
        $app->get('/batches/{id:[0-9]+}/edit', [\App\Controllers\AdminBatchController::class, 'editForm'])->setName('admin.batches.edit');
        $app->post('/batches/{id:[0-9]+}/edit', [\App\Controllers\AdminBatchController::class, 'edit']);
        $app->post('/batches/{id:[0-9]+}/delete', [\App\Controllers\AdminBatchController::class, 'delete'])->setName('admin.batches.delete');

        // Students (pre-registration records)
        $app->get('/students', [\App\Controllers\AdminStudentController::class, 'index'])->setName('admin.students');
        $app->get('/students/create', [\App\Controllers\AdminStudentController::class, 'createForm'])->setName('admin.students.create');
        $app->post('/students/create', [\App\Controllers\AdminStudentController::class, 'create']);
        $app->get('/students/import', [\App\Controllers\AdminStudentController::class, 'importForm'])->setName('admin.students.import');
        $app->post('/students/import', [\App\Controllers\AdminStudentController::class, 'import']);
        $app->get('/students/{id:[0-9]+}', [\App\Controllers\AdminStudentController::class, 'show'])->setName('admin.students.show');
        $app->get('/students/{id:[0-9]+}/edit', [\App\Controllers\AdminStudentController::class, 'editForm'])->setName('admin.students.edit');
        $app->post('/students/{id:[0-9]+}/edit', [\App\Controllers\AdminStudentController::class, 'edit']);
        $app->post('/students/{id:[0-9]+}/block', [\App\Controllers\AdminStudentController::class, 'toggleBlock'])->setName('admin.students.block');
        $app->post('/students/{id:[0-9]+}/delete', [\App\Controllers\AdminStudentController::class, 'delete'])->setName('admin.students.delete');

        // Routine CRUD
        $app->get('/routines', [\App\Controllers\AdminRoutineController::class, 'index'])->setName('admin.routines');
        $app->get('/routines/create', [\App\Controllers\AdminRoutineController::class, 'createForm'])->setName('admin.routines.create');
        $app->post('/routines/create', [\App\Controllers\AdminRoutineController::class, 'create']);
        $app->get('/routines/{id:[0-9]+}/edit', [\App\Controllers\AdminRoutineController::class, 'editForm'])->setName('admin.routines.edit');
        $app->post('/routines/{id:[0-9]+}/edit', [\App\Controllers\AdminRoutineController::class, 'edit']);
        $app->post('/routines/{id:[0-9]+}/delete', [\App\Controllers\AdminRoutineController::class, 'delete'])->setName('admin.routines.delete');

        // Attendance
        $app->get('/attendance', [\App\Controllers\AdminAttendanceController::class, 'index'])->setName('admin.attendance');
        $app->post('/attendance/{id:[0-9]+}/override', [\App\Controllers\AdminAttendanceController::class, 'override'])->setName('admin.attendance.override');
        $app->get('/attendance/mark', [\App\Controllers\AdminAttendanceController::class, 'markForm'])->setName('admin.attendance.mark');
        $app->post('/attendance/mark', [\App\Controllers\AdminAttendanceController::class, 'mark']);

        // Payments
        $app->get('/payments', [\App\Controllers\AdminPaymentController::class, 'index'])->setName('admin.payments');
        $app->get('/payments/create', [\App\Controllers\AdminPaymentController::class, 'createForm'])->setName('admin.payments.create');
        $app->post('/payments/create', [\App\Controllers\AdminPaymentController::class, 'create']);
        $app->post('/payments/{id:[0-9]+}/verify', [\App\Controllers\AdminPaymentController::class, 'verify'])->setName('admin.payments.verify');
        $app->post('/payments/{id:[0-9]+}/reject', [\App\Controllers\AdminPaymentController::class, 'reject'])->setName('admin.payments.reject');
        $app->post('/payments/{id:[0-9]+}/deadline', [\App\Controllers\AdminPaymentController::class, 'deadline'])->setName('admin.payments.deadline');

        // Assignments (grading)
        $app->get('/assignments', [\App\Controllers\AdminAssignmentController::class, 'index'])->setName('admin.assignments');
        $app->get('/assignments/grade/{id:[0-9]+}', [\App\Controllers\AdminAssignmentController::class, 'gradeForm'])->setName('admin.assignments.grade');
        $app->post('/assignments/grade/{id:[0-9]+}', [\App\Controllers\AdminAssignmentController::class, 'grade']);
    })->add(new RequireRoleMiddleware($container->get(\App\Auth\AuthService::class), ['admin', 'trainer', 'cashier']));

    $app->post('/logout', [AuthController::class, 'logout'])
        ->setName('logout')
        ->add($container->get(RequireAuthMiddleware::class));
};