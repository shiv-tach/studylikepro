@php
    // The public contact page is rendered by resources/js/public. Blade owns the
    // copy, the support details, the CSRF token, the old input and the
    // validation messages, then hands them over as a JSON island.
    $config = [
        'appName' => config('app.name', 'Studylikepro'),
        'authenticated' => auth()->check(),
        'csrfToken' => csrf_token(),
        'sent' => session('status') === 'contact-sent',
        'old' => [
            'name' => old('name', auth()->user()?->name),
            'email' => old('email', auth()->user()?->email),
            'subject' => old('subject'),
            'message' => old('message'),
        ],
        'errors' => [
            'name' => $errors->first('name'),
            'email' => $errors->first('email'),
            'subject' => $errors->first('subject'),
            'message' => $errors->first('message'),
            'website' => $errors->first('website'),
        ],
        'support' => [
            'email' => $supportEmail,
            'phone' => $supportPhone,
            'hours' => $supportHours,
        ],
        'copy' => [
            'eyebrow' => __('Support'),
            'title' => __('Talk to support'),
            'intro' => __('Booking trouble, payments, refunds, verification or something else — tell us what happened and we will reply by email.'),
            'sent' => __('Thanks — your message is with our support team. You will hear back at the address you gave us.'),
            'name' => __('Your name'),
            'email' => __('Email'),
            'subject' => __('Subject'),
            'subjectPlaceholder' => __('e.g. I was charged but the lesson did not happen'),
            'message' => __('What happened?'),
            'messagePlaceholder' => __('Lesson date, teacher name, and anything we should look at.'),
            'send' => __('Send to support'),
            'otherWays' => __('Other ways to reach us'),
            'emailLabel' => __('Email'),
            'phoneLabel' => __('Phone'),
            'hoursLabel' => __('Hours'),
            'fasterTitle' => __('Faster than a form'),
            'fasterTips' => [
                __('Lesson problem? Use "Report a problem" in the lesson chat — support sees the booking, payment and chat in one place.'),
                __('Payment or refund question? The lesson receipt lists every charge and refund.'),
                __('Teacher application? Track the status on your verification page.'),
            ],
            'navSubjects' => __('Subjects'),
            'navTeachers' => __('Teachers'),
            'navContact' => __('Contact'),
        ],
        'urls' => [
            'home' => route('home'),
            'subjects' => route('catalog.subjects.index'),
            'teachers' => route('teachers.index'),
            'contact' => route('legal.contact'),
            'contactSend' => route('legal.contact.send'),
            'login' => route('login'),
            'registerStudent' => route('register', ['role' => 'student']),
            'dashboard' => route('dashboard'),
            'privacy' => route('legal.privacy'),
            'terms' => route('legal.terms'),
            'refunds' => route('legal.refunds'),
        ],
        'images' => [
            'logo' => asset('images/brand/logo-nav.png'),
        ],
    ];

    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-public-page
    :title="__('Contact support')"
    :description="__('Contact Studylikepro support about a lesson, payment, refund, teacher verification or anything else.')"
    :vite="['resources/css/app.css', 'resources/js/public/main.js']"
>
    <script type="application/json" id="public-contact-config">@json($config, $jsonFlags)</script>

    <div id="public-contact-app"></div>

    {{-- Fallback for crawlers and visitors without JavaScript: the same form,
         posted natively. --}}
    <noscript>
        <div style="max-width:44rem;margin:0 auto;padding:3rem 1.5rem;font-family:system-ui,sans-serif;line-height:1.6">
            <h1>{{ __('Talk to support') }}</h1>
            <p>{{ __('Booking trouble, payments, refunds, verification or something else — tell us what happened and we will reply by email.') }}</p>

            @if (session('status') === 'contact-sent')
                <p><strong>{{ __('Thanks — your message is with our support team. You will hear back at the address you gave us.') }}</strong></p>
            @endif

            <form method="POST" action="{{ route('legal.contact.send') }}">
                @csrf
                <div class="hidden" aria-hidden="true">
                    <label for="website">{{ __('Website') }}</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <p><label for="name">{{ __('Your name') }}</label><br>
                    <input id="name" name="name" type="text" required value="{{ $config['old']['name'] }}"></p>
                <p><label for="email">{{ __('Email') }}</label><br>
                    <input id="email" name="email" type="email" required value="{{ $config['old']['email'] }}"></p>
                <p><label for="subject">{{ __('Subject') }}</label><br>
                    <input id="subject" name="subject" type="text" required value="{{ $config['old']['subject'] }}"></p>
                <p><label for="message">{{ __('What happened?') }}</label><br>
                    <textarea id="message" name="message" rows="6" required>{{ $config['old']['message'] }}</textarea></p>

                <button type="submit">{{ __('Send to support') }}</button>
            </form>

            <h2>{{ __('Other ways to reach us') }}</h2>
            <ul>
                <li>{{ __('Email') }}: <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>
                <li>{{ __('Phone') }}: {{ $supportPhone }}</li>
                <li>{{ __('Hours') }}: {{ $supportHours }}</li>
            </ul>
        </div>
    </noscript>
</x-public-page>
