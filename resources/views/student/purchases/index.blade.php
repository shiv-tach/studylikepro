@php
    use App\Enums\ServiceType;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('My purchases') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Lesson plans, summaries, paper marking, discussions and Q&A.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        @if (session('status') === 'purchase-cancelled')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('The purchase was cancelled.') }}
            </div>
        @endif

        <form method="GET" action="{{ route('student.purchases.index') }}" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <select name="type" onchange="this.form.submit()"
                    class="rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <option value="">{{ __('All services') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected($selectedType === $type->value)>{{ $type->icon() }} {{ $type->label() }}</option>
                @endforeach
            </select>
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <a href="{{ route('student.purchases.index', ['tab' => 'active', 'type' => $selectedType]) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors {{ $tab === 'active' ? 'bg-primary text-white' : 'text-slate-500 hover:text-primary dark:text-slate-400' }}">
                    {{ __('Active') }} <span class="ml-1 rounded-full bg-slate-100 px-1.5 text-[10px] dark:bg-slate-800">{{ $counts['active'] }}</span>
                </a>
                <a href="{{ route('student.purchases.index', ['tab' => 'past', 'type' => $selectedType]) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors {{ $tab === 'past' ? 'bg-primary text-white' : 'text-slate-500 hover:text-primary dark:text-slate-400' }}">
                    {{ __('Past') }} <span class="ml-1 rounded-full bg-slate-100 px-1.5 text-[10px] dark:bg-slate-800">{{ $counts['past'] }}</span>
                </a>
            </div>
        </form>

        @if ($purchases->isEmpty())
            <div class="{{ $cardBase }} px-6 py-12 text-center" :class="{{ $cardTheme }}">
                <p class="text-4xl">🛍️</p>
                <p class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('Nothing here yet.') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Browse a teacher profile and pick a service to get started.') }}</p>
                <a href="{{ route('teachers.index') }}"
                   class="mt-5 inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                    {{ __('Find a teacher') }}
                </a>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($purchases as $purchase)
                    <a href="{{ route('student.purchases.show', $purchase) }}"
                       class="{{ $cardBase }} block transition-all hover:-translate-y-0.5 hover:shadow-md" :class="{{ $cardTheme }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-primary">{{ $purchase->type()->icon() }} {{ $purchase->type()->label() }}</p>
                                <p class="mt-1 font-bold text-slate-800 dark:text-slate-100">{{ $purchase->scopeLabel() }}</p>
                                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ __('with :teacher', ['teacher' => $purchase->teacherProfile->user->name]) }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $purchase->status->badgeClasses() }}">{{ $purchase->status->label() }}</span>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3 text-sm dark:border-slate-800">
                            <span class="text-slate-500 dark:text-slate-400">
                                {{ platform_settings()->formatMinor($purchase->price_minor) }}
                            </span>
                            @if ($purchase->isLessonPlan() && $purchase->allocation_minutes)
                                @php $remaining = $purchase->remainingMinutes(); @endphp
                                @if ($purchase->status->isConsumable())
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $remaining > 0 ? 'bg-primary/10 text-primary' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' }}">
                                        {{ $remaining > 0 ? __(':minutes min left', ['minutes' => $remaining]) : __('Fully booked') }}
                                    </span>
                                @endif
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">{{ $purchases->links() }}</div>
        @endif
    </div>
</x-app-layout>
