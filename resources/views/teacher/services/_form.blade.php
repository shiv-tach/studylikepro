@php
    $isEdit = $offering !== null;
    $action = $isEdit ? route('teacher.services.update', $offering) : route('teacher.services.store');
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

    $lessonOptions = $lessons
        ->map(fn ($lesson) => [
            'id' => (int) $lesson->id,
            'subject_id' => (int) $lesson->subject_id,
            'name' => $lesson->grade ? $lesson->name.' ('.$lesson->grade->label.')' : $lesson->name,
        ])
        ->values()
        ->all();

    $allocation = (int) old('allocation_minutes', $offering?->allocation_minutes ?? 60);
    $hours = (int) floor($allocation / 60);
    $minutes = $allocation % 60;
@endphp

<form method="POST" action="{{ $action }}" class="mt-5 space-y-4 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800"
      x-data="{ subjectId: '{{ old('subject_id', $offering?->subject_id ?? '') }}', lessonId: {{ (int) old('lesson_id', $offering?->lesson_id ?? 0) }}, hours: {{ $hours }}, minutes: {{ $minutes }}, lessons: @json($lessonOptions) }">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <input type="hidden" name="service_type" value="{{ $type->value }}">

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label :value="__('Subject')" />
            <select name="subject_id" x-model="subjectId" class="mt-1 block w-full {{ $fieldClasses }}">
                @if ($type->allowsLessonOrSubject())
                    <option value="">{{ __('Whole subject (optional)') }}</option>
                @endif
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(old('subject_id', $offering?->subject_id) == $subject->id)>
                        {{ $subject->name }}{{ $subject->educationLevel ? ' — '.$subject->educationLevel->name : '' }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
        </div>

        @if ($type->requiresLesson() || $type->allowsLessonOrSubject())
            <div>
                <x-input-label :value="__('Lesson')" />
                <select name="lesson_id" class="mt-1 block w-full {{ $fieldClasses }}">
                    @if ($type->allowsLessonOrSubject())
                        <option value="">{{ __('Whole subject') }}</option>
                    @endif
                    <template x-for="lesson in lessons.filter((l) => ! subjectId || l.subject_id == subjectId)" :key="lesson.id">
                        <option :value="lesson.id" :selected="lesson.id == lessonId" x-text="lesson.name"></option>
                    </template>
                </select>
                <x-input-error :messages="$errors->get('lesson_id')" class="mt-2" />
            </div>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label :value="__('Title')" />
            <input name="title" type="text" maxlength="160" value="{{ old('title', $offering?->title) }}"
                   placeholder="{{ __('e.g. O/L Maths crash plan') }}"
                   class="mt-1 block w-full {{ $fieldClasses }}" />
            <x-input-error :messages="$errors->get('title')" class="mt-2" />
        </div>

        <div>
            <x-input-label :value="__('Price (rupees)')" />
            <div class="relative mt-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">{{ platform_settings()->currencySymbol() }}</span>
                <input name="price" type="number" min="1" max="1000000"
                       value="{{ old('price', $offering ? (int) round($offering->price_minor / 100) : '') }}"
                       class="block w-full rounded-xl border-slate-300 bg-white pl-8 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
            </div>
            <p class="mt-1 text-xs text-slate-400">{{ $type === \App\Enums\ServiceType::Lesson ? __('Flat price for the whole plan.') : ($type === \App\Enums\ServiceType::PaperMarking ? __('Flat price per paper.') : __('Flat price per session.')) }}</p>
            <x-input-error :messages="$errors->get('price')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label :value="__('Description (optional)')" />
        <textarea name="description" rows="2" maxlength="2000"
                  class="mt-1 block w-full {{ $fieldClasses }}">{{ old('description', $offering?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    @if ($type === \App\Enums\ServiceType::Lesson)
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label :value="__('Total time')" />
                <div class="mt-1 flex items-center gap-2">
                    <select x-model.number="hours" class="{{ $fieldClasses }}">
                        @for ($hour = 1; $hour <= 100; $hour++)
                            <option value="{{ $hour }}">{{ $hour }}h</option>
                        @endfor
                    </select>
                    <select x-model.number="minutes" class="{{ $fieldClasses }}">
                        <option value="0">0m</option>
                        <option value="30">30m</option>
                    </select>
                </div>
                <input type="hidden" name="allocation_minutes" :value="hours * 60 + minutes">
                <p class="mt-1 text-xs text-slate-400">{{ __('Students book this total time in sessions, at least 30 minutes.') }}</p>
                <x-input-error :messages="$errors->get('allocation_minutes')" class="mt-2" />
            </div>

            <div>
                <x-input-label :value="__('Session length')" />
                <select name="default_session_minutes" class="mt-1 block w-full {{ $fieldClasses }}">
                    <option value="">{{ __('No preference') }}</option>
                    @foreach ($durations as $duration)
                        <option value="{{ $duration }}" @selected(old('default_session_minutes', $offering?->default_session_minutes) == $duration)>{{ $duration }} minutes</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('default_session_minutes')" class="mt-2" />
            </div>

            <div>
                <x-input-label :value="__('Session cap (optional)')" />
                <input name="max_sessions" type="number" min="1" max="20"
                       value="{{ old('max_sessions', $offering?->max_sessions) }}"
                       placeholder="{{ __('e.g. 8') }}"
                       class="mt-1 block w-full {{ $fieldClasses }}" />
                <x-input-error :messages="$errors->get('max_sessions')" class="mt-2" />
            </div>
        </div>
    @endif

    @if ($type === \App\Enums\ServiceType::PaperMarking)
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label :value="__('Page limit (optional)')" />
                <input name="max_pages" type="number" min="1" max="500"
                       value="{{ old('max_pages', $offering?->max_pages) }}"
                       placeholder="{{ __('Any length') }}"
                       class="mt-1 block w-full {{ $fieldClasses }}" />
                <x-input-error :messages="$errors->get('max_pages')" class="mt-2" />
            </div>

            <div>
                <x-input-label :value="__('Turnaround (hours)')" />
                <input name="turnaround_hours" type="number" min="1" max="720"
                       value="{{ old('turnaround_hours', $offering?->turnaround_hours) }}"
                       placeholder="{{ __('e.g. 72') }}"
                       class="mt-1 block w-full {{ $fieldClasses }}" />
                <x-input-error :messages="$errors->get('turnaround_hours')" class="mt-2" />
            </div>
        </div>
    @endif

    @if ($type === \App\Enums\ServiceType::PaperDiscussion || $type === \App\Enums\ServiceType::Qna)
        <div>
            <x-input-label :value="__('Session length')" />
            <select name="default_session_minutes" class="mt-1 block w-full sm:w-56 {{ $fieldClasses }}">
                <option value="">{{ __('No preference') }}</option>
                @foreach ($durations as $duration)
                    <option value="{{ $duration }}" @selected(old('default_session_minutes', $offering?->default_session_minutes) == $duration)>{{ $duration }} minutes</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('default_session_minutes')" class="mt-2" />
        </div>
    @endif

    <div class="flex justify-end">
        <x-primary-button>{{ $isEdit ? __('Save changes') : __('Add service') }}</x-primary-button>
    </div>
</form>
