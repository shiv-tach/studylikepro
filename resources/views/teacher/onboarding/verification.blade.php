@php
    // Wizard step 5 ("Verification"). The island is built by
    // App\Support\VerificationStepConfig; `mode` makes every form post with
    // `return=onboarding` so the teacher stays inside the wizard.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $cardClasses = 'rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-slate-800/80 dark:bg-slate-900 sm:p-6';
    $status = $profile->verification_status;
@endphp
<x-onboarding-layout>
    <x-slot name="progress">
        <x-onboarding-progress :current="5" />
    </x-slot>

    <script type="application/json" id="teacher-verification-config">@json($config, $jsonFlags)</script>

    <div id="teacher-verification-app"></div>

    <noscript>
        <div class="space-y-5">
            @foreach ($config['notices'] as $notice)
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 text-sm text-slate-700 dark:border-slate-800/80 dark:bg-slate-900 dark:text-slate-200">
                    <p class="font-semibold">{{ $notice['title'] }}</p>
                    <p class="mt-0.5">{{ $notice['message'] }}</p>
                </div>
            @endforeach

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                    <p class="font-semibold">{{ __('Please fix the following:') }}</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($profile->documents->isNotEmpty())
                <div class="{{ $cardClasses }}">
                    <h2 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Your documents') }}</h2>
                    <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($profile->documents as $document)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-slate-800 dark:text-slate-100">{{ $document->typeLabel() }}</span>
                                    <span class="block truncate text-xs text-slate-400">{{ $document->original_name }}</span>
                                </span>
                                @if ($canEditDocuments)
                                    <form method="POST" action="{{ route('teacher.verification.documents.destroy', $document) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="return" value="onboarding">
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:underline">{{ __('Remove') }}</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($canEditDocuments)
                <div class="{{ $cardClasses }}">
                    <h2 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Upload a document') }}</h2>

                    <form method="POST" action="{{ route('teacher.verification.documents.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="return" value="onboarding">

                        <div>
                            <x-input-label for="type" :value="__('Document type')" />
                            <select id="type" name="type" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 sm:text-sm">
                                @foreach ($documentTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="document" :value="__('File')" />
                            <input id="document" name="document" type="file" accept="application/pdf,image/jpeg,image/png,image/webp"
                                   class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-400" />
                            <x-input-error :messages="$errors->get('document')" class="mt-2" />
                        </div>

                        <div class="flex justify-end">
                            <x-secondary-button type="submit">{{ __('Upload document') }}</x-secondary-button>
                        </div>
                    </form>
                </div>

                <div class="{{ $cardClasses }}">
                    <h2 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('What will you teach?') }}</h2>

                    <form method="POST" action="{{ route('teacher.verification.subjects.store') }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="return" value="onboarding">

                        @foreach ($subjectGroups as $group)
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-400 first:mt-0">{{ $group['name'] }}</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                @foreach ($group['subjects'] as $subject)
                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200/80 px-3.5 py-2.5 text-sm dark:border-slate-800">
                                        <input type="checkbox" name="subjects[]" value="{{ $subject['id'] }}"
                                               @checked(in_array((int) $subject['id'], $selectedSubjectIds, true))
                                               class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-900" />
                                        <span aria-hidden="true">{{ $subject['icon'] }}</span>
                                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $subject['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                        <x-input-error :messages="$errors->get('subjects')" class="mt-2" />

                        <div class="mt-4 flex justify-end">
                            <x-secondary-button type="submit">{{ __('Save subjects') }}</x-secondary-button>
                        </div>
                    </form>
                </div>

                <div class="{{ $cardClasses }}">
                    <h2 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Submit for review') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Nothing reaches our team until you submit. After that your documents are locked while we review — usually 1–2 business days.') }}
                    </p>

                    <form method="POST" action="{{ route('teacher.verification.submit') }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="return" value="onboarding">

                        <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-800/40 dark:text-slate-300">
                            <input type="checkbox" name="agree" value="1" required @checked($config['values']['agree'])
                                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-900">
                            <span>{{ __('I have read and accept the teacher terms and the privacy policy, including the commission and cancellation rules.') }}</span>
                        </label>
                        <x-input-error :messages="$errors->get('agree')" />

                        <div class="flex justify-end">
                            <x-primary-button class="w-full sm:w-auto">{{ __('Submit application') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </noscript>

    @vite('resources/js/teacher-verification/main.js')
</x-onboarding-layout>
