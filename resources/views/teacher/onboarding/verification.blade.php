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

            <!-- Documents: one upload block per document type. The type is part of
                 the block, so nothing has to be matched up by hand. -->
            <div class="{{ $cardClasses }}">
                <h2 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Your documents') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Everything our team will review. Choose a file in the section it belongs to — each upload is saved as a draft right away.') }}
                </p>

                @if ($errors->has('type') || $errors->has('document'))
                    <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                        <x-input-error :messages="$errors->get('type')" />
                        <x-input-error :messages="$errors->get('document')" />
                    </div>
                @endif

                <div class="mt-4 space-y-3">
                    @foreach ($documentTypes as $value => $label)
                        @php($typeDocuments = $profile->documents->where('type', $value))

                        <div class="rounded-xl border border-slate-200/80 p-3 dark:border-slate-800/80">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="break-words text-sm font-bold text-slate-800 dark:text-slate-100">{{ $label }}</p>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $value === 'id_proof' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                    {{ $value === 'id_proof' ? __('Required') : __('Optional') }}
                                </span>
                            </div>

                            @if ($typeDocuments->isNotEmpty())
                                <ul class="mt-2 divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($typeDocuments as $document)
                                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                            <span class="min-w-0 break-words text-xs text-slate-600 dark:text-slate-300">
                                                {{ $document->original_name }}
                                                <span class="block text-[11px] text-slate-400">{{ $document->created_at->format('d M Y') }}</span>
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
                            @endif

                            @if ($canEditDocuments)
                                <form method="POST" action="{{ route('teacher.verification.documents.store') }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                                    @csrf
                                    <input type="hidden" name="return" value="onboarding">
                                    <input type="hidden" name="type" value="{{ $value }}">

                                    <label class="sr-only" for="document-{{ $value }}">{{ __('Upload a document') }} — {{ $label }}</label>
                                    <input id="document-{{ $value }}" name="document" type="file" accept="application/pdf,image/jpeg,image/png,image/webp"
                                           class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-400" />

                                    <div class="flex justify-end">
                                        <x-secondary-button type="submit">{{ __('Upload document') }}</x-secondary-button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($canEditDocuments)

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
