<?php

namespace App\Providers;

use App\Contracts\LessonClassifier;
use App\Contracts\MeetingProvider;
use App\Contracts\PaymentGateway;
use App\Models\EducationLevel;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\SubjectBasket;
use App\Services\ActivityLogger;
use App\Services\AI\OpenAILessonClassifier;
use App\Services\CatalogService;
use App\Services\ConversationService;
use App\Services\Meetings\DailyMeetingProvider;
use App\Services\Meetings\FakeMeetingProvider;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\RazorpayGateway;
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

        $this->app->bind(LessonClassifier::class, OpenAILessonClassifier::class);

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

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute($limit('login'))
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute($limit('register'))->by($request->ip()));

        RateLimiter::for('requests', fn (Request $request) => Limit::perMinute($limit('requests'))
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute($limit('uploads'))
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute($limit('messages'))
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute($limit('checkout'))
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute($limit('contact'))->by($request->ip()));

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
