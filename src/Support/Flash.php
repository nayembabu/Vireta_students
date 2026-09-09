<?php

declare(strict_types=1);

namespace App\Support;

use Odan\Session\SessionInterface;

final class Flash
{
    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function info(string $message): void
    {
        $this->add('info', $message);
    }

    public function success(string $message): void
    {
        $this->add('success', $message);
    }

    public function error(string $message): void
    {
        $this->add('error', $message);
    }

    public function add(string $status, string $message): void
    {
        $messages = $this->session->get('flash') ?? [];
        $messages[] = ['status' => $status, 'message' => $message];
        $this->session->set('flash', $messages);
    }

    public function pull(): array
    {
        $messages = $this->session->get('flash') ?? [];
        $this->session->delete('flash');
        return $messages;
    }
}