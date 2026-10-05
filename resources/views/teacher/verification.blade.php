@php
    $status = $profile->verification_status;
    $hasIdProof = $profile->documents->contains('type', 'id_proof');
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $selectClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Verification') }}</h2>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $status->badgeClasses() }}">
                {{ $status->label() }}
            </span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status') === 'document-uploaded')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Document uploaded.
            </div>
        @elseif (session('status') === 'document-removed')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800/80 dark:bg-slate-900/60 dark:text-slate-300">
                Document removed.
            </div>
        @elseif (session('status') === 'submitted-for-review')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Application submitted — our team will review your documents shortly.
            </div>
        @elseif (session('status') === 'documents-locked')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                Your documents are locked while the application is under review.
            </div>
        @endif

        <x-input-error :messages="$errors->get('document')" class="mt-2" />

        <!-- Status overview -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            @if ($status === \App\Enums\VerificationStatus::Pending)
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Application under review</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Thanks! We received your application {{ $profile->submitted_at?->diffForHumans() }}. Our team reviews documents within 1–2 business days.
                    You will be notified when your profile is approved and visible to students.
                </p>
            @elseif ($status === \App\Enums\VerificationStatus::Approved)
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">You are verified 🎉</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Your profile was approved {{ $profile->verified_at?->diffForHumans() }}. Verified teachers appear in student search and can receive tutoring requests.
                </p>
            @elseif ($status === \App\Enums\VerificationStatus::Rejected)
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Action needed</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Our team could not approve the application yet. Please address the notes below, update your documents, and resubmit.
                </p>
                <div class="mt-4 rounded-xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                    {{ $profile->verification_notes }}
                </div>
            @else
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Get verified</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Upload your documents below, then submit your application. Verification keeps quality high and builds student trust.
                </p>
            @endif
        </div>

        <!-- Documents -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Your documents</h3>

            @if ($profile->documents->isEmpty())
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No documents uploaded yet.</p>
            @else
                <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($profile->documents as $document)
                        <li class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $document->typeLabel() }}</p>
                                    <p class="truncate text-xs text-slate-400">{{ $document->original_name }} · {{ $document->created_at->format('d M Y') }}</p>
                                </div>
                            </div>

                            @if ($canEditDocuments)
                                <form method="POST" action="{{ route('teacher.verification.documents.destroy', $document) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30 dark:hover:text-rose-400">
                                        <span class="sr-only">Remove {{ $document->typeLabel() }}</span>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($canEditDocuments)
            <!-- Upload -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Upload a document</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">PDF, JPG, PNG or WebP — up to 5 MB. A government ID is required to submit.</p>

                <form method="POST" action="{{ route('teacher.verification.documents.store') }}" enctype="multipart/form-data" class="mt-5 space-y-5">
                    @csrf

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="type" :value="__('Document type')" />
                            <select id="type" name="type" class="{{ $selectClasses }}">
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
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>{{ __('Upload document') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <!-- Submit for review -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Submit for review</h3>

                <ul class="mt-4 space-y-3 text-sm">
                    <li class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                        <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Teaching profile completed
                    </li>
                    <li class="flex items-center gap-2 {{ $hasIdProof ? 'text-slate-600 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500' }}">
                        @if ($hasIdProof)
                            <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @else
                            <svg class="h-5 w-5 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @endif
                        Government ID uploaded
                    </li>
                </ul>

                <form method="POST" action="{{ route('teacher.verification.submit') }}" class="mt-5 space-y-4">
                    @csrf

                    <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-800/40 dark:text-slate-300">
                        <input type="checkbox" name="agree" value="1" required @checked(old('agree'))
                               class="mt-0.5 rounded border-slate-300 text-primary shadow-sm focus:ring-primary dark:border-slate-600 dark:bg-slate-900">
                        <span>
                            {{ __('I have read and accept the') }}
                            <a href="{{ route('legal.terms') }}" target="_blank" class="font-semibold text-primary hover:underline">{{ __('teacher terms') }}</a>
                            {{ __('and the') }}
                            <a href="{{ route('legal.privacy') }}" target="_blank" class="font-semibold text-primary hover:underline">{{ __('privacy policy') }}</a>,
                            {{ __('including the commission and cancellation rules.') }}
                        </span>
                    </label>
                    @error('agree')
                        <p class="text-xs text-rose-500">{{ $message }}</p>
                    @enderror

                    <div class="flex items-center justify-end">
                        <x-primary-button>{{ __('Submit for review') }}</x-primary-button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
