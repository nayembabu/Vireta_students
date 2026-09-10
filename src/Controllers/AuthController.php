<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\BasePath;
use App\Support\Flash;
use DateTimeImmutable;
use Odan\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AuthController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash,
        private readonly SessionInterface $session
    ) {
    }

    public function showRegister(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/register_step1.html.twig', [
            'old' => ['educational_registration_no' => '', 'phone_no' => ''],
            'errors' => ['educational_registration_no' => '', 'phone_no' => ''],
        ]);
    }

    public function postRegister(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $regNo = trim((string)($data['educational_registration_no'] ?? ''));
        $phone = $this->normalizePhone((string)($data['phone_no'] ?? ''));

        $errors = [];
        if ($regNo === '') {
            $errors['educational_registration_no'] = 'Please enter your registration number.';
        }
        if ($phone === '') {
            $errors['phone_no'] = 'Please enter your mobile number.';
        }

        if ($errors !== []) {
            return $this->render($response, 'auth/register_step1.html.twig', [
                'old' => ['educational_registration_no' => $regNo, 'phone_no' => $data['phone_no'] ?? ''],
                'errors' => array_merge(['educational_registration_no' => '', 'phone_no' => ''], $errors),
            ]);
        }

        $student = Student::where('educational_registration_no', $regNo)
            ->where('phone_no', $phone)
            ->first();

        if (!$student instanceof Student) {
            $this->flash->error('We could not find your information in our records. Please try again with the correct registration and mobile number.');
            return $this->redirect('/register');
        }

        if ($student->status === 'registered') {
            $this->flash->error('An account already exists with this information. Please sign in.');
            return $this->redirect('/login');
        }

        if ($student->status === 'blocked') {
            $this->flash->error('Account creation is currently blocked for you. Please contact the ViretaDev office.');
            return $this->redirect('/register');
        }

        $this->session->set('pending_registration_student_id', $student->id);

        return $this->redirect('/register/details');
    }

    public function showDetails(Request $request, Response $response): Response
    {
        $student = $this->pendingStudent($request);

        if ($student === null) {
            return $this->redirect('/register');
        }

        return $this->render($response, 'auth/register_step2.html.twig', [
            'student' => $student,
            'old' => $this->emptyPersonalFields(),
            'errors' => $this->emptyPersonalFields(),
        ]);
    }

    public function postDetails(Request $request, Response $response): Response
    {
        $student = $this->pendingStudent($request);

        if ($student === null) {
            return $this->redirect('/register');
        }

        $data = $request->getParsedBody();
        $old = [
            'name' => trim((string)($data['name'] ?? '')),
            'father_name' => trim((string)($data['father_name'] ?? '')),
            'mother_name' => trim((string)($data['mother_name'] ?? '')),
            'address' => trim((string)($data['address'] ?? '')),
            'ssc_roll' => trim((string)($data['ssc_roll'] ?? '')),
            'ssc_registration' => trim((string)($data['ssc_registration'] ?? '')),
            'whatsapp_number' => trim((string)($data['whatsapp_number'] ?? '')),
            'emergency_phone' => trim((string)($data['emergency_phone'] ?? '')),
            'date_of_birth' => trim((string)($data['date_of_birth'] ?? '')),
            'gender' => trim((string)($data['gender'] ?? '')),
            'blood_group' => trim((string)($data['blood_group'] ?? '')),
            'nid_birth_no' => trim((string)($data['nid_birth_no'] ?? '')),
        ];

        $errors = $this->validatePersonalInfo($old);

        if ($errors !== []) {
            return $this->render($response, 'auth/register_step2.html.twig', [
                'student' => $student,
                'old' => $old,
                'errors' => array_merge($this->emptyPersonalFields(), $errors),
            ]);
        }

        $dob = DateTimeImmutable::createFromFormat('Y-m-d', $old['date_of_birth']) ?: null;

        $student->update([
            'name' => $old['name'],
            'father_name' => $old['father_name'],
            'mother_name' => $old['mother_name'],
            'address' => $old['address'] !== '' ? $old['address'] : null,
            'ssc_roll' => $old['ssc_roll'],
            'ssc_registration' => $old['ssc_registration'],
            'whatsapp_number' => $old['whatsapp_number'] !== '' ? $old['whatsapp_number'] : null,
            'emergency_phone' => $old['emergency_phone'] !== '' ? $old['emergency_phone'] : null,
            'date_of_birth' => $dob,
            'gender' => $old['gender'],
            'blood_group' => $old['blood_group'] !== '' ? strtoupper($old['blood_group']) : null,
            'nid_birth_no' => $old['nid_birth_no'] !== '' ? $old['nid_birth_no'] : null,
        ]);

        return $this->redirect('/register/account');
    }

    public function showAccount(Request $request, Response $response): Response
    {
        $student = $this->pendingStudent($request);

        if ($student === null) {
            return $this->redirect('/register');
        }

        return $this->render($response, 'auth/register_step3.html.twig', [
            'student' => $student,
            'old' => ['email' => ''],
            'errors' => ['email' => '', 'password' => '', 'password_confirmation' => ''],
        ]);
    }

    public function postAccount(Request $request, Response $response): Response
    {
        $student = $this->pendingStudent($request);

        if ($student === null) {
            return $this->redirect('/register');
        }

        $data = $request->getParsedBody();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        $confirm = (string)($data['password_confirmation'] ?? '');

        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (User::where('email', $email)->exists()) {
            $errors['email'] = 'An account already exists with this email.';
        } elseif (Student::where('email', $email)->exists()) {
            $errors['email'] = 'This email is already used by another account.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($confirm !== $password) {
            $errors['password_confirmation'] = 'The passwords do not match.';
        }

        if ($errors !== []) {
            return $this->render($response, 'auth/register_step3.html.twig', [
                'student' => $student,
                'old' => ['email' => $email],
                'errors' => array_merge(['email' => '', 'password' => '', 'password_confirmation' => ''], $errors),
            ]);
        }

        $user = User::create([
            'name' => $student->name,
            'username' => $this->uniqueUsername($email),
            'email' => $email,
            'phone' => $student->phone_no,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => Role::STUDENT,
            'status' => 'active',
            'batch_id' => $student->batch_id,
            'student_id' => $student->id,
        ]);

        $student->update([
            'email' => $email,
            'status' => 'registered',
            'registered_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auth->login($user);
        $this->flash->success('Welcome, ' . $student->name . '! Your account has been created successfully.');

        return $this->redirect('/');
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.html.twig', [
            'old' => ['email' => ''],
            'errors' => [],
        ]);
    }

    public function postLogin(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');

        if ($this->auth->attempt($email, $password)) {
            $user = $this->auth->user();
            $this->flash->success('Welcome, ' . $user->name . '!');
            return $this->redirect('/');
        }

        $this->flash->error('Invalid email or password.');
        return $this->redirect('/login');
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->auth->logout();
        $this->flash->info('You have been logged out.');
        return $this->redirect('/login');
    }

    private function pendingStudent(Request $request): ?Student
    {
        $studentId = $this->session->get('pending_registration_student_id');

        if ($studentId === null) {
            return null;
        }

        $student = Student::find($studentId);

        if ($student === null || $student->status !== 'pending') {
            $this->session->delete('pending_registration_student_id');
            return null;
        }

        return $student;
    }

    private function emptyPersonalFields(): array
    {
        return array_fill_keys([
            'name', 'father_name', 'mother_name', 'address', 'ssc_roll',
            'ssc_registration', 'whatsapp_number', 'emergency_phone',
            'date_of_birth', 'gender', 'blood_group', 'nid_birth_no',
        ], '');
    }

    private function validatePersonalInfo(array $old): array
    {
        $errors = [];

        foreach (['name', 'father_name', 'mother_name', 'ssc_roll', 'ssc_registration'] as $field) {
            if ($old[$field] === '') {
                $errors[$field] = 'Please fill in this field.';
            }
        }

        if ($old['date_of_birth'] === '') {
            $errors['date_of_birth'] = 'Please enter your date of birth.';
        } elseif (!DateTimeImmutable::createFromFormat('Y-m-d', $old['date_of_birth'])) {
            $errors['date_of_birth'] = 'Please enter a valid date (YYYY-MM-DD).';
        }

        if (!in_array($old['gender'], ['male', 'female', 'other'], true)) {
            $errors['gender'] = 'Please select your gender.';
        }

        return $errors;
    }

    private function uniqueUsername(string $email): string
    {
        $base = strtolower(trim(explode('@', $email)[0] ?? ''));
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?: 'user';

        $username = $base;
        $i = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base . $i;
            $i++;
        }

        return $username;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone) ?? '';
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        if (str_starts_with($phone, '+880')) {
            $digits = substr($phone, 4);
            if (strlen($digits) === 10) {
                return $phone;
            }
        }

        if (str_starts_with($phone, '8801') && strlen($phone) === 13) {
            return '+' . $phone;
        }

        if (str_starts_with($phone, '01') && strlen($phone) === 11) {
            return '+880' . substr($phone, 1);
        }

        return $phone;
    }

    private function render(Response $response, string $template, array $data = []): Response
    {
        return $this->twig->render($response, $template, $data);
    }

    private function redirect(string $url): Response
    {
        $response = (new \Slim\Psr7\Response())->withStatus(302);
        return $response->withHeader('Location', BasePath::detect($_SERVER) . $url);
    }
}