<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler extends Exception
{
    use ApiResponse;

    /**
     * Handle the exception and return the appropriate JSON response.
     */
    public function handle(Throwable $e, Request $request): ?JsonResponse
    {
        if ($request->is('api/*')) {
            // Validation Exception
            if ($e instanceof ValidationException) {
                return $this->error($e->getMessage(), 422, $e->errors());
            }

            // Model Not Found Exception
            if ($e instanceof ModelNotFoundException) {
                return $this->error('Record not found', 404);
            }

            // Authentication Exception
            if ($e instanceof AuthenticationException) {
                return $this->error($e->getMessage(), 401);
            }

            // Authorization Exception
            if ($e instanceof AuthorizationException) {
                return $this->error($e->getMessage(), 403);
            }

            // Page Not Found Exception
            if ($e instanceof NotFoundHttpException) {
                return $this->error('Page not found', 404);
            }

            // Bad Request Exception
            if ($e instanceof BadRequestHttpException) {
                return $this->error('Bad request', 400);
            }
            // Dynamically determine the status code if available
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return $this->error($e->getMessage(), $statusCode);
        }

        return null;  // If it's not an API request, return null
    }
}
