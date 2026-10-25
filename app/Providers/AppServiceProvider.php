<?php

namespace App\Providers;

use App\Contracts\MeetingProvider;
use App\Contracts\PaymentGateway;
use App\Contracts\SmsGateway;
use App\Models\EducationLevel;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\SubjectBasket;
use App\Services\ActivityLogger;
use App\Services\CatalogService;
use App\Services\ConversationService;
use App\Services\Meetings\DailyMeetingProvider;
use App\Services\Meetings\FakeMeetingProvider;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\RazorpayGateway;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsLenzGateway;
use App\Support\PhoneNumber;
use App\Support\SmsLenzConfig;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request: controllers describe an action and the
        // audit middleware picks that description up on the way out.
        $this->app->singleton(ActivityLogger::class);

        $this->app->bind(PaymentGateway::class, function () {
            return match (config('studylikepro.payments.gateway')) {
                'razorpay' => new RazorpayGateway,
                default => new FakePaymentGateway,
            };
        });

        $this->app->bind(MeetingProvider::class, function () {
            return match (config('studylikepro.meeting.provider')) {
                'daily' => new DailyMeetingProvider(
                    config('services.daily.key'),
                    (string) config('services.daily.base_url'),
                    (int) config('services.daily.timeout'),
                ),
                default => new FakeMeetingProvider,
            };
        });

        $this->app->bind(SmsGateway::class, function () {
            $smsLenz = app(SmsLenzConfig::class);
            $credentials = $smsLenz->credentials();

            return match ($smsLenz->provider()) {
                'smslenz' => new SmsLenzGateway(
                    $credentials['user_id'],
                    $credentials['api_key'],
                    $credentials['sender_id'],
                    $credentials['base_url'],
                    $credentials['timeout'],
                ),
                default => new FakeSmsGateway,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->flushCatalogCacheOnWrites();

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            $view->with('userTheme', $user?->theme);

            if ($user !== null) {
                // The header bell and the sidebar chat badge.
                $view->with([
                    'recentNotifications' => $user->notifications()->limit(5)->get(),
                    'unreadNotificationCount' => $user->unreadNotifications()->count(),
                    'unreadMessageCount' => app(ConversationService::class)->unreadCountFor($user),
                ]);
            }
        });
    }

    /**
     * Named limits applied on the routes. Everything that writes, uploads or
     * costs money is bounded; signed-in users are counted per account so one
     * noisy network cannot lock everybody out.
     */
    private function configureRateLimiting(): void
    {
        $limit = fn (string $key): int => (int) config("studylikepro.throttle.{$key}");

        RateLimiter::for('login', function (Request $request) use ($limit) {
            $login = trim((string) $request->input('login'));

            // "076 982 88 74" and "+94 76 982 8874" must share one bucket.
            $identifier = str_contains($login, '@')
                ? Str::lower($login)
                : (PhoneNumber::normalize($login) ?? $login);

            return Limit::perMinute($limit('login'))->by($identifier.'|'.$request->ip());
        });

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute($limit('register'))->by($request->ip()));

        // Passcodes are bucketed on the number being verified (in canonical
        // digits) plus the caller, so "076 982 88 74" and "+94 76 982 8874"
        // share one budget and one noisy network cannot exhaust another's. The
        // verify screen has no mobile field, so it reads the pending number
        // from the session.
        $otpBucket = function (Request $request): string {
            $mobile = PhoneNumber::normalize((string) $request->input('mobile'))
                ?? PhoneNumber::normalize((string) data_get($request->session()->get('register.pending'), 'mobile'));

            return ($mobile ?? 'none').'|'.$request->ip();
        };

        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute($limit('otp'))->by($otpBucket($request)));

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute($limit('otp-verify'))->by($otpBucket($request)));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute($limit('uploads'))
            ->by($request->user()?->id ?: $request->ip()));

        // The studio autosaves every few seconds while a paper is open.
        RateLimiter::for('marking-draft', fn (Request $request) => Limit::perMinute($limit('marking-draft'))
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute($limit('messages'))
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute($limit('checkout'))
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute($limit('contact'))->by($request->ip()));

        // The admin connection test sends a real SMS; one admin cannot loop it.
        RateLimiter::for('sms-test', fn (Request $request) => Limit::perMinute($limit('sms-test'))
            ->by($request->user()?->id ?: $request->ip()));

        // Providers retry, so the ceiling is high — it only exists to stop a
        // flood from filling the queue.
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute($limit('webhooks'))->by($request->ip()));
    }

    /**
     * Any write to the catalog drops the cached copy the public pages read.
     */
    private function flushCatalogCacheOnWrites(): void
    {
        $flush = fn () => app(CatalogService::class)->flush();

        foreach ([EducationLevel::class, SubjectBasket::class, Subject::class, Lesson::class] as $model) {
            $model::saved($flush);
            $model::deleted($flush);
        }
    }
}
