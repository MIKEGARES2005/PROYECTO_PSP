<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use Illuminate\Contracts\View\View;

class PublicActivoFichaController extends Controller
{
    public function show(Activo $activo): View
    {
        $activo->load('categoria');

        return view('public.activo-ficha', compact('activo'));
    }
}
