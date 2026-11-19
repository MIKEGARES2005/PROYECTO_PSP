<?php

namespace App\Providers;

use App\Models\Notificacion;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ViewFacade::composer('components.docente-layout', function (View $view): void {
            $user = auth()->user();
            $isTeacher = $user?->tipo_usuario === 'docente';

            $view->with([
                'docenteNotificaciones' => $isTeacher
                    ? Notificacion::query()
                        ->where('user_id', $user->id)
                        ->latest('created_at')
                        ->take(5)
                        ->get(['id', 'tipo', 'titulo', 'mensaje', 'url', 'leida_en', 'created_at'])
                    : collect(),
                'docenteNotificacionesNoLeidas' => $isTeacher
                    ? Notificacion::query()
                        ->where('user_id', $user->id)
                        ->whereNull('leida_en')
                        ->count()
                    : 0,
            ]);
        });
    }
}
