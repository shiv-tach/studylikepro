@php
    $card = 'rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900';
    $field = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
@endphp

<x-public-layout :title="__('Contact support')">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 {{ $card }}">
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-50">{{ __('Talk to support') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Booking trouble, payments, refunds, verification or something else — tell us what happened and we will reply by email.') }}</p>

            @if (session('status') === 'contact-sent')
                <div class="mt-5 rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ __('Thanks — your message is with our support team. You will hear back at the address you gave us.') }}
                </div>
            @endif

            <form method="POST" action="{{ route('legal.contact.send') }}" class="mt-6 space-y-4">
                @csrf

                {{-- Honeypot: hidden from people, irresistible to bots. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="name" :value="__('Your name')" />
                        <input id="name" name="name" type="text" required value="{{ old('name', auth()->user()?->name) }}" class="{{ $field }}">
                        @error('name')
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <input id="email" name="email" type="email" required value="{{ old('email', auth()->user()?->email) }}" class="{{ $field }}">
                        @error('email')
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <x-input-label for="subject" :value="__('Subject')" />
                    <input id="subject" name="subject" type="text" required value="{{ old('subject') }}" class="{{ $field }}"
                           placeholder="{{ __('e.g. I was charged but the lesson did not happen') }}">
                    @error('subject')
                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-input-label for="message" :value="__('What happened?')" />
                    <textarea id="message" name="message" rows="6" required class="{{ $field }}"
                              placeholder="{{ __('Lesson date, teacher name, and anything we should look at.') }}">{{ old('message') }}</textarea>
                    @error('message')
                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <x-primary-button>{{ __('Send to support') }}</x-primary-button>
            </form>
        </div>

        <aside class="space-y-6">
            <div class="{{ $card }}">
                <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Other ways to reach us') }}</h2>
                <dl class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Email') }}</dt>
                        <dd><a href="mailto:{{ $supportEmail }}" class="text-primary hover:underline">{{ $supportEmail }}</a></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Phone') }}</dt>
                        <dd>{{ $supportPhone }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Hours') }}</dt>
                        <dd>{{ $supportHours }}</dd>
                    </div>
                </dl>
            </div>

            <div class="{{ $card }}">
                <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Faster than a form') }}</h2>
                <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li>{{ __('Lesson problem? Use "Report a problem" in the lesson chat — support sees the booking, payment and chat in one place.') }}</li>
                    <li>{{ __('Payment or refund question? The lesson receipt lists every charge and refund.') }}</li>
                    <li>{{ __('Teacher application? Track the status on your verification page.') }}</li>
                </ul>
            </div>
        </aside>
    </div>
</x-public-layout>
