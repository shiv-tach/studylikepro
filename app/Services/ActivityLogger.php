<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The admin audit trail. The middleware records every state-changing console
 * request; controllers can replace the generic description with a sentence that
 * means more to a human reviewer.
 */
class ActivityLogger
{
    /** Keys that must never be written to the log. */
    private const REDACTED = ['_token', 'password', 'password_confirmation', 'current_password', 'payload', 'signature'];

    private ?string $pendingDescription = null;

    /**
     * Use this description for the request currently being handled.
     */
    public function describe(string $description): void
    {
        $this->pendingDescription = $description;
    }

    public function takeDescription(): ?string
    {
        $description = $this->pendingDescription;
        $this->pendingDescription = null;

        return $description;
    }

    public function log(
        ?User $actor,
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
        ?string $ipAddress = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description ?? str($action)->after('admin.')->replace('.', ' ')->headline()->toString(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }

    /**
     * The request payload, minus anything sensitive or bulky.
     *
     * @return array<string, mixed>
     */
    public function sanitisedInput(Request $request): array
    {
        return collect($request->except(self::REDACTED))
            ->map(fn ($value) => is_scalar($value) || $value === null
                ? $value
                : (is_array($value) ? '[array]' : '[value]'))
            ->all();
    }
}
