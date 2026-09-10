<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Batch;
use App\Models\Student;
use App\Support\BasePath;
use App\Support\Flash;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

final class AdminStudentController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth,
        private readonly Flash $flash
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $query = Student::with(['batch', 'user']);

        $search = trim((string)($request->getQueryParams()['q'] ?? ''));
        $status = trim((string)($request->getQueryParams()['status'] ?? ''));
        $batchId = (int)($request->getQueryParams()['batch'] ?? 0);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('educational_registration_no', 'like', "%{$search}%")
                    ->orWhere('phone_no', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status !== '' && in_array($status, ['pending', 'registered', 'blocked'], true)) {
            $query->where('status', $status);
        }

        if ($batchId > 0) {
            $query->where('batch_id', $batchId);
        }

        $students = $query->orderByDesc('id')->get();

        $items = $students->map(static function (Student $s): array {
            return [
                'id' => $s->id,
                'name' => $s->name ?? '—',
                'reg_no' => $s->educational_registration_no,
                'phone' => $s->phone_no,
                'email' => $s->email,
                'batch' => $s->batch?->name,
                'status' => $s->status,
                'registered' => $s->user !== null,
                'account_email' => $s->user?->email,
                'created_at' => $s->created_at instanceof \DateTimeInterface ? $s->created_at->format('Y-m-d') : null,
            ];
        })->values()->all();

        return $this->render($response, 'admin/students/index.html.twig', [
            'items' => $items,
            'batches' => Batch::orderBy('name')->get(),
            'filters' => ['q' => $search, 'status' => $status, 'batch' => $batchId],
        ]);
    }

    public function show(Request $request, Response $response): Response
    {
        $student = $this->find($request);

        if ($student === null) {
            return $this->render404($response, 'Student record not found.');
        }

        return $this->render($response, 'admin/students/show.html.twig', [
            'student' => $this->studentData($student),
        ]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/students/form.html.twig', [
            'student' => null,
            'batches' => Batch::orderBy('name')->get(),
            'old' => $this->emptyOld(),
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $old = $this->normalize($data);

        $errors = $this->validate($old, null);

        if ($errors !== []) {
            return $this->render($response, 'admin/students/form.html.twig', [
                'student' => null,
                'batches' => Batch::orderBy('name')->get(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        Student::create([
            'educational_registration_no' => $old['educational_registration_no'],
            'phone_no' => $this->normalizePhone($old['phone_no']),
            'name' => $old['name'] !== '' ? $old['name'] : null,
            'batch_id' => $old['batch_id'] > 0 ? $old['batch_id'] : null,
            'status' => 'pending',
        ]);

        $this->flash->success('Student pre-record added. They can now register with these details.');

        return $this->redirect('/admin/students');
    }

    public function editForm(Request $request, Response $response): Response
    {
        $student = $this->find($request);

        if ($student === null) {
            return $this->render404($response, 'Student record not found.');
        }

        return $this->render($response, 'admin/students/form.html.twig', [
            'student' => $student,
            'batches' => Batch::orderBy('name')->get(),
            'old' => [
                'educational_registration_no' => $student->educational_registration_no,
                'phone_no' => $student->phone_no,
                'name' => $student->name ?? '',
                'batch_id' => (int)$student->batch_id,
            ],
            'errors' => $this->emptyErrors(),
        ]);
    }

    public function edit(Request $request, Response $response): Response
    {
        $student = $this->find($request);

        if ($student === null) {
            return $this->render404($response, 'Student record not found.');
        }

        $data = $request->getParsedBody();
        $old = $this->normalize($data);

        $errors = $this->validate($old, $student->id);

        if ($errors !== []) {
            return $this->render($response, 'admin/students/form.html.twig', [
                'student' => $student,
                'batches' => Batch::orderBy('name')->get(),
                'old' => $old,
                'errors' => array_merge($this->emptyErrors(), $errors),
            ]);
        }

        $student->update([
            'educational_registration_no' => $old['educational_registration_no'],
            'phone_no' => $this->normalizePhone($old['phone_no']),
            'name' => $old['name'] !== '' ? $old['name'] : null,
            'batch_id' => $old['batch_id'] > 0 ? $old['batch_id'] : null,
        ]);

        $this->flash->success('Student pre-record updated.');

        return $this->redirect('/admin/students');
    }

    public function toggleBlock(Request $request, Response $response): Response
    {
        $student = $this->find($request);

        if ($student === null) {
            return $this->render404($response, 'Student record not found.');
        }

        if ($student->status === 'blocked') {
            $student->update(['status' => 'pending']);
            $this->flash->success('Access restored for this student.');
        } else {
            $student->update(['status' => 'blocked']);
            $this->flash->info('Student blocked. Their account can no longer be used.');
        }

        return $this->redirect('/admin/students');
    }

    public function delete(Request $request, Response $response): Response
    {
        $student = $this->find($request);

        if ($student === null) {
            return $this->render404($response, 'Student record not found.');
        }

        if ($student->user !== null) {
            $this->flash->error('This student already has an account and cannot be deleted.');
            return $this->redirect('/admin/students');
        }

        $student->delete();
        $this->flash->success('Student pre-record deleted.');

        return $this->redirect('/admin/students');
    }

    public function importForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/students/import.html.twig', [
            'batches' => Batch::orderBy('name')->get(),
            'result' => null,
        ]);
    }

    public function import(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $batchId = (int)($body['batch_id'] ?? 0);

        $files = $request->getUploadedFiles();
        $uploaded = $files['csv_file'] ?? null;

        if ($uploaded === null || $uploaded->getError() !== UPLOAD_ERR_OK) {
            $this->flash->error('Please upload a CSV file.');
            return $this->redirect('/admin/students/import');
        }

        $content = (string)file_get_contents($uploaded->getFilePath());
        $rows = $this->parseCsv($content);

        if (count($rows) < 2) {
            $this->flash->error('The CSV file must contain a header row and at least one data row.');
            return $this->redirect('/admin/students/import');
        }

        $header = array_map(static fn ($h) => strtolower(trim((string)$h)), $rows[0]);

        $regIdx = array_search('educational_registration_no', $header, true);
        if ($regIdx === false) {
            $regIdx = array_search('reg_no', $header, true);
        }
        $phoneIdx = array_search('phone_no', $header, true);
        if ($phoneIdx === false) {
            $phoneIdx = array_search('phone', $header, true);
        }
        $nameIdx = array_search('name', $header, true);

        if ($regIdx === false || $phoneIdx === false) {
            $this->flash->error('CSV must have columns: educational_registration_no (or reg_no) and phone_no (or phone).');
            return $this->redirect('/admin/students/import');
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_slice($rows, 1) as $row) {
            $reg = trim((string)($row[$regIdx] ?? ''));
            $phone = $this->normalizePhone(trim((string)($row[$phoneIdx] ?? '')));
            $name = $nameIdx !== false ? trim((string)($row[$nameIdx] ?? '')) : '';

            if ($reg === '' || $phone === '') {
                $skipped++;
                continue;
            }

            if (Student::where('educational_registration_no', $reg)->exists()) {
                $skipped++;
                continue;
            }

            Student::create([
                'educational_registration_no' => $reg,
                'phone_no' => $phone,
                'name' => $name !== '' ? $name : null,
                'batch_id' => $batchId > 0 ? $batchId : null,
                'status' => 'pending',
            ]);
            $created++;
        }

        $this->flash->success(sprintf(
            'Import complete: %d added, %d skipped (duplicate/invalid).',
            $created,
            $skipped
        ));

        return $this->redirect('/admin/students');
    }

    private function find(Request $request): ?Student
    {
        $id = $this->routeId($request);
        return Student::with(['batch', 'user'])->find($id);
    }

    private function studentData(Student $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'reg_no' => $student->educational_registration_no,
            'phone_no' => $student->phone_no,
            'email' => $student->email,
            'batch' => $student->batch?->name,
            'status' => $student->status,
            'registered_at' => $student->registered_at instanceof \DateTimeInterface ? $student->registered_at->format('Y-m-d H:i') : null,
            'created_at' => $student->created_at instanceof \DateTimeInterface ? $student->created_at->format('Y-m-d H:i') : null,
            'father_name' => $student->father_name,
            'mother_name' => $student->mother_name,
            'address' => $student->address,
            'ssc_roll' => $student->ssc_roll,
            'ssc_registration' => $student->ssc_registration,
            'whatsapp_number' => $student->whatsapp_number,
            'emergency_phone' => $student->emergency_phone,
            'date_of_birth' => $student->date_of_birth instanceof \DateTimeInterface ? $student->date_of_birth->format('Y-m-d') : null,
            'gender' => $student->gender,
            'blood_group' => $student->blood_group,
            'nid_birth_no' => $student->nid_birth_no,
            'account' => $student->user !== null ? [
                'email' => $student->user->email,
                'username' => $student->user->username,
                'status' => $student->user->status,
            ] : null,
        ];
    }

    private function normalize(array $data): array
    {
        return [
            'educational_registration_no' => trim((string)($data['educational_registration_no'] ?? '')),
            'phone_no' => trim((string)($data['phone_no'] ?? '')),
            'name' => trim((string)($data['name'] ?? '')),
            'batch_id' => (int)($data['batch_id'] ?? 0),
        ];
    }

    private function validate(array $old, ?int $ignoreId): array
    {
        $errors = [];

        if ($old['educational_registration_no'] === '') {
            $errors['educational_registration_no'] = 'Registration number is required.';
        } elseif (Student::where('educational_registration_no', $old['educational_registration_no'])
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $errors['educational_registration_no'] = 'This registration number is already in use.';
        }

        if ($old['phone_no'] === '') {
            $errors['phone_no'] = 'Phone number is required.';
        } elseif ($this->normalizePhone($old['phone_no']) === '') {
            $errors['phone_no'] = 'Please enter a valid Bangladeshi phone number.';
        } elseif (Student::where('phone_no', $this->normalizePhone($old['phone_no']))
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $errors['phone_no'] = 'This phone number is already in use.';
        }

        return $errors;
    }

    private function parseCsv(string $content): array
    {
        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
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

    private function routeId(Request $request): int
    {
        try {
            $route = RouteContext::fromRequest($request)->getRoute();
            $id = (int)$route->getArgument('id');
        } catch (\Throwable) {
            $id = 0;
        }

        return $id;
    }

    private function emptyErrors(): array
    {
        return [
            'educational_registration_no' => '',
            'phone_no' => '',
            'name' => '',
            'batch_id' => '',
        ];
    }

    private function emptyOld(): array
    {
        return [
            'educational_registration_no' => '',
            'phone_no' => '',
            'name' => '',
            'batch_id' => '',
        ];
    }

    private function render404(Response $response, string $title): Response
    {
        return $this->render($response, 'errors/404_message.html.twig', ['message' => $title])->withStatus(404);
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
