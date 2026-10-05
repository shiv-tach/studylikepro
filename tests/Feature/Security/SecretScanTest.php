<?php

use Illuminate\Support\Facades\File;

/**
 * Nothing that unlocks a provider account or a customer record may live in the
 * repository. These patterns are the ones that leak in practice.
 */
it('keeps provider credentials out of the tracked files', function () {
    $patterns = [
        '/sk-[A-Za-z0-9_\-]{20,}/' => 'OpenAI-style secret key',
        '/rzp_live_[A-Za-z0-9]+/' => 'Razorpay live key',
        '/AKIA[0-9A-Z]{16}/' => 'AWS access key id',
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----/' => 'private key',
        '/xox[baprs]-[A-Za-z0-9\-]{10,}/' => 'Slack token',
        '/AIza[0-9A-Za-z\-_]{35}/' => 'Google API key',
    ];

    $roots = ['app', 'config', 'database', 'resources', 'routes', 'tests', 'bootstrap', 'public'];
    $paths = collect([base_path('.env.example'), base_path('composer.json'), base_path('package.json')]);

    foreach ($roots as $root) {
        if (File::isDirectory(base_path($root))) {
            foreach (File::allFiles(base_path($root)) as $file) {
                $paths->push($file->getPathname());
            }
        }
    }

    $findings = [];

    foreach ($paths as $path) {
        // Broken or linked files (public/storage) are not part of the source.
        if (! is_file($path) || is_link($path)) {
            continue;
        }

        $contents = File::get($path);

        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $contents) === 1) {
                $findings[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).' → '.$label;
            }
        }
    }

    expect($findings)->toBe([]);
});

it('keeps the local environment file out of version control', function () {
    $ignore = File::get(base_path('.gitignore'));

    expect($ignore)->toContain('.env')
        ->and(File::exists(base_path('.env')))->toBeTrue();
});

it('reads every integration credential from the environment', function () {
    $services = File::get(base_path('config/services.php'));

    // Anything that looks like a hardcoded value rather than an env() call.
    expect($services)->not->toMatch("/'(key|secret|token|password)'\s*=>\s*'(?!')[^']{8,}'/i");
});
