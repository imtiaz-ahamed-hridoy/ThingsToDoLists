<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function markEmailAsVerified(User $user): bool;

    public function createResetPasswordToken(string $email): string;

    public function findResetPasswordToken(string $email, string $token): ?object;

    public function deleteResetPasswordTokenByEmail(string $email): bool;
}
