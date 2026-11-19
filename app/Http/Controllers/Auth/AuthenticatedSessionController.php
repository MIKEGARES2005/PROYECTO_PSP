<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\BitacoraService;
use App\Services\EstudiantePortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(EstudiantePortalService $estudiantePortal): View
    {
        $connectionStatus = $estudiantePortal->connectionStatus();
        $loginLockoutUntil = null;
        $lockoutSessionKey = 'login_lockout_username_staff';
        $lockoutUntilSessionKey = 'login_lockout_until_staff';
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

        return view('auth.login', [
            'estudiantePortalUrl' => $connectionStatus['available'] ? $connectionStatus['portalUrl'] : null,
            'estudianteQrUrl' => $connectionStatus['available'] ? $connectionStatus['qrCodeUrl'] : null,
            'loginLockoutUntil' => $loginLockoutUntil,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        return $this->authenticateAndRedirect($request);
    }

    private function authenticateAndRedirect(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        if ($user?->tipo_usuario === 'docente' && $user->debe_cambiar_password) {
            $ultimaNotificacionInicial = $user->notificaciones()
                ->whereIn('tipo', ['solicitud_aprobada', 'recuperacion_aprobada'])
                ->latest('created_at')
                ->latest('id')
                ->first();

            if ($ultimaNotificacionInicial?->tipo === 'recuperacion_aprobada') {
                return redirect()->route('dashboard')
                    ->with('teacher_welcome', true)
                    ->with('staff_login_welcome', true)
                    ->with('teacher_recovery_welcome', true);
            }

            return redirect()->route('dashboard')
                ->with('teacher_welcome', true)
                ->with('staff_login_welcome', true);
        }

        if ($user && $user->debe_cambiar_password) {
            return redirect()->route('profile.edit')
                ->with('staff_login_welcome', true)
                ->with('warning', 'Debe actualizar su contraseña temporal antes de continuar.');
        }

        if ($user?->tipo_usuario === 'estudiante') {
            return redirect()->route('estudiante.dashboard');
        }

        return redirect()->route('dashboard')
            ->with('staff_login_welcome', true);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $rutaLogin = $user?->tipo_usuario === 'estudiante' ? 'estudiante.login' : 'login';

        if ($user) {
            BitacoraService::registrar('logout', 'users', $user->id, null, null, null, $user);
            $user->setRememberToken(null);
            $user->save();
        }

        Auth::guard('web')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route($rutaLogin)
            ->with('logged_out_portal', $user?->tipo_usuario === 'estudiante' ? 'student' : 'staff');
    }
}
