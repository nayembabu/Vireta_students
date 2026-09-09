<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Payment;
use App\Models\UserCourse;
use App\Services\FeesService;
use App\Support\BasePath;
use App\Support\Flash;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;

final class FeeController
{
    private const UPLOAD_DIR = __DIR__ . '/../../public/uploads/payments';

    private const ALLOWED_FILES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ];

    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly FeesService $fees,
        private readonly Flash $flash
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        return $this->render($response, 'fees/index.html.twig', [
            'data' => $this->fees->courses($user, (new DateTimeImmutable())->format('Y-m-d')),
            'history' => $this->fees->history($user),
            'courses' => $this->enrolledCourses($user),
            'batch' => $user->batch?->name,
            'errors' => $this->emptyErrors(),
            'old' => $this->emptyOld(),
        ]);
    }

    public function pay(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $body = $request->getParsedBody();
        $old = [
            'course_id' => trim((string)($body['course_id'] ?? '')),
            'trx_id' => trim((string)($body['trx_id'] ?? '')),
            'sender_number' => trim((string)($body['sender_number'] ?? '')),
            'amount' => trim((string)($body['amount'] ?? '')),
            'note' => trim((string)($body['note'] ?? '')),
        ];

        $errors = $this->validate($old, $request);

        if ($errors !== []) {
            return $this->render($response, 'fees/index.html.twig', [
                'data' => $this->fees->courses($user, (new DateTimeImmutable())->format('Y-m-d')),
                'history' => $this->fees->history($user),
                'courses' => $this->enrolledCourses($user),
                'batch' => $user->batch?->name,
                'errors' => array_merge($this->emptyErrors(), $errors),
                'old' => $old,
            ]);
        }

        $files = $request->getUploadedFiles();
        /** @var UploadedFileInterface|null $uploaded */
        $uploaded = $files['screenshot'] ?? null;

        $screenshot = null;
        if ($uploaded !== null && $uploaded->getError() === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($uploaded->getClientFilename(), PATHINFO_EXTENSION));
            $relDir = 'payments/' . $user->id;
            $absDir = self::UPLOAD_DIR . '/' . $user->id;

            if (!is_dir($absDir)) {
                mkdir($absDir, 0775, true);
            }

            $filename = sprintf('p_%d_%d.%s', $user->id, time(), $ext);
            $uploaded->moveTo($absDir . '/' . $filename);
            $screenshot = $relDir . '/' . $filename;
        }

        Payment::create([
            'user_id' => $user->id,
            'course_id' => (int)$old['course_id'],
            'trx_id' => $old['trx_id'],
            'sender_number' => $old['sender_number'],
            'amount' => (float)$old['amount'],
            'screenshot' => $screenshot,
            'note' => $old['note'] !== '' ? $old['note'] : null,
            'status' => 'pending',
        ]);

        $this->flash->success('পেমেন্ট Submitted। অ্যাডমিন যাচাই করার পর তা যোগ হবে।');

        return $this->redirect('/fees');
    }

    private function enrolledCourses($user): array
    {
        $enrollments = UserCourse::with(['course'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['enrolled', 'in_progress', 'completed'])
            ->get();

        $items = [];
        foreach ($enrollments as $e) {
            if ($e->course !== null) {
                $items[] = ['id' => $e->course->id, 'title' => $e->course->title];
            }
        }

        return $items;
    }

    private function validate(array $old, Request $request): array
    {
        $errors = [];

        $courseId = (int)$old['course_id'];
        if ($courseId <= 0) {
            $errors['course_id'] = 'Please select a course.';
        } elseif (!$this->isEnrolled($this->auth->user()->id, $courseId)) {
            $errors['course_id'] = 'This course is not in your enrollments.';
        }

        if ($old['trx_id'] === '') {
            $errors['trx_id'] = 'Please enter the payment number (TRX ID).';
        } elseif (!preg_match('/^[A-Za-z0-9]{4,60}$/', $old['trx_id'])) {
            $errors['trx_id'] = 'Please enter a valid payment number.';
        } elseif ($this->fees->trxExists($old['trx_id'])) {
            $errors['trx_id'] = 'This payment number has already been used.';
        }

        if ($old['sender_number'] === '') {
            $errors['sender_number'] = 'Please enter the sender number.';
        } elseif (!preg_match('/^01[0-9]{9}$/', $old['sender_number'])) {
            $errors['sender_number'] = 'সঠিক Please enter the sender number. (০১XXXXXXXXX)';
        }

        if ($old['amount'] === '') {
            $errors['amount'] = 'Please enter the amount.';
        } elseif (!is_numeric($old['amount']) || (float)$old['amount'] <= 0) {
            $errors['amount'] = 'সঠিক Please enter the amount.';
        }

        $files = $request->getUploadedFiles();
        /** @var UploadedFileInterface|null $uploaded */
        $uploaded = $files['screenshot'] ?? null;

        if ($uploaded !== null && $uploaded->getError() !== UPLOAD_ERR_NO_FILE) {
            if ($uploaded->getError() !== UPLOAD_ERR_OK) {
                $errors['screenshot'] = 'There was a problem uploading the screenshot.';
            } else {
                $ext = strtolower(pathinfo($uploaded->getClientFilename(), PATHINFO_EXTENSION));
                $mime = $uploaded->getClientMediaType();

                if (!isset(self::ALLOWED_FILES[$ext]) || self::ALLOWED_FILES[$ext] !== $mime) {
                    $errors['screenshot'] = 'Only jpg, png or pdf files are allowed.';
                } elseif ($uploaded->getSize() > self::MAX_FILE_SIZE) {
                    $errors['screenshot'] = 'The screenshot size must be at most 5MB.';
                }
            }
        }

        return $errors;
    }

    private function isEnrolled(int $userId, int $courseId): bool
    {
        return UserCourse::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereIn('status', ['enrolled', 'in_progress', 'completed'])
            ->exists();
    }

    private function emptyErrors(): array
    {
        return [
            'course_id' => '',
            'trx_id' => '',
            'sender_number' => '',
            'amount' => '',
            'screenshot' => '',
        ];
    }

    private function emptyOld(): array
    {
        return [
            'course_id' => '',
            'trx_id' => '',
            'sender_number' => '',
            'amount' => '',
            'note' => '',
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