<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use Illuminate\Contracts\View\View;

class PublicEquipoFichaController extends Controller
{
    public function show(Equipo $equipo): View
    {
        $equipo->load([
            'activo.categoria',
            'complementos' => fn ($query) => $query
                ->wherePivotNull('fecha_fin')
                ->with('activo'),
        ]);

        return view('public.equipo-ficha', compact('equipo'));
    }
}
