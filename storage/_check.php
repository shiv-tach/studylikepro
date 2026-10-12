<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$action = $argv[1] ?? 'show';
$email = 'teacher@gmail.com';
$user = App\Models\User::where('email', $email)->firstOrFail();

if ($action === 'show') {
    echo $user->password.PHP_EOL;
} elseif ($action === 'set') {
    $user->password = Illuminate\Support\Facades\Hash::make('studylikepro-test-123');
    $user->save();
    echo "set\n";
} elseif ($action === 'restore') {
    $user->password = $argv[2];
    $user->save();
    echo "restored\n";
}
