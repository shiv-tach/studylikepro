<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Enums\ResponseStatus;
use App\Models\TutoringRequest;
use Illuminate\Console\Command;

class ExpireTutoringRequests extends Command
{
    protected $signature = 'studylikepro:expire-requests';

    protected $description = 'Expire open tutoring requests past their deadline';

    public function handle(): int
    {
        $expired = TutoringRequest::query()
            ->where('status', RequestStatus::Open->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => RequestStatus::Expired->value,
                'updated_at' => now(),
            ]);

        TutoringRequest::query()
            ->where('status', RequestStatus::Expired->value)
            ->whereHas('responses', fn ($query) => $query->where('status', ResponseStatus::Pending->value))
            ->get()
            ->each(fn (TutoringRequest $request) => $request->responses()
                ->where('status', ResponseStatus::Pending->value)
                ->update(['status' => ResponseStatus::Expired->value, 'updated_at' => now()]));

        $this->info("Expired {$expired} tutoring request(s).");

        return self::SUCCESS;
    }
}
