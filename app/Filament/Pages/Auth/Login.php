<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            throw ValidationException::withMessages([
                'data.email' => trans('filament-panels::pages/auth/login.messages.throttled', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]),
            ]);
        }

        $data = $this->form->getState();

        $userModel = config('auth.providers.users.model');

        $user = $userModel::query()
            ->where('email', $data['email'] ?? null)
            ->first();

        if (! $user || ! Hash::check($data['password'] ?? '', $user->password)) {
            throw ValidationException::withMessages([
                'data.email' => 'Las credenciales ingresadas no son correctas.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'data.email' => 'Tu usuario se encuentra desactivado. Comunícate con el administrador del negocio.',
            ]);
        }

        if (! $user->isSuperAdmin() && ! $user->tenant?->is_active) {
            throw ValidationException::withMessages([
                'data.email' => 'El acceso a tu negocio está suspendido o pendiente de pago. Comunícate con soporte para reactivar el servicio.',
            ]);
        }

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            throw ValidationException::withMessages([
                'data.email' => 'Las credenciales ingresadas no son correctas.',
            ]);
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
