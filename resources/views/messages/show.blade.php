@php
    use App\Models\Dispute;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $bookingsRoute = $isTeacher ? 'teacher.bookings.show' : 'student.bookings.show';
    $lesson = $conversation->booking;
    $lessonTitle = $lesson?->lesson?->name ?? $lesson?->subject?->name ?? __('Tutoring lesson');
    $timezone = auth()->user()->studentProfile?->timezone
        ?? auth()->user()->teacherProfile?->timezone
        ?? config('studylikepro.default_display_timezone');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('messages.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('All messages') }}</a>
                <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ $counterpart }}</h2>
                @if ($lesson)
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ $lessonTitle }} · {{ $lesson->starts_at->copy()->setTimezone($timezone)->format('D d M Y, H:i') }} ·
                        <a href="{{ route($bookingsRoute, $lesson) }}" class="font-semibold text-primary hover:underline">{{ __('open the lesson') }}</a>
                    </p>
                @endif
            </div>

            @if ($reportAlreadyOpen)
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                    {{ __('Report under review') }}
                </span>
            @else
                <button type="button" x-data="" @click="$dispatch('open-modal', 'report-conversation')"
                        class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    {{ __('Report a problem') }}
                </button>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4">
        @if (session('status') === 'report-received')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Thanks — our support team has this conversation and will follow up by email or in the app.') }}
            </div>
        @elseif (session('status') === 'report-already-open')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                {{ __('You already have an open report on this lesson — support will get back to you before long.') }}
            </div>
        @endif

        <div class="{{ $cardBase }} flex flex-col" :class="{{ $cardTheme }}"
             x-data="{
                 after: {{ $messages->last()?->id ?? 0 }},
                 rendered: [{{ $messages->pluck('id')->implode(', ') }}],
                 pollUrl: '{{ route('messages.poll', $conversation) }}',
                 storeUrl: '{{ route('messages.store', $conversation) }}',
                 csrf: '{{ csrf_token() }}',
                 sending: false,
                 body: '',
                 errors: '',
                 templateFor(message) {
                     if (message.type === 'system') return this.$refs.templateSystem;
                     return message.mine ? this.$refs.templateMine : this.$refs.templateTheirs;
                 },
                 build(message) {
                     const node = this.templateFor(message).content.firstElementChild.cloneNode(true);
                     node.dataset.messageId = message.id;
                     node.querySelectorAll('[data-slot]').forEach((element) => {
                         if (element.dataset.slot === 'sender') element.textContent = message.sender || '';
                         if (element.dataset.slot === 'at') element.textContent = message.at || '';
                         if (element.dataset.slot === 'initials') element.textContent = (message.sender || '?').substring(0, 2);
                     });
                     const body = node.querySelector('[data-body]');
                     if (body) {
                         if (message.body) { body.textContent = message.body; } else { body.remove(); }
                     }
                     const image = node.querySelector('[data-image]');
                     if (image) {
                         if (message.attachment_url) { image.src = message.attachment_url; } else { image.closest('a').remove(); }
                     }
                     return node;
                 },
                 append(message) {
                     if (this.rendered.includes(message.id)) return;
                     this.rendered.push(message.id);
                     this.after = Math.max(this.after, message.id);
                     this.$refs.thread.appendChild(this.build(message));
                     this.scrollToEnd();
                 },
                 scrollToEnd() {
                     this.$nextTick(() => { this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight; });
                 },
                 async send() {
                     if (this.sending) return;
                     const file = this.$refs.image && this.$refs.image.files.length ? this.$refs.image.files[0] : null;
                     if (!this.body.trim() && !file) { this.errors = {{ Illuminate\Support\Js::from(__('Write a message or attach a photo first.')) }}; return; }
                     const form = new FormData();
                     if (this.body.trim()) form.append('body', this.body);
                     if (file) form.append('image', file);
                     this.sending = true;
                     this.errors = '';
                     try {
                         const response = await fetch(this.storeUrl, {
                             method: 'POST',
                             headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                             body: form,
                         });
                         if (response.status === 422) {
                             const data = await response.json();
                             this.errors = Object.values(data.errors || {}).flat().join(' ');
                             return;
                         }
                         if (!response.ok) { this.errors = {{ Illuminate\Support\Js::from(__('That message did not go through — please try again.')) }}; return; }
                         const data = await response.json();
                         this.append(data.message);
                         this.body = '';
                         if (this.$refs.image) this.$refs.image.value = '';
                     } catch (error) {
                         this.errors = {{ Illuminate\Support\Js::from(__('That message did not go through — check your connection.')) }};
                     } finally {
                         this.sending = false;
                         this.scrollToEnd();
                     }
                 },
                 async tick() {
                     try {
                         const response = await fetch(this.pollUrl + '?after=' + this.after, { headers: { 'Accept': 'application/json' } });
                         if (!response.ok) return;
                         const data = await response.json();
                         data.messages.forEach((message) => this.append(message));
                     } catch (error) {
                         // Offline for a moment; the next tick catches up.
                     }
                 },
                 init() {
                     this.scrollToEnd();
                     setInterval(() => this.tick(), 5000);
                 },
             }">
            <!-- Thread -->
            <div x-ref="thread" class="flex max-h-[60vh] min-h-[18rem] flex-col gap-4 overflow-y-auto pr-1">
                @foreach ($messages as $message)
                    @include('messages.partials.bubble', ['message' => $message, 'mine' => $message->wasSentBy(auth()->user())])
                @endforeach
            </div>

            <!-- Composer -->
            <form @submit.prevent="send()" class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800">
                <div class="flex items-end gap-3">
                    <textarea x-model="body" rows="2" maxlength="2000"
                              placeholder="{{ __('Write a message…') }}"
                              class="block w-full resize-none rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"></textarea>

                    <label class="cursor-pointer rounded-xl border border-slate-300 p-2.5 text-slate-500 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800" title="{{ __('Attach a photo') }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="sr-only">{{ __('Attach a photo') }}</span>
                    </label>
                    <input type="file" accept="image/*" x-ref="image" class="sr-only">

                    <button type="submit" :disabled="sending"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!sending">{{ __('Send') }}</span>
                        <span x-show="sending" x-cloak>{{ __('Sending…') }}</span>
                    </button>
                </div>
                <p x-show="errors" x-text="errors" class="mt-2 text-xs text-rose-600 dark:text-rose-400" x-cloak></p>
                <p class="mt-2 text-[11px] text-slate-400">{{ __('Messages cannot be edited or deleted. Keep personal contact details out of the chat.') }}</p>
            </form>

            <!-- Templates the poller clones when new messages arrive -->
            <template x-ref="templateTheirs">
                <div class="flex justify-start gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-200" data-slot="initials"></div>
                    <div class="max-w-[80%]">
                        <p class="text-xs text-slate-400"><span data-slot="sender"></span> · <span data-slot="at"></span></p>
                        <div class="mt-1 whitespace-pre-line rounded-2xl rounded-bl-md bg-slate-100 px-4 py-2 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200" data-body></div>
                        <a href="#" target="_blank" rel="noopener noreferrer" class="mt-2 block">
                            <img data-image alt="{{ __('Shared photo') }}" class="max-h-64 rounded-xl border border-slate-200 object-cover dark:border-slate-700">
                        </a>
                    </div>
                </div>
            </template>

            <template x-ref="templateMine">
                <div class="flex justify-end gap-3">
                    <div class="max-w-[80%]">
                        <p class="text-right text-xs text-slate-400">{{ __('You') }} · <span data-slot="at"></span></p>
                        <div class="mt-1 whitespace-pre-line rounded-2xl rounded-br-md bg-primary px-4 py-2 text-sm text-white" data-body></div>
                        <a href="#" target="_blank" rel="noopener noreferrer" class="mt-2 block">
                            <img data-image alt="{{ __('Shared photo') }}" class="ml-auto max-h-64 rounded-xl border border-slate-200 object-cover dark:border-slate-700">
                        </a>
                    </div>
                </div>
            </template>

            <template x-ref="templateSystem">
                <p class="mx-auto max-w-md rounded-full bg-slate-100 px-4 py-1.5 text-center text-xs text-slate-500 dark:bg-slate-800/70 dark:text-slate-400" data-body></p>
            </template>
        </div>

        <p class="px-2 text-xs text-slate-500 dark:text-slate-400">
            {{ __('Something wrong with this lesson? Use "Report a problem" and our support team steps in — disputes and refunds are decided by Studylikepro, not by the chat.') }}
        </p>
    </div>

    @unless ($reportAlreadyOpen)
        <x-modal name="report-conversation" focusable>
            <form method="POST" action="{{ route('messages.report', $conversation) }}" class="p-6">
                @csrf
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Report a problem') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('This opens a dispute our support team reviews. Money issues, no-shows and behaviour reports all start here.') }}
                </p>

                <div class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="report-reason" :value="__('What happened?')" />
                        <select id="report-reason" name="reason" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            @foreach (Dispute::REASONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="report-details" :value="__('Anything we should know? (optional)')" />
                        <textarea id="report-details" name="details" rows="3" maxlength="1000"
                                  class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('details') }}</textarea>
                        <x-input-error :messages="$errors->get('details')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                    <x-danger-button>{{ __('Send to support') }}</x-danger-button>
                </div>
            </form>
        </x-modal>
    @endunless
</x-app-layout>
