<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditUserOperation
{
    private const SENSITIVE_KEYS = [
        '_token',
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'secret',
        'authorization',
        'cookie',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $actor = $request->user();

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->record(
                $request,
                $actor ?? $request->user(),
                $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
                $startedAt
            );

            throw $exception;
        }

        $this->record($request, $actor ?? $request->user(), $response->getStatusCode(), $startedAt);

        return $response;
    }

    private function record(Request $request, ?User $user, int $statusCode, int $startedAt): void
    {
        if (! $user || ! $this->shouldRecord($request)) {
            return;
        }

        try {
            $subject = $this->subject($request);

            AuditLog::query()->create([
                'user_id' => $user->getKey(),
                'user_name' => $user->name,
                'user_email' => $user->email,
                'method' => $request->method(),
                'route_name' => $request->route()?->getName(),
                'path' => '/'.$request->path(),
                'subject_type' => $subject['type'],
                'subject_id' => $subject['id'],
                'subject_label' => $subject['label'],
                'request_data' => $this->sanitize($request->all()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => $statusCode,
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function shouldRecord(Request $request): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        // A report download is a deliberate user operation even though it uses GET.
        return $request->route()?->getName() === 'reports.download';
    }

    /**
     * @return array{type: ?string, id: ?string, label: ?string}
     */
    private function subject(Request $request): array
    {
        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            if ($value instanceof Model) {
                return [
                    'type' => $value::class,
                    'id' => (string) $value->getKey(),
                    'label' => $this->modelLabel($value),
                ];
            }

            if (is_scalar($value)) {
                return [
                    'type' => $name,
                    'id' => (string) $value,
                    'label' => (string) $value,
                ];
            }
        }

        return ['type' => null, 'id' => null, 'label' => null];
    }

    private function modelLabel(Model $model): string
    {
        foreach (['name', 'plate_number', 'email', 'sequence_number', 'key', 'operation_uuid'] as $attribute) {
            if (filled($model->getAttribute($attribute))) {
                return (string) $model->getAttribute($attribute);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitive($key)) {
            return '[REDACTED]';
        }

        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getClientOriginalName(),
                'size' => $value->getSize(),
                'mime_type' => $value->getClientMimeType(),
            ];
        }

        if (is_array($value)) {
            return collect($value)
                ->mapWithKeys(fn (mixed $item, string|int $itemKey) => [
                    $itemKey => $this->sanitize($item, (string) $itemKey),
                ])
                ->all();
        }

        return is_scalar($value) || $value === null ? $value : (string) $value;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        return collect(self::SENSITIVE_KEYS)->contains(
            fn (string $sensitive) => $key === $sensitive || str_contains($key, $sensitive)
        );
    }
}
