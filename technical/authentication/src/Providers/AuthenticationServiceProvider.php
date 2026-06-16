<?php

namespace Technical\Authentication\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Fortify;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider;
use Xefi\LaravelOSDD\LayerServiceProvider;

class AuthenticationServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        $this->bootRouting();
        $this->registerMicrosoftProvider();
        $this->configurePasswordReset();
        $this->configureEmailVerification();
    }

    public function register(): void
    {
        // Headless Fortify configuration (views=false, api guard, 2FA feature).
        $this->overrideConfigFrom(__DIR__.'/../../config/fortify.php', 'fortify');

        // Merge the Microsoft Socialite credentials into the services config.
        $this->overrideConfigFrom(__DIR__.'/../../config/services.php', 'services');

        // Fortify's own HTTP controllers are session + StatefulGuard based. This
        // layer ships stateless replacements, so Fortify's routes are disabled;
        // its guard-free Actions (2FA, recovery codes) are still used directly.
        Fortify::ignoreRoutes();
    }

    private function bootRouting(): void
    {
        $this->withRouting(
            api: __DIR__.'/../../routes/api.php',
        );
    }

    private function registerMicrosoftProvider(): void
    {
        // The socialiteproviders/microsoft driver registers itself via an event.
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', Provider::class);
        });
    }

    private function configurePasswordReset(): void
    {
        // The reset link is consumed by the front-end (stateless), not a Blade route.
        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            $base = rtrim((string) config('app.frontend_url'), '/');
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return "$base/reset-password?token={$token}&email={$email}";
        });
    }

    private function configureEmailVerification(): void
    {
        // The verify route is model-bound on {user}, so the signed URL must
        // carry a `user` parameter (Laravel's default notification emits `id`).
        VerifyEmail::createUrlUsing(function ($notifiable): string {
            return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                'user' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]);
        });
    }
}
