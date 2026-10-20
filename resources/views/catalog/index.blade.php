@php
    // The public subject catalog is rendered by resources/js/public. Blade owns
    // the route map and the display strings, then hands them over as a JSON
    // island — the same island the sticky explorer fetches per level/grade.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $copy = $config['copy'];
    $levelCards = $config['levels'];
    $gradeCards = $config['grades'];
    $subjectGroups = $config['subjectGroups'];
@endphp

<x-public-page
    :title="$copy['title']"
    :description="__('Browse the Sri Lankan syllabus subject by subject — primary, O/L and A/L — and open a grade to see its subjects and lessons.')"
    :og-image="asset('images/hero/hero-students-1200.jpg')"
    :vite="['resources/css/app.css', 'resources/js/public/main.js']"
>
    <script type="application/json" id="public-subjects-config">@json($config, $jsonFlags)</script>

    <div id="public-subjects-app"></div>

    {{-- Fallback for crawlers and visitors without JavaScript. --}}
    <noscript>
        <div style="max-width:44rem;margin:0 auto;padding:3rem 1.5rem;font-family:system-ui,sans-serif;line-height:1.6">
            <h1>{{ $copy['title'] }}</h1>
            <p>{{ $copy['intro'] }}</p>

            @if (empty($levelCards))
                <p>{{ $copy['catalogEmpty'] }}</p>
            @else
                <h2>{{ $copy['levelStep'] }}</h2>
                <ul>
                    @foreach ($levelCards as $levelOption)
                        <li><a href="{{ $levelOption['url'] }}">{{ $levelOption['icon'] }} {{ $levelOption['name'] }}</a></li>
                    @endforeach
                </ul>

                @if ($config['level'])
                    <h2>{{ $copy['gradeStep'] }}</h2>
                    <ul>
                        @foreach ($gradeCards as $gradeCard)
                            <li><a href="{{ $gradeCard['url'] }}">{{ $gradeCard['label'] }}</a></li>
                        @endforeach
                    </ul>
                @endif

                @if ($config['grade'])
                    <h2>{{ $copy['subjectStep'] }}</h2>
                    <ul>
                        @foreach ($subjectGroups as $group)
                            @foreach ($group['subjects'] as $subject)
                                <li><a href="{{ $subject['url'] }}">{{ $subject['icon'] }} {{ $subject['name'] }}</a> — {{ $subject['lessonLabel'] }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                @endif
            @endif

            <p>
                <a href="{{ $config['urls']['teachers'] }}">{{ $copy['navTeachers'] }}</a>
                <span aria-hidden="true"> · </span>
                <a href="{{ $config['urls']['contact'] }}">{{ $copy['navContact'] }}</a>
            </p>
        </div>
    </noscript>
</x-public-page>
