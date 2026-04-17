<?php

namespace App\Providers;

use App\Interfaces\OptionRepositoryInterface;
use App\Repositories\OptionRepository;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register repository bindings
        $this->app->bind(OptionRepositoryInterface::class, OptionRepository::class);

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {

            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);

            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = config('app.frontend_url', 'http://localhost:5173')
                . '/auth/reset-password?token=' . $token
                . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

            return (new MailMessage)
                ->subject('Reset Your TeamSync Password')
                ->greeting('Hello!')
                ->line('You are receiving this email because we received a password reset request for your TeamSync account.')
                ->action('Reset Password', $url)
                ->line('This password reset link will expire in 60 minutes.')
                ->line('If you did not request a password reset, no further action is required.');
        });

        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            return (new MailMessage)
                ->subject('Verify Your TeamSync Email Address')
                ->greeting('Welcome to TeamSync!')
                ->line('Please verify your email address to secure your account and unlock all features.')
                ->action('Verify Email Address', $verificationUrl)
                ->line('This verification link will expire in 60 minutes.')
                ->line('If you did not create an account, no further action is required.');
        });
    }
}
