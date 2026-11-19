<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class UserAnonymizationService
{
    public function anonymize(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $usuario = User::onlyTrashed()
                ->with([
                    'docente' => fn ($query) => $query->withTrashed(),
                    'estudiante' => fn ($query) => $query->withTrashed(),
                ])
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            if ($usuario->anonymized_at !== null) {
                return;
            }

            $rutas = collect([
                $usuario->docente?->foto_personal,
                $usuario->estudiante?->foto_personal,
            ])->filter(fn ($ruta) => $ruta !== null);

            $rutas = $rutas->map(function ($ruta) use ($usuario): string {
                if (! is_string($ruta) || trim($ruta) === '') {
                    throw new RuntimeException(
                        "La ruta del archivo personal del usuario {$usuario->id} no es válida.",
                    );
                }

                return str_replace('\\', '/', $ruta);
            })->unique();

            foreach ($rutas as $ruta) {
                if (! $this->isSafePublicPath($ruta)) {
                    throw new RuntimeException(
                        "La ruta del archivo personal del usuario {$usuario->id} no es válida.",
                    );
                }
            }

            $disco = Storage::disk('public');

            foreach ($rutas as $ruta) {
                if ($disco->exists($ruta) && ! $disco->delete($ruta)) {
                    throw new RuntimeException(
                        "No se pudo eliminar el archivo personal del usuario {$usuario->id}.",
                    );
                }
            }

            DB::table('sessions')->where('user_id', $usuario->id)->delete();
            DB::table('preguntas_seguridad')->where('user_id', $usuario->id)->delete();
            DB::table('notificaciones')->where('user_id', $usuario->id)->delete();

            if ($usuario->estudiante !== null) {
                $guardado = $usuario->estudiante->forceFill([
                    'nombres' => 'Usuario eliminado',
                    'apellido_paterno' => 'Cuenta',
                    'apellido_materno' => null,
                    'matricula' => null,
                    'ci' => 'ELIM-'.$usuario->id,
                    'ci_extension' => null,
                    'telefono' => null,
                    'correo_institucional' => null,
                    'correo_personal' => null,
                    'direccion' => null,
                    'foto_personal' => null,
                    'estado' => 'retirado',
                ])->save();

                if (! $guardado) {
                    throw new RuntimeException("No se pudo anonimizar el perfil del usuario {$usuario->id}.");
                }
            }

            if ($usuario->docente !== null) {
                $guardado = $usuario->docente->forceFill([
                    'nombres' => 'Usuario eliminado',
                    'apellido_paterno' => 'Cuenta',
                    'apellido_materno' => null,
                    'ci' => 'ELIM-'.$usuario->id,
                    'ci_extension' => null,
                    'telefono' => null,
                    'correo_personal' => null,
                    'cargo' => 'Eliminado',
                    'especialidad' => null,
                    'nivel_academico' => null,
                    'fecha_ingreso' => null,
                    'foto_personal' => null,
                    'estado' => 'inactivo',
                ])->save();

                if (! $guardado) {
                    throw new RuntimeException("No se pudo anonimizar el perfil del usuario {$usuario->id}.");
                }
            }

            $usernameAnterior = $usuario->username;
            $guardado = $usuario->forceFill([
                'username' => $this->availableAnonymizedUsername($usuario),
                'password' => Str::random(64),
                'activo' => false,
                'debe_cambiar_password' => false,
                'remember_token' => null,
                'anonymized_at' => now(),
            ])->save();

            if (! $guardado) {
                throw new RuntimeException("No se pudo anonimizar la cuenta del usuario {$usuario->id}.");
            }

            BitacoraService::registrar(
                'anonimizar_perfil_usuario',
                'users',
                $usuario->id,
                ['username' => $usernameAnterior, 'tipo_usuario' => $usuario->tipo_usuario],
                ['anonimizado_en' => $usuario->anonymized_at?->toDateTimeString()],
                null,
                $actor,
            );
        });
    }

    private function availableAnonymizedUsername(User $user): string
    {
        $baseUsername = 'eliminado_'.$user->id;
        $username = $baseUsername;

        while (User::withTrashed()
            ->where('username', $username)
            ->where('id', '<>', $user->id)
            ->exists()) {
            $username = $baseUsername.'_'.Str::lower(Str::random(8));
        }

        return $username;
    }

    private function isSafePublicPath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && preg_match('/^[A-Za-z]:/', $path) !== 1
            && preg_match('~(^|/)\.\.?(/|$)~', $path) !== 1
            && ! str_contains($path, "\0");
    }
}
