<?php

namespace App\Console\Commands;

use App\Models\CodigoQr;
use App\Models\Notificacion;
use App\Models\ReporteGenerado;
use App\Models\User;
use App\Services\UserAnonymizationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('system:purge-expired-data')]
#[Description('Elimina datos temporales vencidos según la política de retención del sistema.')]
class PurgeExpiredSystemData extends Command
{
    public function handle(UserAnonymizationService $anonymization): int
    {
        $now = now();
        $sessionCutoff = $now->copy()
            ->subMinutes((int) config('session.lifetime', 120))
            ->timestamp;
        $cacheCutoff = $now->timestamp;
        $notificationCutoff = $now->copy()->subDays((int) config('retention.notifications.read_after_days', 5));
        $userDeletionCutoff = $now->copy()->subDays((int) config('retention.users.deletion_grace_period_days', 2));

        $deleted = [
            'sessions' => $this->deleteExpiredRows(
                DB::table('sessions')->where('last_activity', '<=', $sessionCutoff),
                'id',
            ),
            'cache' => $this->deleteExpiredRows(
                DB::table('cache')->where('expiration', '<=', $cacheCutoff),
                'key',
            ),
            'cache_locks' => $this->deleteExpiredRows(
                DB::table('cache_locks')->where('expiration', '<=', $cacheCutoff),
                'key',
            ),
            'notificaciones' => $this->deleteExpiredRows(
                Notificacion::query()
                    ->whereNotNull('leida_en')
                    ->where('leida_en', '<=', $notificationCutoff)
                    ->getQuery(),
                'id',
            ),
            'usuarios_anonimizados' => $this->anonymizeExpiredUsers($userDeletionCutoff, $anonymization),
            'reportes_generados' => $this->deleteExpiredReports($now->copy()->subDays(30)),
            'codigos_qr' => $this->deleteExpiredQrCodes($now->copy()->subDays(30)),
        ];

        foreach ($deleted as $table => $count) {
            $resultado = $table === 'usuarios_anonimizados'
                ? "{$table}: {$count} perfil(es) anonimizados."
                : "{$table}: {$count} registro(s) eliminados.";

            $this->line($resultado);
        }

        $this->warn('bitacora: requiere archivado y validacion del reporte del periodo antes de purgarse.');

        $this->warn('heartbeats: sin depuración automática hasta validar el proceso de generación de resúmenes horarios.');

        return self::SUCCESS;
    }

    private function deleteExpiredRows(QueryBuilder $query, string $keyColumn): int
    {
        $deleted = 0;

        while (true) {
            $ids = (clone $query)
                ->orderBy($keyColumn)
                ->limit(500)
                ->pluck($keyColumn);

            if ($ids->isEmpty()) {
                return $deleted;
            }

            $deleted += (clone $query)->whereIn($keyColumn, $ids)->delete();
        }
    }

    private function anonymizeExpiredUsers(
        \DateTimeInterface $deletionCutoff,
        UserAnonymizationService $anonymization,
    ): int {
        $anonymized = 0;

        User::onlyTrashed()
            ->whereNull('anonymized_at')
            ->where('deleted_at', '<=', $deletionCutoff)
            ->chunkById(100, function (EloquentCollection $usuarios) use (&$anonymized, $anonymization): void {
                foreach ($usuarios as $usuario) {
                    $anonymization->anonymize($usuario);
                    $anonymized++;
                }
            });

        return $anonymized;
    }

    private function deleteExpiredReports(\DateTimeInterface $expirationCutoff): int
    {
        $deleted = 0;

        ReporteGenerado::query()
            ->whereNotNull('vence_en')
            ->where('vence_en', '<=', $expirationCutoff)
            ->chunkById(100, function (EloquentCollection $reportes) use (&$deleted): void {
                foreach ($reportes as $reporte) {
                    $ruta = str_replace('\\', '/', $reporte->ruta);

                    if (! $this->isSafePublicPath($ruta)) {
                        throw new RuntimeException(
                            "La ruta del reporte {$reporte->id} no es una ruta relativa válida del disco public.",
                        );
                    }

                    $disco = Storage::disk('public');

                    if ($disco->exists($ruta) && ! $disco->delete($ruta)) {
                        throw new RuntimeException("No se pudo eliminar el archivo del reporte {$reporte->id}.");
                    }

                    if (! $reporte->delete()) {
                        throw new RuntimeException("No se pudo eliminar el registro del reporte {$reporte->id}.");
                    }

                    $deleted++;
                }
            });

        return $deleted;
    }

    private function deleteExpiredQrCodes(\DateTimeInterface $expirationCutoff): int
    {
        $deleted = 0;

        CodigoQr::query()
            ->where(function (EloquentBuilder $query) use ($expirationCutoff): void {
                $query->where('expira_en', '<=', $expirationCutoff)
                    ->orWhere(function (EloquentBuilder $query) use ($expirationCutoff): void {
                        $query->where('activo', false)
                            ->where('updated_at', '<=', $expirationCutoff);
                    });
            })
            ->chunkById(500, function (EloquentCollection $codigos) use (&$deleted): void {
                foreach ($codigos as $codigo) {
                    if (! $codigo->delete()) {
                        throw new RuntimeException("No se pudo eliminar el código QR {$codigo->id}.");
                    }

                    $deleted++;
                }
            });

        return $deleted;
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
