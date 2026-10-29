@php
    // A server-rendered status strip for teachers who are not live yet. It has
    // to work without JavaScript, so it lives in the layout rather than in the
    // Vue app shell, and it reuses the profile the request already loaded.
    $bannerUser = Auth::user();
    $bannerProfile = $bannerUser?->isTeacher() ? $bannerUser->teacherProfile : null;
    $bannerStage = \App\Support\TeacherApproval::stage($bannerProfile);
    $bannerOnStatusPage = request()->routeIs('teacher.approval.status');
@endphp

@if (! $bannerOnStatusPage && in_array($bannerStage, [\App\Support\TeacherApproval::STAGE_REVIEW, \App\Support\TeacherApproval::STAGE_ACTION], true))
    @php
        $isAction = $bannerStage === \App\Support\TeacherApproval::STAGE_ACTION;
        $expected = \App\Support\TeacherApproval::expectedDecisionAt($bannerProfile);
    @endphp

    <div @class([
        'border-b px-4 py-2.5 text-xs font-semibold',
        'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300' => $isAction,
        'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300' => ! $isAction,
    ]) role="status">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2">
            <p class="min-w-0">
                @if ($isAction)
                    {{ __('Action needed — your application could not be approved yet. You are not visible to students.') }}
                @else
                    {{ __('In review — your profile is not visible to students yet and cannot be booked.') }}
                    @if ($expected)
                        {{ __('Expected decision by :date.', ['date' => display_datetime($expected)]) }}
                    @endif
                @endif
            </p>

            <a href="{{ route('teacher.approval.status') }}"
               class="shrink-0 underline underline-offset-2 hover:no-underline">
                {{ $isAction ? __('Fix my application') : __('View approval status') }}
            </a>
        </div>
    </div>
@endif
