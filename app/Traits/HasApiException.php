<?php

namespace App\Traits;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

trait HasApiException
{
    use ApiResponse;

    /**
     * Handle exceptions in a consistent way
     */
    public function handleException(Exception|Throwable $exception): JsonResponse
    {
        switch (true) {
            case $exception instanceof ValidationException:
                return $this->error($exception->getMessage(), 422, $exception->errors());

            case $exception instanceof ModelNotFoundException:
                return $this->error($exception->getMessage(), 404);

            case $exception instanceof QueryException:
                return $this->error('A database error occurred. Please try again later.', 500);

            default:
                $context = [
                    'exception' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ];
                Log::error('Unhandled exception in RegisterController', $context);

                $message = $exception->getMessage() ?: 'Something went wrong. Please try again later.';

                return $this->error($message, 500);
        }
    }
}
