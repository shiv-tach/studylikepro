<?php

namespace App\Http\Requests;

use App\Models\TutoringRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTutoringRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxAttachments = (int) config('studylikepro.requests.max_attachments');

        return [
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'grade_id' => ['nullable', 'integer', Rule::exists('grades', 'id')->where('is_active', true)],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'attachments' => ['nullable', 'array', 'max:'.$maxAttachments],
            'attachments.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
            'windows' => ['required', 'array', 'min:1', 'max:5'],
            'windows.*.date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'windows.*.from' => ['required', 'date_format:H:i'],
            'windows.*.to' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $timezone = $this->user()->studentProfile?->timezone
                ?? config('studylikepro.default_display_timezone');

            foreach ($this->input('windows', []) as $index => $window) {
                $start = CarbonImmutable::parse($window['date'].' '.$window['from'], $timezone);
                $end = CarbonImmutable::parse($window['date'].' '.$window['to'], $timezone);

                if ($end->lessThanOrEqualTo($start)) {
                    $validator->errors()->add("windows.{$index}.to", __('The end time must be after the start time.'));
                } elseif ($end->lessThanOrEqualTo(CarbonImmutable::now())) {
                    $validator->errors()->add("windows.{$index}.date", __('Preferred windows must be in the future.'));
                }
            }

            $todayCount = TutoringRequest::query()
                ->where('student_id', $this->user()->id)
                ->whereDate('created_at', today())
                ->count();

            if ($todayCount >= (int) config('studylikepro.requests.daily_submission_limit')) {
                $validator->errors()->add('description', __('You have reached today\'s request limit. Try again tomorrow or cancel an open request.'));
            }
        });
    }

    /**
     * Preferred windows converted to UTC ISO-8601 ranges.
     *
     * @return list<array{starts_at: string, ends_at: string}>
     */
    public function windowPayload(): array
    {
        $timezone = $this->user()->studentProfile?->timezone
            ?? config('studylikepro.default_display_timezone');

        return collect($this->validated('windows'))
            ->map(fn (array $window) => [
                'starts_at' => CarbonImmutable::parse($window['date'].' '.$window['from'], $timezone)->utc()->toIso8601String(),
                'ends_at' => CarbonImmutable::parse($window['date'].' '.$window['to'], $timezone)->utc()->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
