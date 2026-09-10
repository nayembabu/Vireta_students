<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\EmailVerification;
use App\Models\Student;
use App\Models\User;
use App\Support\BasePath;
use App\Support\Flash;
use DateTime;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ProfileController
{
    private const UPLOAD_DIR = __DIR__ . '/../../public/uploads/profiles';

    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger
    ) {
    }

    private function studentArray(User $user): ?array
    {
        $student = $user->student()->first() ?? $this->fallbackStudent($user);

        return $student?->load('batch')->toArray();
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        return $this->render($response, 'profile/index.html.twig', [
            'user' => $user,
            'student' => $this->studentArray($user),
            'errors' => $this->emptyErrors(),
            'old' => [],
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $student = $user->student()->first() ?? $this->fallbackStudent($user);

        $data = $request->getParsedBody();
        $old = [
            'name' => trim((string)($data['name'] ?? '')),
            'username' => strtolower(trim((string)($data['username'] ?? ''))),
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

        $errors = $this->validateProfile($old, $user->id);

        if ($errors !== []) {
            return $this->render($response, 'profile/index.html.twig', [
                'user' => $user,
                'student' => $this->studentArray($user),
                'errors' => array_merge($this->emptyErrors(), $errors),
                'old' => $old,
            ]);
        }

        $dob = DateTimeImmutable::createFromFormat('Y-m-d', $old['date_of_birth']);

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

        $user->update(['name' => $old['name'], 'username' => $old['username']]);

        $this->flash->success('Profile updated successfully.');

        return $this->redirect('/profile');
    }

    public function photo(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $student = $user->student()->first() ?? $this->fallbackStudent($user);

        $files = $request->getUploadedFiles();
        /** @var UploadedFileInterface|null $uploaded */
        $uploaded = $files['pro_pic'] ?? null;

        if ($uploaded === null || $uploaded->getError() !== UPLOAD_ERR_OK) {
            $this->flash->error('There was a problem uploading the photo. Please try again.');
            return $this->redirect('/profile');
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $uploaded->getClientMediaType();

        if (!isset($allowed[$mime])) {
            $this->flash->error('Only JPG, PNG or WEBP images are allowed.');
            return $this->redirect('/profile');
        }

        if ($uploaded->getSize() > 2 * 1024 * 1024) {
            $this->flash->error('The photo size must be at most 2MB.');
            return $this->redirect('/profile');
        }

        $filename = sprintf('s%d_%d.%s', $student->id, time(), $allowed[$mime]);

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0775, true);
        }

        $uploaded->moveTo(self::UPLOAD_DIR . '/' . $filename);

        if ($student->pro_pic !== null) {
            $oldPath = self::UPLOAD_DIR . '/' . basename($student->pro_pic);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $student->update(['pro_pic' => 'profiles/' . $filename]);

        $this->flash->success('Profile photo updated.');

        return $this->redirect('/profile');
    }

    public function password(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $current = (string)($data['current_password'] ?? '');
        $new = (string)($data['new_password'] ?? '');
        $confirm = (string)($data['new_password_confirmation'] ?? '');

        if (!password_verify($current, $user->password_hash)) {
            $this->flash->error('Your current password is incorrect.');
            return $this->redirect('/profile');
        }

        if (strlen($new) < 8) {
            $this->flash->error('নতুন Password must be at least 8 characters.।');
            return $this->redirect('/profile');
        }

        if ($new !== $confirm) {
            $this->flash->error('নতুন The passwords do not match.।');
            return $this->redirect('/profile');
        }

        $user->update(['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);

        $this->auth->logout();
        $this->flash->success('Password changed. Please sign in with your new password.');

        return $this->redirect('/login');
    }

    public function email(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $data = $request->getParsedBody();
        $newEmail = strtolower(trim((string)($data['email'] ?? '')));

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->flash->error('Please enter a valid email address.।');
            return $this->redirect('/profile');
        }

        if (User::where('email', $newEmail)->exists() || Student::where('email', $newEmail)->exists()) {
            $this->flash->error('This email is already used by another account.');
            return $this->redirect('/profile');
        }

        $token = bin2hex(random_bytes(32));

        EmailVerification::where('user_id', $user->id)->delete();

        EmailVerification::create([
            'user_id' => $user->id,
            'new_email' => $newEmail,
            'token' => $token,
            'expires_at' => (new DateTime('+30 minutes'))->format('Y-m-d H:i:s'),
        ]);

        $scheme = $request->getUri()->getScheme();
        $host = $request->getUri()->getHost();
        $link = sprintf('%s://%s%s/profile/email/verify?token=%s', $scheme, $host, BasePath::detect($_SERVER), $token);

        try {
            $mail = (new Email())
                ->from('no-reply@viretadev.com')
                ->to($newEmail)
                ->subject('Email Address Confirmation - ' . getenv('APP_NAME') ?: 'ViretaDev Student Portal')
                ->html(sprintf('<p>Click the link below to confirm your new email address:</p><p><a href="%s">%s</a></p><p>This link is valid for 30 minutes.</p>', $link, $link));

            $this->mailer->send($mail);
            $this->logger->info('Email verification sent', ['user_id' => $user->id, 'to' => $newEmail, 'link' => $link]);
        } catch (\Throwable $e) {
            $this->logger->error('Email verification send failed', ['user_id' => $user->id, 'error' => $e->getMessage(), 'link' => $link]);
        }

        $this->flash->success('A confirmation link has been sent to your new email. Your email will change after you click it.');

        return $this->redirect('/profile');
    }

    public function verifyEmail(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $token = (string)($query['token'] ?? '');
        $record = EmailVerification::where('token', $token)->first();

        if (!$record instanceof EmailVerification) {
            $this->flash->error('The confirmation link is invalid.');
            return $this->redirect('/profile');
        }

        if ($record->expires_at instanceof DateTimeInterface && $record->expires_at->getTimestamp() < time()) {
            $this->flash->error('The link has expired. Please try again.');
            $record->delete();
            return $this->redirect('/profile');
        }

        $user = $record->user;

        $user->update(['email' => $record->new_email]);

        if ($user->student !== null) {
            $user->student->update(['email' => $record->new_email]);
        } elseif ($user->student_id !== null) {
            Student::whereKey($user->student_id)->update(['email' => $record->new_email]);
        }

        $record->delete();

        $this->flash->success('Email confirmed. Please sign in with your new email from now on.');

        return $this->redirect('/profile');
    }

    private function fallbackStudent(User $user): ?Student
    {
        return $user->student_id !== null ? Student::find($user->student_id) : null;
    }

    private function validateProfile(array $old, int $userId): array
    {
        $errors = [];

        foreach (['name', 'father_name', 'mother_name', 'ssc_roll', 'ssc_registration'] as $field) {
            if ($old[$field] === '') {
                $errors[$field] = 'Please fill in this field.';
            }
        }

        $username = $old['username'];
        if ($username === '') {
            $errors['username'] = 'Please enter a username.';
        } elseif (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            $errors['username'] = 'Use 3-30 characters: letters, numbers, dot, dash or underscore.';
        } elseif (User::where('username', $username)->where('id', '!=', $userId)->exists()) {
            $errors['username'] = 'This username is already taken.';
        }

        if ($old['date_of_birth'] === '') {
            $errors['date_of_birth'] = 'Please enter your date of birth.';
        } elseif (!DateTimeImmutable::createFromFormat('Y-m-d', $old['date_of_birth'])) {
            $errors['date_of_birth'] = 'Please enter a valid date.';
        }

        if (!in_array($old['gender'], ['male', 'female', 'other'], true)) {
            $errors['gender'] = 'Please select your gender.';
        }

        return $errors;
    }

    private function emptyErrors(): array
    {
        return [
            'name' => '',
            'username' => '',
            'father_name' => '',
            'mother_name' => '',
            'date_of_birth' => '',
            'gender' => '',
            'ssc_roll' => '',
            'ssc_registration' => '',
        ];
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