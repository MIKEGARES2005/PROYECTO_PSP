<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\Categoria;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $activosQuery = Activo::query();
        $categoriasQuery = Categoria::query()->where('estado', 'activa');

        $esDocenteSinRolAdministrativo = $usuario->isDocente() && ! $usuario->isAdministrador();

        if ($esDocenteSinRolAdministrativo) {
            $activosQuery->where(function ($query): void {
                $query->whereHas('equipo')
                    ->orWhereHas('complemento');
            });
            $categoriasQuery->whereHas('activos', function ($query): void {
                $query->where(function ($activoQuery): void {
                    $activoQuery->whereHas('equipo')
                        ->orWhereHas('complemento');
                });
            });
        }

        $grupo = $request->string('grupo')->toString();
        $tipo = $request->string('tipo')->toString();
        $categoriaId = $request->integer('categoria');
        $categorias = $categoriasQuery->withCount([
            'activos' => function ($query) use ($esDocenteSinRolAdministrativo): void {
                if ($esDocenteSinRolAdministrativo) {
                    $query->where(function ($activoQuery): void {
                        $activoQuery->whereHas('equipo')
                            ->orWhereHas('complemento');
                    });
                }
            },
        ])->orderBy('nombre')->get();
        $activos = (clone $activosQuery)
            ->with(['categoria', 'equipo', 'complemento'])
            ->when($grupo !== '', fn ($query) => $query->whereHas('categoria', fn ($categoriaQuery) => $categoriaQuery->where('grupo', $grupo)))
            ->when($tipo === 'equipo', fn ($query) => $query->whereHas('equipo'))
            ->when($tipo === 'complemento', fn ($query) => $query->whereHas('complemento'))
            ->when($tipo === 'mueble', fn ($query) => $query->whereHas('categoria', fn ($categoriaQuery) => $categoriaQuery->where('grupo', 'mobiliario')))
            ->when($categoriaId > 0, fn ($query) => $query->where('categoria_id', $categoriaId))
            ->when($request->filled('buscar'), function ($query) use ($request): void {
                $buscar = trim($request->string('buscar')->toString());
                $query->where(function ($activoQuery) use ($buscar): void {
                    $activoQuery->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%")
                        ->orWhere('marca', 'like', "%{$buscar}%")
                        ->orWhere('modelo', 'like', "%{$buscar}%")
                        ->orWhere('numero_serie', 'like', "%{$buscar}%")
                        ->orWhereHas('categoria', fn ($categoriaQuery) => $categoriaQuery->where('nombre', 'like', "%{$buscar}%"));
                });
            })
            ->latest()->paginate(12)->withQueryString();
        $resumen = [
            'equipos' => (clone $activosQuery)->whereHas('equipo')->count(),
            'complementos' => (clone $activosQuery)->whereHas('complemento')->count(),
            'muebles' => $esDocenteSinRolAdministrativo ? 0 : (clone $activosQuery)->whereHas('categoria', fn ($query) => $query->where('grupo', 'mobiliario'))->count(),
            'total' => (clone $activosQuery)->count(),
        ];

        return view('inventario.index', compact('activos', 'categorias', 'grupo', 'tipo', 'categoriaId', 'resumen'));
    }
}
