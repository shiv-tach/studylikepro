@php
    // /teacher/verification is onboarding step 2 and is rendered by
    // resources/js/teacher-verification. Blade keeps the application status, the
    // documents on file, the document types, the limits, the validation errors
    // and the route map, then hands them over as a JSON island. The noscript
    // fallback below is the same flow without JavaScript: the upload and submit
    // forms post to the same routes.
    $status = $profile->verification_status;
    $hasIdProof = $profile->documents->contains('type', 'id_proof');
    $themePreset = $userTheme['preset'] ?? 'classic';

    $profileComplete = (bool) $profile->isComplete();
    $verificationSubmitted = (bool) $profile->hasSubmittedVerification();
    $hasAvailability = \App\Support\TeacherOnboarding::hasAvailability($profile);
    $selectedSubjectIds = array_map('intval', $selectedSubjectIds);
    $hasSubjects = $selectedSubjectIds !== [];

    $steps = \App\Support\TeacherOnboarding::steps($profile);

    $typeHints = [
        'id_proof' => __('A passport, national ID card or driving licence — this is the one we need before you can submit.'),
        'degree' => __('Your degree or diploma certificate.'),
        'certificate' => __('A teaching qualification or training certificate.'),
        'other' => __('Anything else that supports your application.'),
    ];

    $documentTypesPayload = collect($documentTypes)
        ->map(fn (string $label, string $value) => [
            'value' => $value,
            'label' => $label,
            'hint' => $typeHints[$value] ?? '',
            'required' => $value === 'id_proof',
        ])
        ->values();

    $uploadedTypes = $profile->documents->pluck('type')->all();
    $selectedType = collect(array_keys($documentTypes))
        ->first(fn (string $type) => ! in_array($type, $uploadedTypes, true)) ?? 'id_proof';

    // Every flash message becomes a toast, so it is seen wherever the teacher
    // is scrolled to after the redirect.
    $notices = [];

    if (session('status') === 'profile-completed') {
        $notices[] = [
            'tone' => 'emerald',
            'title' => __('Step 1 complete'),
            'message' => __('Your teaching profile is saved. Set your weekly availability, then upload your documents.'),
        ];
    } elseif (session('status') === 'availability-saved') {
        $notices[] = [
            'tone' => 'emerald',
            'title' => __('Weekly availability saved'),
            'message' => __('Your week is set. Upload your documents below, then submit them for review.'),
        ];
    } elseif (session('status') === 'complete-your-availability') {
        $notices[] = [
            'tone' => 'amber',
            'title' => __('One step left'),
            'message' => __('Set your weekly availability before submitting your documents.'),
        ];
    } elseif (session('status') === 'complete-your-verification') {
        $notices[] = [
            'tone' => 'amber',
            'title' => __('One step left'),
            'message' => __('Finish your verification to unlock your dashboard.'),
        ];
    } elseif (session('status') === 'document-uploaded') {
        $notices[] = [
            'tone' => 'emerald',
            'title' => __('Document uploaded'),
            'message' => $hasIdProof
                ? __('That was the document we needed — submit your application below.')
                : __('Your government ID is still needed before you can submit.'),
        ];
    } elseif (session('status') === 'document-removed') {
        $notices[] = [
            'tone' => 'slate',
            'title' => __('Document removed'),
            'message' => __('You can upload it again before you submit.'),
        ];
    } elseif (session('status') === 'subjects-saved') {
        $notices[] = [
            'tone' => 'emerald',
            'title' => __('Subjects saved'),
            'message' => __('Our team approves your subjects together with your application.'),
        ];
    } elseif (session('status') === 'submitted-for-review') {
        $notices[] = [
            'tone' => 'emerald',
            'title' => __('Application submitted'),
            'message' => __('Our team will review your documents — usually within 1–2 business days.'),
        ];
    } elseif (session('status') === 'documents-locked') {
        $notices[] = [
            'tone' => 'amber',
            'title' => __('Documents locked'),
            'message' => __('Your documents are locked while the application is under review.'),
        ];
    } elseif (session('status') === 'verification-locked') {
        $notices[] = [
            'tone' => 'amber',
            'title' => __('Already submitted'),
            'message' => __('Your application is already with our team. You can upload new documents only if we ask for changes.'),
        ];
    }

    // Acceptance is stored on the profile when the application is submitted, so
    // the checklist keeps showing it as done afterwards. A failed submit keeps
    // whatever the teacher actually ticked, error included.
    $agreementAccepted = session()->hasOldInput()
        ? (bool) old('agree')
        : $profile->hasAcceptedAgreement();

    $payload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'status' => [
            'current' => $status->value,
            'label' => $status->label(),
        ],
        'profile' => [
            'isComplete' => $profileComplete,
            'hasSubmittedVerification' => $verificationSubmitted,
            'submittedAt' => display_datetime($profile->submitted_at),
            'verifiedAt' => display_datetime($profile->verified_at),
            'notes' => $profile->verification_notes,
        ],
        'canEditDocuments' => $canEditDocuments,
        'documentTypes' => $documentTypesPayload,
        'documents' => $profile->documents->map(fn ($document) => [
            'id' => $document->id,
            'type' => $document->type,
            'typeLabel' => $document->typeLabel(),
            'originalName' => $document->original_name,
            'uploadedAt' => $document->created_at?->format('d M Y'),
            'isRequired' => $document->type === 'id_proof',
            'destroyUrl' => route('teacher.verification.documents.destroy', $document),
        ])->values(),
        'selectedType' => $selectedType,
        'subjectGroups' => $subjectGroups,
        'selectedSubjectIds' => $selectedSubjectIds,
        'highlightDocumentId' => $highlightDocumentId !== null ? (int) $highlightDocumentId : null,
        'limits' => [
            'accept' => 'application/pdf,image/jpeg,image/png,image/webp',
            'maxBytes' => 5 * 1024 * 1024,
            'maxSize' => '5 MB',
            'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
        ],
        'values' => [
            'agree' => $agreementAccepted,
        ],
        // Laravel owns the wording of every message; the app only decides where
        // it is shown, so the two never drift apart.
        'errors' => [
            'type' => $errors->get('type'),
            'document' => $errors->get('document'),
            'agree' => $errors->get('agree'),
            'subjects' => $errors->get('subjects'),
        ],
        'notices' => $notices,
        'steps' => $steps,
        'currentStep' => 3,
        'urls' => [
            'upload' => route('teacher.verification.documents.store'),
            'submit' => route('teacher.verification.submit'),
            'subjects' => route('teacher.verification.subjects.store'),
            'terms' => route('legal.terms'),
            'privacy' => route('legal.privacy'),
            'dashboard' => route('teacher.dashboard'),
            'profile' => route('teacher.profile'),
            'availability' => route('teacher.availability.setup'),
        ],
        'copy' => [
            'toastLabel' => __('Notifications'),
            'toastDismiss' => __('Dismiss notification'),
            'errorTitle' => __('Please check the following:'),
            'stepsTitle' => __('Teacher setup'),
            'stepsCompleted' => __(':completed of :total steps completed'),
            'stepLabel' => __('Step :number'),
            'stepStatusDone' => __('Completed'),
            'stepStatusCurrent' => __('In progress'),
            'stepStatusUpNext' => __('Up next'),
            'stepStatusLocked' => __('Locked'),
            'statusTitles' => [
                'draft' => __('Get verified'),
                'pending' => __('Application under review'),
                'approved' => __('You are verified'),
                'rejected' => __('We need a bit more from you'),
            ],
            'statusBodies' => [
                'draft' => __('Upload your documents below, then submit your application. Verification keeps quality high and builds student trust.'),
                'pending' => __('Thanks! We received your application on :submitted. Our team reviews documents within 1–2 business days, and you will be notified when your profile is approved.'),
                'approved' => __('Your profile was approved on :verified. Verified teachers appear in student search and can be booked for live lessons.'),
                'rejected' => __('Our team could not approve the application yet. Please address the notes below, update your documents, and resubmit.'),
            ],
            'reviewerNotes' => __('What our team said'),
            'checklistTitle' => __('What we need'),
            'checklistHint' => __('Everything submission waits for, updated as you go.'),
            'checklistProgress' => __('Progress'),
            'checklistDone' => __('Done'),
            'checklistTodo' => __('To do'),
            'checklistTip' => __('Upload one clear file per document. You can add more than one file of the same type.'),
            'checklistLocked' => __('Your documents are locked while the application is under review.'),
            'reqProfile' => __('Teaching profile'),
            'reqProfileHint' => __('Saved.'),
            'reqId' => __('Government ID'),
            'reqIdHintDone' => __('On file and ready for review.'),
            'reqIdHintMissing' => __('Upload your ID to unlock submission.'),
            'reqIdAction' => __('Upload now'),
            'reqSubjects' => __('Subjects you will teach'),
            'reqSubjectsHintDone' => __('At least one subject selected.'),
            'reqSubjectsHintMissing' => __('Choose at least one subject before submitting.'),
            'reqSubjectsAction' => __('Choose now'),
            'reqTerms' => __('Terms and policies accepted'),
            'reqTermsHintDone' => __('Accepted.'),
            'reqTermsHintMissing' => __('Tick the box on the submit card.'),
            'documentsTitle' => __('Your documents'),
            'documentsHint' => __('Everything our team will review.'),
            'documentsCount' => __(':count on file'),
            'documentsEmptyTitle' => __('No documents uploaded yet'),
            'documentsEmptyHint' => __('Start with your government ID — it is the one we need before you can submit.'),
            'requiredDocument' => __('Required'),
            'optionalDocument' => __('Optional'),
            'uploadedDocument' => __('Uploaded'),
            'justUploaded' => __('Just uploaded'),
            'uploadedOn' => __('Uploaded :date'),
            'removeDocument' => __('Remove :type'),
            'uploadTitle' => __('Upload a document'),
            'uploadHint' => __('Files are saved as a draft right away — nothing reaches our team until you submit below.'),
            'uploadTypeLabel' => __('What are you uploading?'),
            'uploadFileLabel' => __('Choose the file'),
            'fileChoose' => __('Choose a file'),
            'fileDropHint' => __('or drag and drop it onto this box'),
            'fileTypes' => __('PDF, JPG, PNG or WebP · up to 5 MB'),
            'fileReady' => __('ready to upload'),
            'fileChange' => __('Change'),
            'fileTypeError' => __('That file type is not accepted. Use a PDF, JPG, PNG or WebP.'),
            'fileSizeError' => __('That file is larger than :limit. Try a smaller scan.'),
            'fileRequired' => __('Choose a file first.'),
            'uploadButton' => __('Upload document'),
            'uploadBusy' => __('Uploading…'),
            'uploadSuccess' => __('Saved to your documents.'),
            'uploadFailed' => __('That upload did not go through. Please try again.'),
            'uploadDraftNote' => __('Saved as a draft — not sent for review yet.'),
            'subjectsTitle' => __('What will you teach?'),
            'subjectsHint' => __('Pick every subject you can teach — you can choose from more than one level. Your selection goes to our team with your documents and is approved together with your application.'),
            'subjectsLockedHint' => __('Your subjects are locked while the application is under review.'),
            'subjectsSearch' => __('Search subjects'),
            'subjectsSelected' => __(':count selected'),
            'subjectsSelectAll' => __('Select all'),
            'subjectsClear' => __('Clear'),
            'subjectsEmpty' => __('No subjects match your search.'),
            'subjectsSaveFailed' => __('Your subjects could not be saved. Please try again.'),
            'submitTitle' => __('Submit for review'),
            'submitHint' => __('Nothing reaches our team until you submit. After that your documents are locked while we review — usually 1–2 business days.'),
            'termsLead' => __('I have read and accept the'),
            'termsLink' => __('teacher terms'),
            'termsAnd' => __('and the'),
            'privacyLink' => __('privacy policy'),
            'termsTail' => __(', including the commission and cancellation rules.'),
            'submitButton' => __('Submit for review'),
            'submitReady' => __('All requirements met — you can submit now.'),
            'submitBlockedId' => __('Upload your government ID above to enable submission.'),
            'submitBlockedSubjects' => __('Choose at least one subject you will teach to enable submission.'),
            'submitBlockedTerms' => __('Tick the terms box to enable submission.'),
            'confirmTitle' => __('Submit your application for review?'),
            'confirmBody' => __('Your documents will be locked while our team reviews them, so please make sure they are clear and correct first. Reviews usually take 1–2 business days and you will be notified once your profile is approved.'),
            'confirmYes' => __('Yes, submit for review'),
            'confirmNo' => __('Not yet'),
            'confirmClose' => __('Close'),
            'removeTitle' => __('Remove this document?'),
            'removeBody' => __('You can upload it again at any time before you submit for review.'),
            'removeConfirm' => __('Remove document'),
            'removeCancel' => __('Keep it'),
            'removeFailed' => __('That document could not be removed. Please try again.'),
            'completeTitle' => __('Onboarding complete'),
            'completeBody' => __('You can use your dashboard while our team reviews your application, or update your documents if we ask for changes.'),
            'completeCta' => __('Go to dashboard'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    // The noscript form below is plain Blade, so it keeps the same field names,
    // the same values and the same card styling as the Vue cards above it.
    $cardClasses = 'rounded-2xl border p-6 '.(in_array($themePreset, ['glass', 'ocean'], true)
        ? 'border-slate-200/40 bg-white/70 backdrop-blur-md dark:border-slate-800/40 dark:bg-slate-900/70'
        : 'border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900');
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

    <script type="application/json" id="teacher-verification-config">@json($payload, $jsonFlags)</script>

    <div id="teacher-verification-app"></div>

    {{-- Fallback for a browser without JavaScript: the upload and submit forms
         post to the same routes as the Vue cards. --}}
    <noscript>
        <div class="w-full space-y-6">
            @foreach ($notices as $notice)
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800/80 dark:bg-slate-900/60 dark:text-slate-200">
                    <p class="font-semibold">{{ $notice['title'] }}</p>
                    <p class="mt-0.5">{{ $notice['message'] }}</p>
                </div>
            @endforeach

            <x-teacher-onboarding-steps :current="3" />

            <!-- Status overview -->
            <div class="{{ $cardClasses }}">
                @if ($status === \App\Enums\VerificationStatus::Pending)
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Application under review</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        Thanks! We received your application {{ $profile->submitted_at?->diffForHumans() }}. Our team reviews documents within 1–2 business days.
                        You will be notified when your profile is approved and visible to students.
                    </p>
                @elseif ($status === \App\Enums\VerificationStatus::Approved)
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">You are verified 🎉</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        Your profile was approved {{ $profile->verified_at?->diffForHumans() }}. Verified teachers appear in student search and can be booked for live lessons.
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

            @if ($status->isSubmitted())
                <!-- Step 2 is done: the rest of the teacher area is unlocked. -->
                <div class="{{ $cardClasses }}">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-xl dark:bg-emerald-900/30">✅</span>
                            <div>
                                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Onboarding complete</h3>
                                <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                    You can use your dashboard while our team reviews your application, or update your documents if we ask for changes.
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('teacher.dashboard') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-all duration-200 hover:bg-primary/90 hover:shadow-lg active:scale-[0.98]">
                            Go to dashboard
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </div>
            @endif

            <!-- Documents -->
            <div class="{{ $cardClasses }}">
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
                <!-- Step 1: uploads save immediately; nothing is sent to the team until the teacher submits below. -->
                <div id="upload-documents" class="{{ $cardClasses }} scroll-mt-24">
                    <div class="flex items-start gap-3">
                        <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">1</span>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Upload your documents</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                PDF, JPG, PNG or WebP — up to 5 MB. A government ID is required to submit.
                                Files are saved as a draft right away — your application is only sent to our team when you submit it below.
                            </p>
                        </div>
                    </div>

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
                                <x-input-error :messages="$errors->get('document')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <x-secondary-button type="submit">{{ __('Upload document') }}</x-secondary-button>
                        </div>
                    </form>
                </div>

                <!-- Subjects: what the teacher will teach; approved together with the application. -->
                <div id="subjects" class="{{ $cardClasses }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">What will you teach?</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Pick every subject you can teach — you can choose from more than one level. Our team approves your subjects together with your application.
                    </p>

                    <form method="POST" action="{{ route('teacher.verification.subjects.store') }}" class="mt-5">
                        @csrf
                        @foreach ($subjectGroups as $group)
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-400 first:mt-0">{{ $group['name'] }}</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($group['subjects'] as $subject)
                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200/80 px-3.5 py-2.5 text-sm transition-colors hover:border-primary/40 dark:border-slate-800">
                                        <input type="checkbox" name="subjects[]" value="{{ $subject['id'] }}"
                                               @checked(in_array((int) $subject['id'], $selectedSubjectIds, true))
                                               class="rounded border-slate-300 text-primary shadow-sm focus:ring-primary dark:border-slate-600 dark:bg-slate-900" />
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

                <!-- Step 2: the final, locking action — visually distinct from uploading. -->
                <div class="{{ $cardClasses }}">
                    <div class="flex items-start gap-3">
                        <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">2</span>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Submit for review</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Nothing reaches our team until you submit below. After that your documents are locked while we review — usually 1–2 business days.
                            </p>
                        </div>
                    </div>

                    <ul class="mt-5 space-y-3 text-sm">
                        <li class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                            <svg class="h-5 w-5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Teaching profile completed
                        </li>
                        <li class="flex items-center gap-2 {{ $hasAvailability ? 'text-slate-600 dark:text-slate-300' : 'text-slate-500 dark:text-slate-400' }}">
                            @if ($hasAvailability)
                                <svg class="h-5 w-5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Weekly availability set</span>
                            @else
                                <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="flex flex-wrap items-center gap-x-2">
                                    Weekly availability required
                                    <a href="{{ route('teacher.availability.setup') }}" class="font-semibold text-primary hover:underline">Set now</a>
                                </span>
                            @endif
                        </li>
                        <li class="flex items-center gap-2 {{ $hasIdProof ? 'text-slate-600 dark:text-slate-300' : 'text-slate-500 dark:text-slate-400' }}">
                            @if ($hasIdProof)
                                <svg class="h-5 w-5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Government ID uploaded</span>
                            @else
                                <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="flex flex-wrap items-center gap-x-2">
                                    Government ID required
                                    <a href="#upload-documents" class="font-semibold text-primary hover:underline">Upload now</a>
                                </span>
                            @endif
                        </li>
                        <li class="flex items-center gap-2 {{ $hasSubjects ? 'text-slate-600 dark:text-slate-300' : 'text-slate-500 dark:text-slate-400' }}">
                            @if ($hasSubjects)
                                <svg class="h-5 w-5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Subjects chosen</span>
                            @else
                                <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="flex flex-wrap items-center gap-x-2">
                                    At least one subject required
                                    <a href="#subjects" class="font-semibold text-primary hover:underline">Choose now</a>
                                </span>
                            @endif
                        </li>
                        <li class="flex items-center gap-2 {{ $agreementAccepted ? 'text-slate-600 dark:text-slate-300' : 'text-slate-500 dark:text-slate-400' }}">
                            @if ($agreementAccepted)
                                <svg class="h-5 w-5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @else
                                <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                            <span>Terms and policies accepted</span>
                        </li>
                    </ul>

                    <form method="POST" action="{{ route('teacher.verification.submit') }}" class="mt-5 space-y-4">
                        @csrf

                        <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-800/40 dark:text-slate-300">
                            <input type="checkbox" name="agree" value="1" required @checked($agreementAccepted)
                                   class="mt-0.5 rounded border-slate-300 text-primary shadow-sm focus:ring-primary dark:border-slate-600 dark:bg-slate-900">
                            <span>
                                I have read and accept the
                                <a href="{{ route('legal.terms') }}" target="_blank" class="font-semibold text-primary hover:underline">teacher terms</a>
                                and the
                                <a href="{{ route('legal.privacy') }}" target="_blank" class="font-semibold text-primary hover:underline">privacy policy</a>,
                                including the commission and cancellation rules.
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('agree')" />

                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Submit your application for review? Your documents are locked once you do, so please make sure they are clear and correct first.
                        </p>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            @if ($hasIdProof && $hasSubjects)
                                <p class="text-xs text-emerald-600 dark:text-emerald-400">
                                    All requirements met — tick the box to submit.
                                </p>
                            @elseif (! $hasIdProof)
                                <p class="text-xs text-amber-600 dark:text-amber-400">
                                    Upload your government ID above to enable submission.
                                </p>
                            @else
                                <p class="text-xs text-amber-600 dark:text-amber-400">
                                    Choose at least one subject above to enable submission.
                                </p>
                            @endif
                            <button type="submit"
                                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-all duration-200 hover:bg-primary/90 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                    @if (! $hasIdProof || ! $hasSubjects) disabled @endif>
                                Submit for review
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </noscript>

    @vite('resources/js/teacher-verification/main.js')
</x-app-layout>
