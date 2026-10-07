@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $selectClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';

    $newInviteUrl = session('invite_token') ? route('register', ['invite' => session('invite_token')]) : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Invite teachers') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Teacher accounts can only be created through one of these links.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        @if ($newInviteUrl)
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-5 dark:border-emerald-900/40 dark:bg-emerald-950/30" x-data="{ copied: false }">
                <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Invite created — share this link once. It cannot be viewed again.</p>
                <div class="mt-3 flex flex-wrap items-stretch gap-2">
                    <input type="text" readonly value="{{ $newInviteUrl }}"
                           class="min-w-0 flex-1 rounded-xl border-emerald-200 bg-white px-3 py-2 font-mono text-xs text-slate-700 focus:outline-none dark:border-emerald-900/60 dark:bg-slate-900 dark:text-slate-200" />
                    <button type="button" @click="navigator.clipboard.writeText('{{ $newInviteUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        <span x-show="!copied">{{ __('Copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </button>
                </div>
            </div>
        @endif

        @if (session('status') === 'invite-revoked')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                Invite revoked.
            </div>
        @endif

        <!-- Create form -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">New onboarding link</h3>
            <form method="POST" action="{{ route('admin.invites.store') }}" class="mt-5 flex flex-wrap items-end gap-4">
                @csrf
                <div class="w-full sm:w-56">
                    <x-input-label for="expires_in_days" :value="__('Expires after')" />
                    <select id="expires_in_days" name="expires_in_days" class="{{ $selectClasses }}">
                        <option value="1">1 day</option>
                        <option value="3">3 days</option>
                        <option value="7" @selected($defaultExpiryDays === 7)>7 days</option>
                        <option value="14" @selected($defaultExpiryDays === 14)>14 days</option>
                        <option value="30" @selected($defaultExpiryDays === 30)>30 days</option>
                    </select>
                    <x-input-error :messages="$errors->get('expires_in_days')" class="mt-2" />
                </div>
                <x-primary-button>{{ __('Create invite') }}</x-primary-button>
            </form>
        </div>

        <!-- Invite list -->
        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            @if ($invites->isEmpty())
                <div class="p-10 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">No invites yet — create the first one above.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($invites as $invite)
                        <li class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    #{{ $invite->id }}
                                    @if ($invite->isUsable())
                                        <span class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Available</span>
                                    @elseif ($invite->isConsumed())
                                        <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">Used</span>
                                    @else
                                        <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Expired</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    Created {{ $invite->created_at->diffForHumans() }} by {{ $invite->creator?->name ?? '—' }}
                                    · expires {{ $invite->expires_at?->toDateString() ?? 'never' }}
                                </p>
                            </div>

                            @if ($invite->isUsable())
                                <form method="POST" action="{{ route('admin.invites.revoke', $invite) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500 transition-colors hover:border-rose-300 hover:text-rose-600 dark:border-slate-700 dark:text-slate-400">
                                        {{ __('Revoke') }}
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                    {{ $invites->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
