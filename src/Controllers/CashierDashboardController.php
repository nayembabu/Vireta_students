<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Models\Payment;
use App\Models\User;
use App\Support\BasePath;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class CashierDashboardController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly AuthService $auth
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->auth->user();

        $counts = [
            'pending' => Payment::where('status', 'pending')->count(),
            'verified' => Payment::where('status', 'verified')->count(),
            'rejected' => Payment::where('status', 'rejected')->count(),
        ];

        // ---- Payments report ----
        $verifiedPayments = Payment::where('status', 'verified')->get();
        $report = [
            'total_collected' => (float)$verifiedPayments->sum('amount'),
            'pending_amount' => (float)Payment::where('status', 'pending')->sum('amount'),
            'recent' => Payment::orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(static function (Payment $p): array {
                    return [
                        'id' => $p->id,
                        'student' => $p->user?->name,
                        'course' => $p->course?->title,
                        'trx_id' => $p->trx_id,
                        'amount' => (float)$p->amount,
                        'status' => $p->status,
                        'date' => $p->created_at instanceof \DateTimeInterface ? $p->created_at->format('Y-m-d H:i') : null,
                    ];
                })
                ->values()
                ->all(),
        ];

        // ---- Batch-wise students view ----
        $students = User::whereHas('role', fn ($q) => $q->where('slug', 'student'))
            ->orderBy('name')
            ->get();

        $batchGroups = [];
        foreach ($students as $s) {
            $key = $s->batch?->name ?? 'No batch';
            $batchGroups[$key][] = [
                'id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
            ];
        }

        return $this->render($response, 'admin/dashboard/cashier.html.twig', [
            'cashier' => [
                'name' => $user->name,
                'today_label' => (new DateTimeImmutable())->format('d M Y'),
            ],
            'counts' => $counts,
            'report' => $report,
            'batch_groups' => $batchGroups,
        ]);
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
