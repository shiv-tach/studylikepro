@php
    // The no-JavaScript twin of the Vue composer. It keeps the same field names,
    // the same routes and the same validation, but the grade/subject/lesson
    // cascade is not filtered — every option is shown at once.
    $isEdit = $offering !== null;
    $action = $isEdit ? route('teacher.services.update', $offering) : route('teacher.services.store');
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

    // Keep the edit form's current scope visible even if it is no longer part
    // of the teacher's subject setup (e.g. the teacher pruned the subject).
    if ($isEdit) {
        if ($offering->grade_id && ! collect($gradeOptions)->contains('id', (int) $offering->grade_id)) {
            $gradeOptions[] = [
                'id' => (int) $offering->grade_id,
                'label' => $offering->grade?->label ?? 'Grade #'.$offering->grade_id,
            ];
        }

        if ($offering->subject_id && ! collect($subjectOptions)->contains('id', (int) $offering->subject_id)) {
            $subjectOptions[] = [
                'id' => (int) $offering->subject_id,
                'name' => $offering->subject?->name ?? 'Subject #'.$offering->subject_id,
                'level_name' => null,
                'grades' => [],
            ];
        }

        if ($offering->lesson_id && ! collect($lessonOptions)->contains('id', (int) $offering->lesson_id)) {
            $lessonOptions[] = [
                'id' => (int) $offering->lesson_id,
                'subject_id' => (int) ($offering->lesson?->subject_id ?? 0),
                'grade_id' => (int) ($offering->lesson?->grade_id ?? 0),
                'name' => $offering->lesson?->name ?? 'Lesson #'.$offering->lesson_id,
            ];
        }
    }
@endphp

<form method="POST" action="{{ $action }}" class="mt-5 space-y-4 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="editing_offering" value="{{ $offering->id }}">
    @endif

    <input type="hidden" name="service_type" value="{{ $type->value }}">

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <x-input-label :value="__('Grade')" />
            <select name="grade_id" class="mt-1 block w-full {{ $fieldClasses }}">
                <option value="">{{ $type->requiresLesson() ? __('Pick a grade') : __('All grades') }}</option>
                @foreach ($gradeOptions as $grade)
                    <option value="{{ $grade['id'] }}" @selected((int) old('grade_id', $offering?->grade_id ?? 0) === $grade['id'])>{{ $grade['label'] }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('grade_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label :value="__('Subject')" />
            <select name="subject_id" class="mt-1 block w-full {{ $fieldClasses }}">
                <option value="">{{ $type->allowsLessonOrSubject() ? __('No subject — lesson only') : __('Pick a subject') }}</option>
                @foreach ($subjectOptions as $subject)
                    <option value="{{ $subject['id'] }}" @selected((int) old('subject_id', $offering?->subject_id ?? 0) === $subject['id'])>
                        {{ $subject['name'] }}{{ $subject['level_name'] ? ' — '.$subject['level_name'] : '' }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('subject_id')" class="mt-2" />
        </div>

        @if ($type->requiresLesson() || $type->allowsLessonOrSubject())
            <div>
                <x-input-label :value="__('Lesson')" />
                <select name="lesson_id" class="mt-1 block w-full {{ $fieldClasses }}">
                    <option value="">{{ $type->allowsLessonOrSubject() ? __('Whole subject') : __('Pick a lesson') }}</option>
                    @foreach ($lessonOptions as $lesson)
                        <option value="{{ $lesson['id'] }}" @selected((int) old('lesson_id', $offering?->lesson_id ?? 0) === $lesson['id'])>
                            {{ $lesson['name'] }}
                        </option>
                    @endforeach
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
            <input name="price" type="number" min="1" max="1000000"
                   value="{{ old('price', $offering ? (int) round($offering->price_minor / 100) : '') }}"
                   class="mt-1 block w-full {{ $fieldClasses }}" />
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
                <x-input-label :value="__('Total time (minutes)')" />
                <input name="allocation_minutes" type="number" min="30" max="6000" step="30"
                       value="{{ old('allocation_minutes', $offering?->allocation_minutes ?? 60) }}"
                       class="mt-1 block w-full {{ $fieldClasses }}" />
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

