@php
    // The question form is rendered by resources/js/student-requests. Blade owns
    // the copy, the limits, the validation messages and the old input, then
    // hands them over as a JSON island, so the Vue app hardcodes no string,
    // URL or validation rule of its own.
    $themePreset = $userTheme['preset'] ?? 'classic';
    $timezone = auth()->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone');
    $maxWindows = 5;
    $maxDescription = 2000;
    $minDescription = 20;

    // A blank form opens on one window for tomorrow evening; a bounced
    // submission brings back exactly the rows the student typed.
    $windowRows = collect(old('windows', [[
        'date' => now()->addDay()->toDateString(),
        'from' => '18:00',
        'to' => '19:00',
    ]]))->map(fn ($window) => [
        'date' => $window['date'] ?? '',
        'from' => $window['from'] ?? '18:00',
        'to' => $window['to'] ?? '19:00',
    ])->values()->all();

    // Every key the form request can produce is looked up by name, so a message
    // lands on the field that failed instead of a generic error block.
    $fieldErrors = collect(['description', 'budget', 'attachments', 'windows'])
        ->merge(collect(range(0, $maxAttachments - 1))->map(fn (int $index) => "attachments.{$index}"))
        ->merge(collect(range(0, $maxWindows - 1))->flatMap(fn (int $index) => [
            "windows.{$index}.date",
            "windows.{$index}.from",
            "windows.{$index}.to",
        ]))
        ->mapWithKeys(fn (string $key) => [$key => $errors->get($key)])
        ->reject(fn (array $messages) => $messages === [])
        ->all();

    $requestForm = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'learnerGrade' => $learnerGrade?->label,
        'timezone' => $timezone,
        'currencySymbol' => platform_settings()->currencySymbol(),
        'maxAttachments' => $maxAttachments,
        'maxWindows' => $maxWindows,
        'maxDescription' => $maxDescription,
        'minDescription' => $minDescription,
        'minWindowDate' => now()->toDateString(),
        'acceptedImageTypes' => 'image/jpeg,image/png,image/webp',
        // The grade is locked to the learning profile, so the page shows it
        // read-only: there is no grade_id field for anyone to tamper with.
        'values' => [
            'description' => (string) old('description', ''),
            'budget' => old('budget'),
            'windows' => $windowRows,
        ],
        'errors' => $fieldErrors,
        'copy' => [
            'introTitle' => __('Describe it in your own words'),
            'introBody' => __('Write the question exactly as you would to a teacher. Our AI reads it (and the photo you attach) to pick the subject and lesson, then quietly notifies the best-matched verified teachers. You will see their proposals right here.'),
            'questionTitle' => __('Your question'),
            'questionHint' => __('At least a couple of sentences — the more detail, the better the match.'),
            'questionPlaceholder' => __('e.g. I keep getting stuck on factorising quadratic equations when the middle term splits into fractions. My exam is next month.'),
            'descriptionMin' => __('At least :count characters.', ['count' => $minDescription]),
            'gradeLabel' => __('Learner grade'),
            'gradeNotSet' => __('Not set'),
            'gradeHint' => __('From your learning profile — we only match lessons for this grade.'),
            'gradeChange' => __('Change grade'),
            'windowsTitle' => __('When could you attend?'),
            'windowsHint' => __('Add up to :count time windows in your timezone (:timezone). Teachers can only propose times inside them.', [
                'count' => $maxWindows,
                'timezone' => $timezone,
            ]),
            'windowAdd' => __('Add window'),
            'windowDate' => __('Date'),
            'windowFrom' => __('From'),
            'windowTo' => __('To'),
            'windowRemove' => __('Remove window'),
            'windowLimit' => __('That is the maximum number of windows.'),
            'attachmentTitle' => __('Photo of the problem'),
            'optional' => __('optional'),
            'attachmentHint' => __('Up to :count images (JPG, PNG or WebP, 5 MB each). A clear photo often lets the AI match the exact lesson on the first try.', ['count' => $maxAttachments]),
            'attachmentChoose' => __('Choose images'),
            'attachmentDrop' => __('or drop them anywhere in this box'),
            'attachmentRemove' => __('Remove image'),
            'attachmentLimit' => __('Only the first :count images are kept.', ['count' => $maxAttachments]),
            'attachmentEmpty' => __('No photo attached yet — a clear picture of the question helps a lot.'),
            'budgetTitle' => __('Budget'),
            'budgetHint' => __('A rough per-lesson budget helps teachers price fairly. Leave it blank and they will quote their usual rate.'),
            'budgetPlaceholder' => '800',
            'budgetPerLesson' => __('per lesson'),
            'errorTitle' => __('We could not send your question yet'),
            'errorIntro' => __('Please check the highlighted fields below.'),
            'submit' => __('Submit question'),
            'submitting' => __('Sending…'),
            'cancel' => __('Cancel'),
        ],
        'urls' => [
            'store' => route('student.requests.store'),
            'index' => route('student.requests.index'),
            'profile' => route('student.profile'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Ask a question') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <script type="application/json" id="student-request-form-config">@json($requestForm, $jsonFlags)</script>

        <div id="student-request-form-app"></div>

        {{-- Fallback for a browser without JavaScript: the same field names,
             limits and validation attributes, posted straight to the server. --}}
        <noscript>
            <form method="POST" action="{{ route('student.requests.store') }}" enctype="multipart/form-data"
                  class="space-y-6 rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                @csrf

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Your question') }}
                    </label>
                    <textarea id="description" name="description" rows="5" minlength="{{ $minDescription }}" maxlength="{{ $maxDescription }}" required
                              class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('description') }}</textarea>
                </div>

                <div>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Learner grade') }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $learnerGrade?->label ?? __('Not set') }}</p>
                </div>

                <div class="space-y-3">
                    @foreach ($windowRows as $index => $window)
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="grow">
                                <label for="window-date-{{ $index }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Date') }}</label>
                                <input id="window-date-{{ $index }}" type="date" name="windows[{{ $index }}][date]" value="{{ $window['date'] }}" min="{{ now()->toDateString() }}" required
                                       class="mt-1 w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                            </div>
                            <div>
                                <label for="window-from-{{ $index }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('From') }}</label>
                                <input id="window-from-{{ $index }}" type="time" name="windows[{{ $index }}][from]" value="{{ $window['from'] }}" required
                                       class="mt-1 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                            </div>
                            <div>
                                <label for="window-to-{{ $index }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('To') }}</label>
                                <input id="window-to-{{ $index }}" type="time" name="windows[{{ $index }}][to]" value="{{ $window['to'] }}" required
                                       class="mt-1 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                            </div>
                        </div>
                    @endforeach
                </div>

                <div>
                    <label for="attachments" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Photo of the problem') }}</label>
                    <input id="attachments" type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp" multiple
                           class="mt-1 block w-full cursor-pointer text-sm text-slate-500 dark:text-slate-400" />
                </div>

                <div>
                    <label for="budget" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Budget') }}</label>
                    <input id="budget" type="number" name="budget" step="0.01" min="0" max="100000" value="{{ old('budget') }}" placeholder="800"
                           class="mt-1 block w-32 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" />
                </div>

                @foreach ($fieldErrors as $messages)
                    @foreach ($messages as $message)
                        <p class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @endforeach
                @endforeach

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('student.requests.index') }}" class="text-sm font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Submit question') }}</x-primary-button>
                </div>
            </form>
        </noscript>
    </div>

    @vite('resources/js/student-requests/main.js')
</x-app-layout>
