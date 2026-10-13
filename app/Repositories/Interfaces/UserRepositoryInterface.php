<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * Get all users
     */
    public function all(): Collection;

    /**
     * Find user by ID
     */
    public function findById(int $id): ?User;

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User;

    /**
     * Create new user
     */
    public function create(array $userData): User;

    /**
     * Update user
     */
    public function update(User $user, array $userData): bool;

    /**
     * Mark user email as verified
     */
    public function markEmailAsVerified(User $user): bool;

    /**
     * Create reset password token
     */
    public function createResetPasswordToken(string $email): string;

    /**
     * Find a password reset token record by email and token.
     *
     * @param  string  $email  The email address associated with the token.
     * @param  string  $token  The password reset token to find.
     * @return object|null The token record if found, or null if not found.
     */
    public function findResetPasswordToken(string $email, string $token): ?object;

    /**
     * Delete a password reset token by its token string.
     *
     * @return bool True if deletion was successful, false otherwise.
     */
    public function deleteResetPasswordTokenByEmail(string $email): bool;
}
