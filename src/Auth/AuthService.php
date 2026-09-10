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
        $identifier = strtolower(trim($identifier));

        $query = User::where('email', $identifier)
            ->orWhere('username', $identifier);

        $phone = $this->normalizePhone($identifier);
        if ($phone !== '') {
            $query->orWhere('phone', $phone);
        }

        $user = $query->first();

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
}