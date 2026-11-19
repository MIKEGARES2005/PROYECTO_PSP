<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\BitacoraService;
use App\Support\DireccionEstudiante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $this->authenticatedUser($request);

        return view('profile.edit', [
            'user' => $user->load(['docente', 'estudiante']),
        ]);
    }

    public function photo(Request $request): BinaryFileResponse
    {
        $user = $this->authenticatedUser($request)->load(['docente', 'estudiante']);
        $perfil = $user->docente ?? $user->estudiante;
        $rutaFoto = $perfil?->foto_personal;

        abort_unless($rutaFoto !== null && Storage::disk('public')->exists($rutaFoto), 404);

        return response()->file(Storage::disk('public')->path($rutaFoto), [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request)->load(['docente', 'estudiante']);
        $validated = $request->validated();
        $nuevaFoto = $request->file('foto_personal');
        $usernameAnterior = $user->username;
        $perfil = $user->docente ?? $user->estudiante;
        $fotoAnterior = $perfil?->foto_personal;
        $datosPerfil = [];

        if ($user->docente !== null) {
            $datosPerfil = Arr::only($validated, [
                'nombres', 'apellido_paterno', 'apellido_materno',
                'ci', 'ci_extension', 'telefono', 'correo_personal',
                'cargo', 'especialidad', 'nivel_academico', 'fecha_ingreso',
            ]);
        } elseif ($user->estudiante !== null) {
            $datosPerfil = Arr::only($validated, [
                'telefono', 'correo_personal', 'direccion',
            ]);
            $datosPerfil['direccion'] = DireccionEstudiante::desdeDatos(
                $validated,
                $validated['direccion'] ?? null,
            );
        }

        $rutaFoto = null;
        if ($nuevaFoto !== null) {
            $directorioFotos = $user->docente !== null ? 'fotos/docentes' : 'fotos/estudiantes';
            $rutaFoto = $nuevaFoto->store($directorioFotos, 'public');

            if ($rutaFoto === false) {
                return back()->withErrors(['foto_personal' => 'No se pudo guardar la fotografía.']);
            }

            $datosPerfil['foto_personal'] = $rutaFoto;
        }

        $usernameNuevo = $validated['username'] ?? $usernameAnterior;
        $antesPerfil = $perfil?->only(array_keys($datosPerfil)) ?? [];

        try {
            DB::transaction(function () use (
                $datosPerfil,
                $perfil,
                $validated,
                $user,
                $usernameAnterior,
                $usernameNuevo,
                $antesPerfil
            ): void {
                if ($perfil !== null && $datosPerfil !== []) {
                    $perfil->update($datosPerfil);

                    BitacoraService::registrar(
                        $user->docente !== null ? 'actualizar_perfil_docente' : 'actualizar_perfil_estudiante',
                        $user->docente !== null ? 'docentes' : 'estudiantes',
                        $perfil->id,
                        $antesPerfil,
                        $datosPerfil
                    );
                }

                if ($usernameNuevo !== $usernameAnterior) {
                    $user->update(['username' => $usernameNuevo]);

                    BitacoraService::registrar(
                        'actualizar_username',
                        'users',
                        $user->id,
                        ['username' => $usernameAnterior],
                        ['username' => $user->username],
                    );
                }

                if (filled($validated['password'] ?? null)) {
                    $user->forceFill([
                        'password' => Hash::make($validated['password']),
                        'debe_cambiar_password' => false,
                        'intentos_fallidos' => 0,
                        'bloqueos_consecutivos' => 0,
                        'bloqueado_hasta' => null,
                    ])->save();

                    BitacoraService::registrar('cambio_password', 'users', $user->id);
                }
            });
        } catch (Throwable $exception) {
            if ($rutaFoto !== null) {
                Storage::disk('public')->delete($rutaFoto);
            }

            throw $exception;
        }

        if ($rutaFoto !== null && $fotoAnterior !== null) {
            Storage::disk('public')->delete($fotoAnterior);
        }

        $route = $this->isStudentRoute($request) ? 'estudiante.profile.edit' : 'profile.edit';

        return Redirect::route($route)->with('status', 'profile-updated');
    }

    private function authenticatedUser(Request $request): User
    {
        return $this->isStudentRoute($request)
            ? $request->user('student')
            : $request->user();
    }

    private function isStudentRoute(Request $request): bool
    {
        return $request->routeIs('estudiante.*');
    }
}
