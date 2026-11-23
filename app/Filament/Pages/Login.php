<?php

namespace App\Filament\Pages;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Components\Attributes\Exposed;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/** Login memakai USERNAME (bukan email). Akun nonaktif ditolak langsung pada tahap kredensial. */
class Login extends BaseLogin
{
    #[Exposed]
    public function authenticate(): ?LoginResponse
    {
        if (Filament::getMultiFactorAuthenticationProviders() !== []) {
            return parent::authenticate();
        }

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();
        $credentials = $this->getCredentialsFromFormData($data);
        $remember = (bool) ($data['remember'] ?? false);
        $timeboxDuration = (int) config('auth.timebox_duration', 200_000);

        // Panel tidak memakai MFA: verifikasi kredensial & izin panel cukup 1 kali (menghindari 2x Bcrypt).
        app(Timebox::class)->call(function (Timebox $timebox) use ($authGuard, $credentials, $remember): void {
            if (! $authGuard->attemptWhen(
                $credentials,
                fn (Authenticatable $user): bool => $this->isUserAllowedToAccessPanel($user),
                $remember,
            )) {
                $this->throwFailureValidationException();
            }

            $timebox->returnEarly();
        }, $timeboxDuration);

        session()->regenerate();

        return app(LoginResponse::class);
    }
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
            'is_active' => true,
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
