@php
    // /admin/settings is rendered by resources/js/admin-settings. Blade keeps the
    // setting definitions (grouped, with their labels and already-formatted
    // values), the validation messages and the route map, then hands them over as
    // a JSON island, so no field, bound or URL is hardcoded in Vue.
    $settingsService = platform_settings();
    $smsLenz = app(\App\Support\SmsLenzConfig::class);
    $smsTest = session('sms_test');

    $groupLabels = [
        'money' => __('Money'),
        'bookings' => __('Bookings'),
        'refunds' => __('Cancellations & refunds'),
        'services' => __('Services'),
        'payments' => __('Checkout & payments'),
        'smslenz' => __('SMSLenz API'),
        'sms' => __('SMS & OTP'),
    ];

    $groups = [];
    $settingErrors = [];

    foreach ($definitions as $key => $definition) {
        $rawValue = old($key, $values[$key] ?? '');
        $isSecret = $definition['type'] === 'secret';

        // A stored API key never travels back to the browser: the island only
        // says whether one is saved, and the field stays empty (blank = keep).
        $inputValue = match (true) {
            $isSecret => '',
            // Money settings are stored in minor units but edited in whole currency.
            $definition['type'] === 'money' => number_format(((int) $rawValue) / 100, 2, '.', ''),
            default => $rawValue,
        };

        $groups[$definition['group']][] = [
            'key' => $key,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'type' => $definition['type'],
            'value' => $inputValue,
            'inputType' => match ($definition['type']) {
                'string' => 'text',
                'secret' => 'password',
                'select' => null,
                default => 'number',
            },
            'step' => $definition['step'] ?? (in_array($definition['type'], ['float', 'money'], true) ? '0.01' : null),
            'min' => $definition['min'] ?? null,
            'max' => $definition['max'] ?? null,
            'hasValue' => $isSecret ? filled($values[$key] ?? null) : null,
            'placeholder' => $isSecret
                ? (filled($values[$key] ?? null) ? __('Saved — leave blank to keep') : __('Paste the API key'))
                : null,
            'options' => collect($definition['options'] ?? [])
                ->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])
                ->values()
                ->all(),
        ];

        // Blade's $errors->get() returns an empty array when the key is absent,
        // so the island never carries a phantom message.
        $settingErrors[$key] = $errors->get($key);
    }

    $orderedGroups = collect($groups)
        ->map(fn ($items, $group) => [
            'key' => $group,
            'label' => $groupLabels[$group] ?? ucfirst($group),
            'items' => array_values($items),
        ])
        ->values()
        ->all();

    $adminSettingsPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $userTheme['preset'] ?? 'classic'],
        'groups' => $orderedGroups,
        'errors' => $settingErrors,
        'status' => [
            'saved' => session('status') === 'settings-saved',
        ],
        // The connection test card reads the saved account, the last test
        // result (a flash) and what a bounced submission typed.
        'sms' => [
            'provider' => $smsLenz->provider(),
            'providerLabel' => $smsLenz->provider() === 'smslenz' ? 'SMSLenz' : __('Demo (log only)'),
            'senderId' => $smsLenz->credentials()['sender_id'],
            'configured' => $smsLenz->isConfigured(),
            'missing' => $smsLenz->missing(),
            'test' => $smsTest,
            'errors' => [
                'mobile' => $errors->get('mobile'),
                'message' => $errors->get('message'),
            ],
            'old' => [
                'mobile' => old('mobile', ''),
                'message' => old('message', ''),
            ],
            'defaultMessage' => __('This is a test message from :app.', ['app' => config('app.name', 'Studylikepro')]),
        ],
        'meta' => [
            'currencySymbol' => $settingsService->currencySymbol(),
        ],
        'copy' => [
            'saved' => __('Settings saved.'),
            'errorTitle' => __('Please check the highlighted fields.'),
            'save' => __('Save settings'),
            'saving' => __('Saving...'),
            'smsTest' => [
                'title' => __('Test the connection'),
                'hint' => __('Send a sample message with the saved credentials. A real SMS is delivered and uses one credit.'),
                'providerLabel' => __('Provider'),
                'senderLabel' => __('Sender ID'),
                'credentialsLabel' => __('Credentials'),
                'ready' => __('Saved and ready'),
                'notReady' => __('Not configured'),
                'missingLabel' => __('Missing'),
                'saveFirst' => __('Save the credentials above, then run the test.'),
                'mobileLabel' => __('Send to'),
                'mobileHint' => __('A Sri Lankan mobile, e.g. :example.', ['example' => \App\Support\PhoneNumber::DISPLAY_EXAMPLE]),
                'mobilePlaceholder' => '07X XXX XXXX',
                'messageLabel' => __('Message'),
                'send' => __('Send test SMS'),
                'sending' => __('Sending...'),
                'successTitle' => __('SMSLenz accepted the message.'),
                'failureTitle' => __('The test message could not be sent.'),
                'campaignLabel' => __('Campaign ID'),
                'creditsLabel' => __('Credits left'),
                'pagesLabel' => __('Pages'),
                'planLabel' => __('Plan'),
                'recipientLabel' => __('Recipient'),
                'sentAtLabel' => __('Tested'),
                'docsLabel' => __('Find your API credentials'),
                'docsUrl' => 'https://smslenz.lk/account/api-key',
            ],
        ],
        'urls' => [
            'update' => route('admin.settings.update'),
            'offers' => route('admin.offers.index'),
            'smsTest' => route('admin.settings.sms-test'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $fieldClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Platform settings') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Commission, fees, holds and the refund policy the marketplace runs on.') }}</p>
            </div>
            <a href="{{ route('admin.offers.index') }}" class="text-xs font-semibold text-primary hover:underline">{{ __('Special offers') }} &rarr;</a>
        </div>
    </x-slot>

    <script type="application/json" id="admin-settings-config">@json($adminSettingsPayload, $jsonFlags)</script>

    <div id="admin-settings-app"></div>

    {{-- Fallback for a browser without JavaScript: the same settings as a real
         PUT form, with the names the server validates. --}}
    <noscript>
        <div class="mx-auto max-w-3xl space-y-6">
            @if (session('status') === 'settings-saved')
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ __('Settings saved.') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                @foreach ($orderedGroups as $group)
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $group['label'] }}</h3>

                        <div class="mt-4 space-y-5">
                            @foreach ($group['items'] as $item)
                                <div>
                                    <x-input-label :for="'setting-'.$item['key']" :value="$item['label']" />
                                    @if ($item['type'] === 'select')
                                        <select id="setting-{{ $item['key'] }}" name="{{ $item['key'] }}" class="{{ $fieldClasses }}">
                                            @foreach ($item['options'] as $option)
                                                <option value="{{ $option['value'] }}" @selected((string) $item['value'] === (string) $option['value'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($item['type'] === 'text')
                                        <textarea id="setting-{{ $item['key'] }}" name="{{ $item['key'] }}" rows="3" class="{{ $fieldClasses }}">{{ $item['value'] }}</textarea>
                                    @else
                                        <x-text-input :id="'setting-'.$item['key']" :name="$item['key']"
                                                      :type="$item['inputType']"
                                                      :step="$item['step']"
                                                      :min="$item['min']"
                                                      :max="$item['max']"
                                                      :placeholder="$item['placeholder']"
                                                      class="mt-1 block w-full"
                                                      :value="$item['value']" />
                                    @endif
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $item['description'] }}
                                        @if ($item['type'] === 'money')
                                            · {{ __('in :currency', ['currency' => $settingsService->currencySymbol()]) }}
                                        @endif
                                    </p>
                                    <x-input-error :messages="$settingErrors[$item['key']]" class="mt-2" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <x-primary-button>{{ __('Save settings') }}</x-primary-button>
                </div>
            </form>

            {{-- The SMSLenz connection test: a separate POST, outside the settings form. --}}
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Test the connection') }}</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Send a sample message with the saved credentials. A real SMS is delivered and uses one credit.') }}
                </p>

                @if ($smsTest)
                    <div class="mt-4 rounded-xl border p-3 text-sm {{ ($smsTest['ok'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
                        {{ ($smsTest['ok'] ?? false) ? __('SMSLenz accepted the message.') : ($smsTest['message'] ?? '') }}
                        @if (! empty($smsTest['campaign_id']))
                            <span class="ml-2 text-xs">{{ __('Campaign ID') }}: {{ $smsTest['campaign_id'] }}</span>
                        @endif
                        @if (! empty($smsTest['credits']))
                            <span class="ml-2 text-xs">{{ __('Credits left') }}: {{ $smsTest['credits'] }}</span>
                        @endif
                    </div>
                @endif

                @if (! $smsLenz->isConfigured())
                    <p class="mt-4 text-xs font-semibold text-amber-700 dark:text-amber-400">
                        {{ __('Save the :fields above, then run the test.', ['fields' => \Illuminate\Support\Arr::join($smsLenz->missing(), ', ', ' '.__('and').' ')]) }}
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.settings.sms-test') }}" class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="sms-test-mobile" :value="__('Send to')" />
                        <x-text-input id="sms-test-mobile" name="mobile" type="tel" class="mt-1 block w-full" :value="old('mobile')" :placeholder="\App\Support\PhoneNumber::DISPLAY_EXAMPLE" required />
                        <x-input-error :messages="$errors->get('mobile')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="sms-test-message" :value="__('Message')" />
                        <textarea id="sms-test-message" name="message" rows="2" class="{{ $fieldClasses }}" required>{{ old('message', __('This is a test message from :app.', ['app' => config('app.name', 'Studylikepro')])) }}</textarea>
                        <x-input-error :messages="$errors->get('message')" class="mt-2" />
                    </div>

                    <x-primary-button>{{ __('Send test SMS') }}</x-primary-button>
                </form>
            </div>
        </div>
    </noscript>

    @vite('resources/js/admin-settings/main.js')
</x-app-layout>
