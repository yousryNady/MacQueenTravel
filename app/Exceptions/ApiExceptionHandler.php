<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler
{
    public static function render(Throwable $exception): array
    {
        if ($exception instanceof ValidationException) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $exception->errors(),
                'code' => 422,
            ];
        }

        if ($exception instanceof ModelNotFoundException) {
            return [
                'success' => false,
                'message' => 'Resource not found',
                'errors' => null,
                'code' => 404,
            ];
        }

        if ($exception instanceof NotFoundHttpException) {
            return [
                'success' => false,
                'message' => 'Endpoint not found',
                'errors' => null,
                'code' => 404,
            ];
        }

        if ($exception instanceof AuthenticationException) {
            return [
                'success' => false,
                'message' => 'Unauthenticated',
                'errors' => null,
                'code' => 401,
            ];
        }

        return [
            'success' => false,
            'message' => $exception->getMessage() ?: 'Server error',
            'errors' => null,
            'code' => 500,
        ];
    }
}
