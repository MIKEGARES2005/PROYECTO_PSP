<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\Categoria;
use App\Models\Complemento;
use App\Models\Equipo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActivoController extends Controller
{
    public function create(Request $request): View
    {
        $categoria = Categoria::query()->where('estado', 'activa')->find($request->integer('categoria'));
        $categorias = Categoria::query()->where('estado', 'activa')->orderBy('nombre')->get();

        return view('inventario.activos.create', [
            'activo' => new Activo,
            'categorias' => $categorias,
            'tipoRegistro' => $this->tipoPorCategoria($categoria),
            'complementos' => $this->complementosDisponibles(),
            'complementosSeleccionados' => [],
            'codigosSugeridos' => $this->codigosSugeridos($categorias),
            'compatibilidadesSeleccionadas' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validatedData($request);
        $tipo = $datos['tipo_registro'];
        $cantidad = $datos['cantidad'];
        $complementoIds = $datos['complementos'] ?? [];
        unset($datos['tipo_registro'], $datos['cantidad'], $datos['complementos']);

        DB::transaction(function () use ($request, $datos, $tipo, $cantidad, $complementoIds): void {
            $categoria = Categoria::query()
                ->where('estado', 'activa')
                ->lockForUpdate()
                ->findOrFail($datos['categoria_id']);

            for ($unidad = 0; $unidad < $cantidad; $unidad++) {
                $codigo = $this->reservarCodigoPatrimonial($categoria);
                $registro = collect($datos)->only([
                    'categoria_id',
                    'nombre',
                    'marca',
                    'modelo',
                    'numero_serie',
                    'estado_uso',
                    'ubicacion',
                    'fecha_adquisicion',
                    'fecha_baja',
                    'observaciones',
                ])->all();
                $registro['codigo'] = $codigo;

                if ($request->hasFile('imagen_referencial')) {
                    $registro['imagen_referencial'] = $request->file('imagen_referencial')->store('inventario', 'public');
                }

                $activo = Activo::create($registro);

                $ficha = collect($datos)->only([
                    'identificador',
                    'tipo_equipo',
                    'modo_uso',
                    'mac',
                    'es_servidor',
                    'tipo_complemento',
                    'tipos_equipo_compatibles',
                    'estado_fisico',
                ])->all();
                if ($tipo === 'equipo') {
                    $ficha['identificador'] = $ficha['identificador'] ?? $codigo;
                }

                $this->guardarFichaTecnica($activo, $tipo, $ficha);
                $this->actualizarComplementos($activo->fresh('equipo')->equipo, $complementoIds);
            }
        });

        return redirect()->route('inventario.index')->with(
            'success',
            $cantidad === 1 ? 'Activo registrado correctamente.' : "{$cantidad} activos registrados correctamente."
        );
    }

    public function show(Activo $activo): View
    {
        if (auth()->user()->isDocente() && ! auth()->user()->isAdministrador()
            && $activo->equipo === null
            && $activo->complemento === null) {
            abort(404);
        }

        $activo->load(['categoria', 'equipo.complementos.activo', 'complemento.equipos.activo']);
        $equiposVinculados = $activo->complemento?->equipos
            ->filter(fn (Equipo $equipo): bool => $equipo->pivot?->fecha_fin === null)
            ->values() ?? collect();
        $complementosDisponibles = $activo->equipo
            ? $this->complementosDisponibles($activo->equipo, false)
            : collect();

        return view('inventario.activos.show', compact('activo', 'complementosDisponibles', 'equiposVinculados'));
    }

    public function downloadQr(Activo $activo): Response
    {
        $activo->loadMissing(['categoria', 'equipo']);
        $etiqueta = [
            'activo' => $activo,
            'qrDataUri' => $this->qrDataUri($activo),
        ];

        return Pdf::loadView('inventario.activos.qr-pdf', $etiqueta)
            ->setPaper('a4')
            ->download('qr-inventario-'.$activo->codigo.'.pdf');
    }

    private function qrDataUri(Activo $activo): string
    {
        $fichaRuta = $activo->equipo !== null
            ? route('equipos.ficha-publica', $activo->equipo, false)
            : route('activos.ficha-publica', $activo, false);
        $fichaUrl = rtrim((string) config('app.url'), '/').$fichaRuta;
        $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&format=png&data='.urlencode($fichaUrl);

        try {
            $qrResponse = Http::withOptions([
                'verify' => config('services.http.ca_bundle') ?: true,
            ])->timeout(10)->get($qrApiUrl);
        } catch (ConnectionException) {
            abort(502, 'No se pudo conectar con el generador del código QR.');
        }

        abort_unless($qrResponse->successful(), 502, 'No se pudo generar el código QR.');
        abort_unless(str_starts_with((string) $qrResponse->header('Content-Type'), 'image/png'), 502, 'El generador devolvió una imagen QR inválida.');

        $qrPng = $qrResponse->body();
        $qrImage = strlen($qrPng) <= 1_000_000 ? @getimagesizefromstring($qrPng) : false;
        abort_unless(
            $qrImage !== false
                && $qrImage['mime'] === 'image/png'
                && $qrImage[0] === 600
                && $qrImage[1] === 600,
            502,
            'El generador devolvió una imagen QR inválida.'
        );

        return 'data:image/png;base64,'.base64_encode($qrPng);
    }

    public function attachComplemento(Request $request, Activo $activo): RedirectResponse
    {
        $equipo = $activo->equipo;
        if ($equipo === null) {
            return back()->withErrors(['activo' => 'Solo un activo registrado como equipo puede recibir complementos.']);
        }

        $datos = $request->validate([
            'complemento_id' => ['required', 'exists:complementos,id'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);
        $complemento = Complemento::query()->findOrFail($datos['complemento_id']);
        $categoriaComplemento = $complemento->activo?->categoria;

        if ($categoriaComplemento?->estado !== 'activa' || $categoriaComplemento?->tipo_registro !== 'complemento') {
            return back()->withErrors([
                'complemento_id' => 'Solo se pueden vincular complementos de categorías activas.',
            ]);
        }

        if (! in_array($equipo->tipo_equipo, $complemento->tipos_equipo_compatibles ?? [], true)) {
            return back()->withErrors([
                'complemento_id' => 'Este complemento no es compatible con el tipo de equipo seleccionado.',
            ]);
        }

        $yaVinculado = $equipo->complementos()
            ->whereKey($datos['complemento_id'])
            ->wherePivotNull('fecha_fin')
            ->exists();
        $vinculadoEnOtro = Complemento::query()
            ->whereKey($datos['complemento_id'])
            ->whereHas('equipos', fn ($query) => $query->whereNull('equipo_complemento.fecha_fin'))
            ->exists();

        if ($yaVinculado || $vinculadoEnOtro) {
            return back()->withErrors(['complemento_id' => 'Este complemento ya esta vinculado a un equipo.']);
        }

        $equipo->complementos()->attach($datos['complemento_id'], [
            'fecha_inicio' => now(),
            'observaciones' => $datos['observaciones'] ?? null,
        ]);

        return back()->with('success', 'Complemento vinculado correctamente al equipo.');
    }

    public function detachComplemento(Activo $activo, Complemento $complemento): RedirectResponse
    {
        $equipo = $activo->equipo;
        if ($equipo === null) {
            return back()->withErrors(['activo' => 'El activo seleccionado no es un equipo.']);
        }

        DB::table('equipo_complemento')
            ->where('equipo_id', $equipo->id)
            ->where('complemento_id', $complemento->id)
            ->whereNull('fecha_fin')
            ->update(['fecha_fin' => now()->addSecond(), 'updated_at' => now()]);

        return back()->with('success', 'Complemento retirado del equipo. El historial se conserva.');
    }

    public function edit(Activo $activo): View
    {
        $activo->load(['categoria', 'equipo', 'complemento']);
        $tipoRegistro = $this->tipoPorCategoria($activo->categoria);
        $categorias = Categoria::query()
            ->where('estado', 'activa')
            ->where('tipo_registro', $tipoRegistro)
            ->orderBy('nombre')
            ->get();

        return view('inventario.activos.edit', [
            'activo' => $activo,
            'categorias' => $categorias,
            'tipoRegistro' => $tipoRegistro,
            'complementos' => $this->complementosDisponibles($activo->equipo),
            'complementosSeleccionados' => $activo->equipo?->complementos()->wherePivotNull('fecha_fin')->pluck('complementos.id')->all() ?? [],
            'codigosSugeridos' => $this->codigosSugeridos($categorias),
            'compatibilidadesSeleccionadas' => $activo->complemento?->tipos_equipo_compatibles ?? [],
        ]);
    }

    public function update(Request $request, Activo $activo): RedirectResponse
    {
        $datos = $this->validatedData($request, $activo);
        $tipo = $datos['tipo_registro'];
        $complementoIds = $datos['complementos'] ?? [];
        unset($datos['tipo_registro'], $datos['cantidad'], $datos['complementos']);
        $imagenAnterior = $activo->imagen_referencial;

        DB::transaction(function () use ($request, $activo, $datos, $tipo, $complementoIds): void {
            $actualizados = $datos;
            if ($request->hasFile('imagen_referencial')) {
                $actualizados['imagen_referencial'] = $request->file('imagen_referencial')->store('inventario', 'public');
            }
            $activo->update($actualizados);
            $this->guardarFichaTecnica($activo, $tipo, $actualizados);
            $this->actualizarComplementos($activo->fresh('equipo')->equipo, $complementoIds);
        });

        if ($request->hasFile('imagen_referencial') && $imagenAnterior) {
            Storage::disk('public')->delete($imagenAnterior);
        }

        return redirect()->route('inventario.activos.show', $activo)->with('success', 'Activo actualizado correctamente.');
    }

    public function destroy(Activo $activo): RedirectResponse
    {
        $equipo = $activo->equipo;
        $complemento = $activo->complemento;
        $tieneHistorial = $activo->incidencias()->exists()
            || $equipo?->asignaciones()->exists()
            || $equipo?->diagnosticos()->exists()
            || $equipo?->heartbeats()->exists()
            || $equipo?->alertas()->exists()
            || ($equipo !== null && DB::table('solicitudes')->where('equipo_id', $equipo->id)->exists())
            || ($equipo !== null && DB::table('equipo_complemento')->where('equipo_id', $equipo->id)->exists())
            || ($complemento !== null && DB::table('asignaciones')->where('complemento_id', $complemento->id)->exists())
            || ($complemento !== null && DB::table('asignacion_checklist')->where('complemento_id', $complemento->id)->exists())
            || ($complemento !== null && DB::table('equipo_complemento')->where('complemento_id', $complemento->id)->exists());

        if ($tieneHistorial) {
            return back()->withErrors([
                'activo' => 'No se puede eliminar este activo porque conserva asignaciones, solicitudes, complementos vinculados, incidencias o historial de diagnóstico y monitoreo.',
            ]);
        }
        if ($activo->imagen_referencial) {
            Storage::disk('public')->delete($activo->imagen_referencial);
        }
        $activo->delete();

        return redirect()->route('inventario.index')->with('success', 'Activo eliminado correctamente.');
    }

    private function validatedData(Request $request, ?Activo $activo = null): array
    {
        if ($activo === null && ! $request->exists('cantidad')) {
            $request->merge(['cantidad' => 1]);
        }

        $equipoId = $activo?->equipo?->id;
        $categoria = Categoria::query()->where('estado', 'activa')->find($request->integer('categoria_id'));
        if ($categoria === null) {
            throw ValidationException::withMessages([
                'categoria_id' => 'Selecciona una categoria activa del inventario.',
            ]);
        }
        $tipo = $this->tipoPorCategoria($categoria);
        if ($activo !== null) {
            $tipoExistente = $activo->equipo ? 'equipo' : ($activo->complemento ? 'complemento' : 'activo');
            if ($tipo !== $tipoExistente) {
                throw ValidationException::withMessages([
                    'categoria_id' => 'En la edicion conserva una categoria del mismo tipo de ficha del activo.',
                ]);
            }
        }
        $reglas = [
            'categoria_id' => ['required', Rule::exists('categorias', 'id')->where(fn ($query) => $query->where('estado', 'activa'))],
            'nombre' => ['required', 'string', 'max:150'],
            'cantidad' => $activo === null ? ['required', 'integer', 'min:1', 'max:100'] : ['prohibited'],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:100', Rule::unique('activos', 'numero_serie')->ignore($activo?->id)],
            'estado_uso' => ['required', Rule::in(['disponible', 'reservado', 'asignado', 'prestado', 'mantenimiento', 'baja', 'danado'])],
            'ubicacion' => ['nullable', 'string', 'max:150'],
            'fecha_adquisicion' => ['nullable', 'date'],
            'fecha_baja' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],
            'imagen_referencial' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'complementos' => ['nullable', 'array'],
            'complementos.*' => ['integer', 'distinct', 'exists:complementos,id'],
            'mac' => ['nullable', 'string', 'max:17', Rule::unique('equipos', 'mac')->ignore($equipoId)],
            'es_servidor' => ['nullable', 'boolean'],
        ];

        if ($tipo === 'equipo') {
            $reglas += [
                'identificador' => ['nullable', 'string', 'max:50', Rule::unique('equipos', 'identificador')->ignore($equipoId)],
                'tipo_equipo' => ['required', Rule::in(['cpu', 'tablet', 'mini_pc', 'router', 'wifi'])],
                'modo_uso' => ['required', Rule::in(['individual', 'grupal'])],
            ];
        }

        if ($tipo === 'complemento') {
            $reglas += [
                'tipo_complemento' => ['required', 'string', 'max:60'],
                'tipos_equipo_compatibles' => ['required', 'array', 'min:1'],
                'tipos_equipo_compatibles.*' => ['required', Rule::in(['cpu', 'tablet', 'mini_pc', 'router', 'wifi'])],
                'estado_fisico' => ['required', Rule::in(['bueno', 'regular', 'danado'])],
            ];
        }

        $datos = $request->validate($reglas);
        $datos['tipo_registro'] = $tipo;

        if ($tipo === 'equipo') {
            $datos['es_servidor'] = $request->boolean('es_servidor');
            if ($activo?->equipo !== null && blank($datos['identificador'])) {
                $datos['identificador'] = $activo->equipo->identificador;
            }

            $tiposPermitidos = $categoria->grupo === 'red'
                ? ['router', 'wifi']
                : ['cpu', 'tablet', 'mini_pc'];
            if (! in_array($datos['tipo_equipo'], $tiposPermitidos, true)) {
                throw ValidationException::withMessages([
                    'tipo_equipo' => 'El tipo de equipo no corresponde a la clasificación de la categoría.',
                ]);
            }
        }

        if ($categoria->grupo === 'mobiliario') {
            $datos['marca'] = null;
            $datos['modelo'] = null;
            $datos['numero_serie'] = null;
        }

        if ($datos['estado_uso'] === 'baja' && empty($datos['fecha_baja'])) {
            throw ValidationException::withMessages([
                'fecha_baja' => 'Un activo dado de baja debe tener fecha de baja.',
            ]);
        }

        if ($activo === null) {
            $datos['cantidad'] = (int) $datos['cantidad'];

            if ($datos['cantidad'] > 1
                && (filled($datos['numero_serie'] ?? null)
                    || filled($datos['mac'] ?? null)
                    || filled($datos['identificador'] ?? null)
                    || ! empty($datos['complementos'] ?? [])
                    || ($datos['es_servidor'] ?? false))) {
                throw ValidationException::withMessages([
                    'cantidad' => 'Para crear varias unidades deja vacíos serie, MAC, identificador y complementos; registra esos datos por unidad después.',
                ]);
            }
        }

        return $datos;
    }

    private function complementosDisponibles(?Equipo $equipo = null, bool $incluirVinculadoAlEquipo = true): Collection
    {
        return Complemento::query()
            ->with('activo.categoria')
            ->whereHas('activo.categoria', fn ($query) => $query
                ->where('estado', 'activa')
                ->where('tipo_registro', 'complemento'))
            ->when(
                $equipo !== null,
                fn ($query) => $query->whereJsonContains('tipos_equipo_compatibles', $equipo->tipo_equipo)
            )
            ->whereDoesntHave('equipos', function ($query) use ($equipo, $incluirVinculadoAlEquipo): void {
                $query->whereNull('equipo_complemento.fecha_fin');
                if ($equipo !== null && $incluirVinculadoAlEquipo) {
                    $query->where('equipos.id', '!=', $equipo->id);
                }
            })
            ->join('activos', 'complementos.activo_id', '=', 'activos.id')
            ->join('categorias', 'activos.categoria_id', '=', 'categorias.id')
            ->orderBy('categorias.nombre')
            ->orderBy('activos.nombre')
            ->select('complementos.*')
            ->get();
    }

    private function actualizarComplementos(?Equipo $equipo, array $complementoIds): void
    {
        if ($equipo === null) {
            return;
        }

        $seleccionados = collect($complementoIds)->map(fn ($id): int => (int) $id)->unique()->values();
        $complementos = Complemento::query()
            ->whereIn('complementos.id', $seleccionados)
            ->whereHas('activo.categoria', fn ($query) => $query
                ->where('estado', 'activa')
                ->where('tipo_registro', 'complemento'))
            ->get();

        if ($complementos->count() !== $seleccionados->count()) {
            throw ValidationException::withMessages([
                'complementos' => 'Selecciona solo complementos pertenecientes a categorías activas.',
            ]);
        }

        $incompatibles = $complementos
            ->filter(fn (Complemento $complemento): bool => ! in_array(
                $equipo->tipo_equipo,
                $complemento->tipos_equipo_compatibles ?? [],
                true
            ));

        if ($incompatibles->isNotEmpty()) {
            throw ValidationException::withMessages([
                'complementos' => 'Uno o más complementos no son compatibles con el tipo de equipo seleccionado.',
            ]);
        }

        $ocupados = Complemento::query()
            ->whereIn('id', $seleccionados)
            ->whereHas('equipos', function ($query) use ($equipo): void {
                $query->whereNull('equipo_complemento.fecha_fin')->where('equipos.id', '!=', $equipo->id);
            })
            ->pluck('id');

        if ($ocupados->isNotEmpty()) {
            throw ValidationException::withMessages([
                'complementos' => 'Uno o mas complementos ya estan vinculados a otro equipo.',
            ]);
        }

        $actuales = $equipo->complementos()->wherePivotNull('fecha_fin')->pluck('complementos.id');
        $ahora = now();

        DB::table('equipo_complemento')
            ->where('equipo_id', $equipo->id)
            ->whereNull('fecha_fin')
            ->whereNotIn('complemento_id', $seleccionados->all())
            ->update(['fecha_fin' => $ahora->copy()->addSecond(), 'updated_at' => $ahora]);

        foreach ($seleccionados->diff($actuales) as $complementoId) {
            DB::table('equipo_complemento')->insert([
                'equipo_id' => $equipo->id,
                'complemento_id' => $complementoId,
                'fecha_inicio' => $ahora,
                'observaciones' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function guardarFichaTecnica(Activo $activo, string $tipo, array $datos): void
    {
        if ($tipo === 'equipo') {
            $activo->equipo()->updateOrCreate([], collect($datos)->only([
                'identificador',
                'tipo_equipo',
                'modo_uso',
                'mac',
                'es_servidor',
            ])->all());
        }
        if ($tipo === 'complemento') {
            $activo->complemento()->updateOrCreate([], collect($datos)->only([
                'tipo_complemento',
                'tipos_equipo_compatibles',
                'estado_fisico',
                'observaciones',
                'observaciones',
            ])->all());
        }
    }

    private function tipoPorCategoria(?Categoria $categoria): string
    {
        return $categoria?->tipo_registro ?? 'activo';
    }

    /**
     * @param  EloquentCollection<int, Categoria>  $categorias
     * @return array<int, string>
     */
    private function codigosSugeridos(EloquentCollection $categorias): array
    {
        return $categorias
            ->filter(fn (Categoria $categoria): bool => filled($categoria->prefijo_codigo))
            ->mapWithKeys(fn (Categoria $categoria): array => [
                $categoria->id => $this->formatearCodigo(
                    $categoria->prefijo_codigo,
                    $categoria->codigo_secuencia + 1
                ),
            ])
            ->all();
    }

    private function reservarCodigoPatrimonial(Categoria $categoria): string
    {
        if (blank($categoria->prefijo_codigo)) {
            $categoria->prefijo_codigo = $this->prefijoAlternativo($categoria);
        }

        $categoria->codigo_secuencia++;
        $categoria->save();

        return $this->formatearCodigo($categoria->prefijo_codigo, $categoria->codigo_secuencia);
    }

    private function prefijoAlternativo(Categoria $categoria): string
    {
        $iniciales = collect(preg_split('/[^A-Za-z0-9]+/', $categoria->nombre, -1, PREG_SPLIT_NO_EMPTY))
            ->filter()
            ->map(fn (string $palabra): string => strtoupper(substr($palabra, 0, 1)))
            ->implode('');
        $base = substr($iniciales ?: 'CAT', 0, 5);
        $prefijo = $base;
        $sufijo = 1;

        while (Categoria::query()
            ->where('prefijo_codigo', $prefijo)
            ->where('id', '!=', $categoria->id)
            ->exists()) {
            $marca = (string) $sufijo++;
            $prefijo = substr($base, 0, 8 - strlen($marca)).$marca;
        }

        return $prefijo;
    }

    private function formatearCodigo(string $prefijo, int $correlativo): string
    {
        return $prefijo.'-'.str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT);
    }
}
