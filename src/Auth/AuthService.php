<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Odan\Session\SessionManagerInterface;

final class AuthService
{
    public function __construct(private readonly SessionManagerInterface $session)
    {
    }

    public function attempt(string $identifier, string $password): bool
    {
        $user = User::where('email', $identifier)->first();

        if (!$user instanceof User) {
            return false;
        }

        if (!password_verify($password, $user->password_hash)) {
            return false;
        }

        if ($user->status !== 'active') {
            return false;
        }

        $this->login($user);
        return true;
    }

    public function login(User $user): void
    {
        $this->session->regenerateId();
        $this->session->set('user_id', $user->id);
    }

    public function logout(): void
    {
        $this->session->delete('user_id');
        $this->session->regenerateId();
    }

    public function id(): ?int
    {
        $id = $this->session->get('user_id');
        return $id !== null ? (int)$id : null;
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function user(): ?User
    {
        if (!$this->check()) {
            return null;
        }
        return User::find($this->id());
    }
}