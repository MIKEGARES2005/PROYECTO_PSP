<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoriaController extends Controller
{
    public function create(): View
    {
        return view('inventario.categorias.create', [
            'categoria' => new Categoria(['tipo_registro' => 'activo']),
        ]);
    }

    public function manage(Request $request): View
    {
        $datos = $request->validate([
            'categoria' => ['nullable', 'integer', Rule::exists('categorias', 'id')],
        ]);
        $categorias = Categoria::query()->orderBy('nombre')->get();
        $categoria = isset($datos['categoria'])
            ? $categorias->firstWhere('id', $datos['categoria'])
            : null;

        return view('inventario.categorias.edit', compact('categoria', 'categorias'));
    }

    public function store(Request $request): RedirectResponse
    {
        Categoria::create($this->validatedData($request));

        return redirect()->route('inventario.index')->with('success', 'Categoria creada correctamente.');
    }

    public function edit(Categoria $categoria): View
    {
        $categorias = Categoria::query()->orderBy('nombre')->get();

        return view('inventario.categorias.edit', compact('categoria', 'categorias'));
    }

    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $datos = $this->validatedData($request, $categoria);
        $hayActivos = DB::table('activos')->where('categoria_id', $categoria->id)->exists();

        if ($hayActivos && $datos['tipo_registro'] !== $categoria->tipo_registro) {
            return back()
                ->withInput()
                ->withErrors(['tipo_registro' => 'No puedes cambiar el tipo de ficha mientras la categoria tenga activos registrados.']);
        }

        $categoria->update($datos);

        return redirect()->route('inventario.index')->with('success', 'Categoria actualizada correctamente.');
    }

    public function destroy(Categoria $categoria): RedirectResponse
    {
        if (DB::table('activos')->where('categoria_id', $categoria->id)->exists()) {
            return back()->withErrors([
                'categoria' => 'No se puede eliminar una categoria que conserva activos o historial.',
            ]);
        }

        $categoria->delete();

        return redirect()->route('inventario.index')->with('success', 'Categoria eliminada correctamente.');
    }

    private function validatedData(Request $request, ?Categoria $categoria = null): array
    {
        $request->merge([
            'prefijo_codigo' => strtoupper(trim((string) $request->input('prefijo_codigo'))),
        ]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias', 'nombre')->ignore($categoria?->id)],
            'grupo' => ['required', Rule::in(['mobiliario', 'red', 'electronico', 'equipo', 'complemento', 'otro'])],
            'tipo_registro' => ['required', Rule::in(['activo', 'equipo', 'complemento'])],
            'estado' => $categoria === null ? ['prohibited'] : ['required', Rule::in(['activa', 'inactiva'])],
            'prefijo_codigo' => ['required', 'regex:/^[A-Z0-9]{2,8}$/', Rule::unique('categorias', 'prefijo_codigo')->ignore($categoria?->id)],
        ]);
        $tipoEsperado = match ($datos['grupo']) {
            'mobiliario', 'electronico', 'otro' => 'activo',
            'equipo' => 'equipo',
            'complemento' => 'complemento',
            'red' => null,
        };

        if (($tipoEsperado !== null && $datos['tipo_registro'] !== $tipoEsperado)
            || ($datos['grupo'] === 'red' && $datos['tipo_registro'] === 'complemento')) {
            throw ValidationException::withMessages([
                'tipo_registro' => 'El tipo de ficha no corresponde a la clasificación seleccionada.',
            ]);
        }

        return $datos + ($categoria === null ? ['estado' => 'activa'] : []);
    }
}
