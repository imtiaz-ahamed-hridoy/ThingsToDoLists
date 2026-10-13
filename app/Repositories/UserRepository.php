<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserRepository implements UserRepositoryInterface
{
    /**
     * The User model instance.
     */
    protected User $model;

    /**
     * UserRepository constructor.
     */
    public function __construct(User $model)
    {
        $this->model = $model;
    }

    // ==================== RepositoryInterface Standard Methods ====================

    public function all(array $columns = ['*']): Collection
    {
        return $this->model->all($columns);
    }

    public function find(int $id, array $columns = ['*']): ?Model
    {
        return $this->model->find($id, $columns);
    }

    public function findOrFail(int $id, array $columns = ['*']): Model
    {
        return $this->model->findOrFail($id, $columns);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->paginate($perPage, $columns);
    }

    public function where(array $conditions, array $columns = ['*']): Collection
    {
        $query = $this->model->newQuery();
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }
        return $query->get($columns);
    }

    public function whereFirst(array $conditions, array $columns = ['*']): ?Model
    {
        $query = $this->model->newQuery();
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }
        return $query->first($columns);
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function query()
    {
        return $this->model->newQuery();
    }

    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    public function deleteById(int $id): bool
    {
        return $this->findOrFail($id)->delete();
    }

    // ==================== Specific Methods ====================

    /**
     * Find a user by its primary key (ID).
     *
     * @param  int  $id  The ID of the user to find.
     * @return User|null The User model if found, otherwise null.
     */
    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    /**
     * Find a user by their email address.
     *
     * @param  string  $email  The email address to search for.
     * @return User|null The User model if found, otherwise null.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Create a new user with the provided data.
     * Password will be hashed automatically if provided.
     *
     * @param  array  $userData  The data to create the user with.
     * @return User The newly created User model.
     */
    public function create(array $userData): User
    {
        if (isset($userData['password'])) {
            $userData['password'] = bcrypt($userData['password']);
        }

        return $this->model->create($userData);
    }

    /**
     * Update an existing user with new data.
     * Password will be hashed automatically if provided.
     *
     * @param  Model  $model  The User model instance to update.
     * @param  array  $data  The data to update the user with.
     * @return Model The updated User model (fresh).
     */
    public function update(Model $model, array $data): Model
    {
        if ($model instanceof User && isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $model->update($data);
        return $model->fresh();
    }

    /**
     * Mark the user's email as verified by setting the email_verified_at timestamp.
     *
     * @param  User  $user  The User model to update.
     * @return bool True if the update was successful, false otherwise.
     */
    public function markEmailAsVerified(User $user): bool
    {
        return $user->forceFill(['email_verified_at' => now()])->save();
    }

    /**
     * Create a password reset token for the given email.
     *
     * @param  string  $email  The email address for which to create the reset token.
     * @return string token.
     */
    public function createResetPasswordToken(string $email): string
    {
        $token = Str::random(60);
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => now(),
        ]);

        return $token;
    }

    /**
     * Find a password reset token record by email and token.
     *
     * @param  string  $email  The email address associated with the token.
     * @param  string  $token  The password reset token to find.
     * @return object|null The token record if found, otherwise null.
     */
    public function findResetPasswordToken(string $email, string $token): ?object
    {
        return DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $token)
            ->first();
    }

    /**
     * Delete a password reset token record by its token value.
     *
     * @param  string  $email  The password reset token to delete.
     * @return bool True if at least one record was deleted, false otherwise.
     */
    public function deleteResetPasswordTokenByEmail(string $email): bool
    {
        return DB::table('password_reset_tokens')->where('email', $email)->delete() > 0;
    }
}
