<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EstudianteLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EstudianteLoginController extends Controller
{
    public function create(): View
    {
        $loginLockoutUntil = null;
        $lockoutSessionKey = 'login_lockout_username_estudiante';
        $lockoutUntilSessionKey = 'login_lockout_until_estudiante';
        $username = old('username') ?: session($lockoutSessionKey);
        $sessionLockoutUntil = (int) session($lockoutUntilSessionKey, 0);

        if (is_string($username) && $username !== '') {
            $user = User::query()->where('username', $username)->first(['bloqueado_hasta']);

            if ($user?->bloqueado_hasta?->isFuture()) {
                $loginLockoutUntil = max($user->bloqueado_hasta->timestamp, $sessionLockoutUntil);
                session()->put($lockoutSessionKey, $username);
                session()->put($lockoutUntilSessionKey, $loginLockoutUntil);
            } elseif ($sessionLockoutUntil > now()->timestamp) {
                $loginLockoutUntil = $sessionLockoutUntil;
                session()->put($lockoutSessionKey, $username);
            } else {
                session()->forget([$lockoutSessionKey, $lockoutUntilSessionKey]);
            }
        }

        return view('auth.login-estudiante', compact('loginLockoutUntil'));
    }

    public function store(EstudianteLoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::guard('student')->user();

        if ($user && $user->debe_cambiar_password) {
            $ultimaNotificacionInicial = $user->notificaciones()
                ->whereIn('tipo', ['solicitud_aprobada', 'recuperacion_aprobada'])
                ->latest('created_at')
                ->latest('id')
                ->first();

            if ($ultimaNotificacionInicial?->tipo === 'recuperacion_aprobada') {
                return redirect()->route('estudiante.dashboard')
                    ->with('student_welcome', true)
                    ->with('student_recovery_welcome', true)
                    ->with('warning', 'Recuperación aprobada. Actualiza tu usuario y contraseña temporal; tus preguntas de seguridad se conservan y no necesitas registrarlas de nuevo.');
            }

            return redirect()->route('estudiante.dashboard')
                ->with('student_welcome', true)
                ->with('warning', 'Solicitud aceptada. Cambia tu usuario y contraseña temporal y registra tus preguntas de seguridad en el panel de tu perfil.');
        }

        return redirect()->route('estudiante.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::guard('student')->user();

        if ($user) {
            $user->setRememberToken(null);
            $user->save();
        }

        Auth::guard('student')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('estudiante.login')
            ->with('logged_out_portal', 'student');
    }
}
