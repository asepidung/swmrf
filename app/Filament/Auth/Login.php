<?php

namespace App\Filament\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BasePage;
use Illuminate\Support\Facades\RateLimiter;

class Login extends BasePage
{
    /**
     * Batas percobaan masuk dihitung PER USERNAME (5 per menit), bukan per alamat internet seperti bawaan Filament.
     * Satu kantor memakai satu alamat yang sama; dengan batas per alamat, 5 percobaan siapa pun mengunci semua
     * orang yang sedang masuk bersamaan (pelatihan 28 Oktober 2026). Penjaga kasar per alamat tetap ada, tetapi
     * longgar (120 per menit) -- hanya menahan serangan massal.
     */
    public function authenticate(): ?LoginResponse
    {
        $ip = (string) request()->ip();
        $username = mb_strtolower(trim((string) ($this->data['username'] ?? '')));
        $userKey = 'login-user:'.sha1($username.'|'.$ip);
        $ipKey = 'login-ip:'.$ip;

        foreach ([[$userKey, 5], [$ipKey, 120]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $this->getRateLimitedNotification(new TooManyRequestsException(static::class, 'authenticate', $ip, RateLimiter::availableIn($key)))?->send();

                return null;
            }
        }

        RateLimiter::hit($userKey, 60);
        RateLimiter::hit($ipKey, 60);

        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        RateLimiter::clear($userKey);

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label(__('Username'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            // Spasi di depan/belakang (keyboard HP) tidak boleh membuat username dianggap salah.
            'username' => trim((string) $data['username']),
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'data.username' => __('Incorrect password or username.'),
        ]);
    }
}
