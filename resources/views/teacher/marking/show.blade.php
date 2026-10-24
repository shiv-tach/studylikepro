@php
    use App\Support\TeacherMarkingShowConfig;

    // /teacher/marking/{order} is rendered by resources/js/teacher-marking-show.
    // Blade owns every label, formatted date, amount, file size and download
    // route, then hands them over as a JSON island. The <noscript> block below
    // repeats the same paperwork and a plain multipart delivery form.
    $config = TeacherMarkingShowConfig::build($order, $maxFiles, $userTheme['preset'] ?? 'classic');

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    // The no-JS twin stays preset-independent, so it maps the semantic tone names
    // onto literal Tailwind classes here.
    $cardClass = 'rounded-2xl border p-6 border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900';
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $toneClasses = [
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'primary' => 'bg-primary/10 text-primary',
        'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ $config['urls']['index'] }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Marking inbox') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Marking order') }} · {{ $config['order']['subjectName'] }}</h2>
        </div>
    </x-slot>

    <div class="w-full space-y-6">
        <script type="application/json" id="teacher-marking-show-config">@json($config, $jsonFlags)</script>

        <div id="teacher-marking-show-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the same order meta, the
             same paper and a plain multipart delivery form. --}}
        <noscript>
            <div class="space-y-6">
                @if (session('status') === 'marking-returned')
                    <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {{ __('Marked copy delivered — the student has been notified.') }}
                    </div>
                @endif

                <div class="{{ $cardClass }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $toneClasses[$config['order']['status']['tone']] ?? $toneClasses['slate'] }}">
                            {{ $config['order']['status']['label'] }}
                        </span>
                        @if ($config['order']['overdue'])
                            <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">
                                {{ $config['copy']['hero']['overdue'] }}
                            </span>
                        @endif
                    </div>

                    <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $config['order']['headline'] }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $config['order']['guidance'] }}</p>

                    <dl class="mt-4 grid gap-3 text-xs sm:grid-cols-2">
                        <div>
                            <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['hero']['student'] }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $config['order']['studentName'] }}</dd>
                        </div>
                        @if ($config['order']['pagesLabel'])
                            <div>
                                <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['hero']['pages'] }}</dt>
                                <dd class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ $config['order']['pagesLabel'] }}</dd>
                            </div>
                        @endif
                        @if ($config['order']['submittedLabel'])
                            <div>
                                <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['hero']['submitted'] }}</dt>
                                <dd class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ $config['order']['submittedLabel'] }}</dd>
                            </div>
                        @endif
                        @if ($config['order']['deadlineLabel'])
                            <div>
                                <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['hero']['deadline'] }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $config['order']['deadlineLabel'] }}</dd>
                            </div>
                        @endif
                        @if ($config['order']['priceLabel'])
                            <div>
                                <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['hero']['price'] }}</dt>
                                <dd class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ $config['order']['priceLabel'] }}</dd>
                            </div>
                        @endif
                        @if ($config['order']['paidLabel'])
                            <div>
                                <dt class="font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['hero']['paid'] }}</dt>
                                <dd class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ $config['order']['paidLabel'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="{{ $cardClass }}">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['files']['title'] }}</h3>
                    <ul class="mt-4 space-y-2">
                        @forelse ($config['files'] as $file)
                            <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-2 dark:border-slate-800">
                                <span class="min-w-0 truncate text-sm text-slate-600 dark:text-slate-300">
                                    {{ $file['name'] }}
                                    @if ($file['sizeLabel'])
                                        <span class="text-xs text-slate-400">· {{ $file['sizeLabel'] }}</span>
                                    @endif
                                </span>
                                <a href="{{ $file['url'] }}" class="shrink-0 text-xs font-semibold text-primary hover:underline">{{ $config['copy']['files']['download'] }}</a>
                            </li>
                        @empty
                            <p class="text-xs text-slate-400">{{ $config['copy']['files']['empty'] }}</p>
                        @endforelse
                    </ul>
                </div>

                @if ($config['canMark'])
                    <form method="POST" action="{{ $config['form']['action'] }}" enctype="multipart/form-data" class="{{ $cardClass }} space-y-5">
                        @csrf

                        <div>
                            <x-input-label for="marked_files" :value="$config['copy']['form']['filesLabel']" />
                            <input id="marked_files" name="marked_files[]" type="file" multiple required accept="{{ $config['form']['accept'] }}"
                                   class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-300">
                            <p class="mt-2 text-xs text-slate-400">{{ $config['copy']['form']['filesHint'] }}</p>
                            @foreach ($config['form']['errors']['marked_files'] as $message)
                                <p class="mt-2 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @endforeach
                        </div>

                        <div>
                            <x-input-label for="feedback" :value="$config['copy']['form']['feedbackLabel']" />
                            <textarea id="feedback" name="feedback" rows="5" maxlength="{{ $config['form']['maxFeedback'] }}"
                                      class="mt-1 block w-full {{ $fieldClasses }}">{{ $config['form']['feedback'] }}</textarea>
                            @foreach ($config['form']['errors']['feedback'] as $message)
                                <p class="mt-2 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @endforeach
                        </div>

                        @if ($config['rubric']['hasCriteria'])
                            <div>
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $config['copy']['studio']['rubric']['title'] }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $config['copy']['studio']['rubric']['hint'] }}</p>

                                <div class="mt-2 space-y-2">
                                    @foreach ($config['rubric']['criteria'] as $index => $criterion)
                                        <div class="flex items-center gap-3">
                                            <input type="hidden" name="rubric_scores[{{ $index }}][key]" value="{{ $criterion['key'] }}">
                                            <input type="number" name="rubric_scores[{{ $index }}][score]" min="0" max="{{ $criterion['max'] }}" step="0.5"
                                                   value="{{ $criterion['scoreLabel'] }}"
                                                   class="block w-24 {{ $fieldClasses }}">
                                            <span class="text-sm text-slate-500 dark:text-slate-400">
                                                {{ $criterion['title'] }} <span class="text-xs text-slate-400">/ {{ $criterion['maxLabel'] }}</span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div>
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $config['copy']['form']['marksLabel'] }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $config['copy']['form']['marksHint'] }}</p>

                                <div class="mt-2 space-y-2">
                                    @foreach ($config['form']['marks'] as $index => $row)
                                        <div class="flex items-center gap-2">
                                            <input type="text" name="marks[{{ $index }}][label]" value="{{ $row['label'] }}" maxlength="120"
                                                   placeholder="{{ $config['copy']['form']['labelPlaceholder'] }}"
                                                   class="block w-2/5 {{ $fieldClasses }}">
                                            <input type="text" name="marks[{{ $index }}][value]" value="{{ $row['value'] }}" maxlength="120"
                                                   placeholder="{{ $config['copy']['form']['valuePlaceholder'] }}"
                                                   class="block w-2/5 {{ $fieldClasses }}">
                                        </div>
                                    @endforeach
                                </div>

                                @foreach ($config['form']['errors']['marks'] as $message)
                                    <p class="mt-2 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @endforeach
                            </div>
                        @endif

                        @if ($config['form']['errors']['order'])
                            <p class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                                {{ $config['form']['errors']['order'] }}
                            </p>
                        @endif

                        <div class="flex justify-end border-t border-slate-200 pt-5 dark:border-slate-800">
                            <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-primary/90">
                                {{ $config['copy']['form']['submit'] }}
                            </button>
                        </div>
                    </form>
                @endif

                @if ($config['delivery'])
                    <div class="{{ $cardClass }}">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['delivery']['title'] }}</h3>
                        @if ($config['delivery']['returnedLabel'])
                            <p class="mt-1 text-xs text-slate-400">{{ $config['delivery']['returnedLabel'] }}</p>
                        @endif

                        @if ($config['delivery']['feedback'])
                            <div class="mt-4 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-600 dark:bg-slate-900/60 dark:text-slate-300">{{ $config['delivery']['feedback'] }}</div>
                        @endif

                        @if ($config['delivery']['marks'] !== [])
                            <dl class="mt-4 divide-y divide-slate-200/80 text-sm dark:divide-slate-800">
                                @foreach ($config['delivery']['marks'] as $mark)
                                    <div class="flex items-center justify-between gap-3 py-2">
                                        <dt class="text-slate-500 dark:text-slate-400">{{ $mark['label'] }}</dt>
                                        <dd class="font-semibold text-slate-700 dark:text-slate-200">{{ $mark['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        <ul class="mt-4 space-y-2">
                            @foreach ($config['delivery']['files'] as $file)
                                <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-2 dark:border-slate-800">
                                    <span class="min-w-0 truncate text-sm text-slate-600 dark:text-slate-300">{{ $file['name'] }}</span>
                                    <a href="{{ $file['url'] }}" class="shrink-0 text-xs font-semibold text-primary hover:underline">{{ $config['copy']['files']['download'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($config['dispute'])
                    <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                        <p class="font-semibold">{{ $config['dispute']['title'] }}</p>
                        <p class="mt-1 whitespace-pre-line">{{ $config['dispute']['reason'] }}</p>
                    </div>
                @endif
            </div>
        </noscript>
    </div>

    @vite('resources/js/teacher-marking-show/main.js')
</x-app-layout>
