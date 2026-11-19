@php
    $categoriasPorId = $categorias->mapWithKeys(fn ($categoria): array => [
        $categoria->id => [
            'tipo' => $categoria->tipo_registro,
            'grupo' => $categoria->grupo,
            'nombre' => $categoria->nombre,
        ],
    ]);
    $compatibilidadesSeleccionadas = collect(old(
        'tipos_equipo_compatibles',
        $compatibilidadesSeleccionadas ?? [],
    ))->values()->all();
    $compatibilidadesPorComplemento = $complementos->mapWithKeys(fn ($complemento): array => [
        $complemento->id => $complemento->tipos_equipo_compatibles ?? [],
    ]);
    $seleccionados = collect(old('complementos', $complementosSeleccionados ?? []))
        ->map(fn ($id): string => (string) $id)
        ->values()
        ->all();
    $categoriaSeleccionada = (string) old('categoria_id', $activo->categoria_id ?? request('categoria'));
    $codigoInicial = $activo->codigo ?: ($codigosSugeridos[$categoriaSeleccionada] ?? '');
    $grupoInicial = $categorias->firstWhere('id', (int) $categoriaSeleccionada)?->grupo ?? '';
    $nombreCategoriaInicial = strtolower($categorias->firstWhere('id', (int) $categoriaSeleccionada)?->nombre ?? '');
    $tipoEquipoSugerido = $grupoInicial === 'red'
        ? 'router'
        : (str_contains($nombreCategoriaInicial, 'tablet')
            ? 'tablet'
            : (str_contains($nombreCategoriaInicial, 'mini pc') ? 'mini_pc' : 'cpu'));
    $tipoEquipoInicial = old('tipo_equipo', $activo->equipo?->tipo_equipo ?? $tipoEquipoSugerido);
@endphp
<div
    x-data="{
        tipo: @js($categoriasPorId[(int) $categoriaSeleccionada]['tipo'] ?? $tipoRegistro),
        grupo: @js($grupoInicial),
        categoriasPorId: @js($categoriasPorId),
        codigosPorCategoria: @js($codigosSugeridos),
        codigoActual: @js($codigoInicial),
        codigoBloqueado: @js($activo->exists),
        cantidad: 1,
        tipoEquipo: @js($tipoEquipoInicial),
        compatibilidadesPorComplemento: @js($compatibilidadesPorComplemento),
        complementos: @js($seleccionados),
        compatibilidades: @js($compatibilidadesSeleccionadas),
        complementosValidados: false,
        cambiarCategoria(id) {
            const categoria = this.categoriasPorId[id] || { tipo: 'activo', grupo: '', nombre: '' };
            this.tipo = categoria.tipo;
            this.grupo = categoria.grupo;
            this.codigoActual = this.codigosPorCategoria[id] || 'Se asignará al guardar';
            if (categoria.tipo === 'equipo') {
                const nombre = categoria.nombre.toLocaleLowerCase();
                const tipoSugerido = categoria.grupo === 'red'
                    ? 'router'
                    : (nombre.includes('tablet') ? 'tablet' : (nombre.includes('mini pc') ? 'mini_pc' : 'cpu'));
                this.cambiarTipoEquipo(tipoSugerido);
            }
        },
        cambiarTipoEquipo(tipo) {
            this.tipoEquipo = tipo;
            this.complementos = this.complementos.filter(id =>
                (this.compatibilidadesPorComplemento[id] || []).includes(tipo)
            );
        },
        sugerirCompatibilidades(tipo) {
            const normalizado = tipo.toLocaleLowerCase();
            if (normalizado.includes('monitor') || normalizado.includes('pantalla')) {
                this.compatibilidades = ['cpu', 'mini_pc'];
            } else if (normalizado.includes('apoyador') || normalizado.includes('soporte')) {
                this.compatibilidades = ['tablet'];
            } else if (['teclado', 'mouse', 'raton', 'ratón'].some(nombre => normalizado.includes(nombre))) {
                if (normalizado.includes('tablet')) this.compatibilidades = ['tablet'];
                else if (normalizado.includes('mini pc')) this.compatibilidades = ['mini_pc'];
                else if (normalizado.includes(' pc') || normalizado.startsWith('pc') || normalizado.includes('computadora')) this.compatibilidades = ['cpu'];
                else this.compatibilidades = ['cpu', 'tablet', 'mini_pc'];
            }
        },
        codigoFinal() {
            if (this.codigoBloqueado || !this.codigoActual || this.cantidad < 2) {
                return this.codigoActual || 'Selecciona una categoría';
            }
            const coincidencia = this.codigoActual.match(/^(.*-)(\d+)$/);
            return coincidencia
                ? coincidencia[1] + String(Number(coincidencia[2]) + this.cantidad - 1).padStart(coincidencia[2].length, '0')
                : this.codigoActual;
        }
    }"
    class="space-y-6"
>
    <section class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
        <div class="mb-6">
            <h2 class="text-lg font-bold text-slate-800">Datos del artículo</h2>
            <p class="mt-1 text-sm text-slate-500">Identificación, estado y ubicación del activo patrimonial.</p>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="categoria_id" class="mb-2 block text-sm font-bold text-slate-700">Categoría</label>
            <select id="categoria_id" name="categoria_id" required @change="cambiarCategoria($event.target.value)" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                <option value="">Selecciona qué tipo de artículo registrar</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected((string) old('categoria_id', $activo->categoria_id ?? request('categoria')) === (string) $categoria->id)>
                        {{ $categoria->nombre }} · {{ match ($categoria->grupo) {'equipo' => 'Equipo informático', 'complemento' => 'Complemento', 'electronico' => 'Electrónico', 'mobiliario' => 'Mobiliario', 'red' => 'Red', default => 'Otro'} }}
                    </option>
                @endforeach
            </select>
            @error('categoria_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="codigo_preview" class="mb-2 block text-sm font-bold text-slate-700">Código patrimonial</label>
            <input id="codigo_preview" type="text" :value="codigoFinal()" readonly class="w-full rounded-xl border-slate-300 bg-slate-100 px-4 py-3 text-sm font-black text-[#272F7A]">
            <p class="mt-2 text-xs text-slate-500">Se reserva el correlativo al guardar. Si registras varias unidades, cada una tendrá código propio.</p>
        </div>
        @if (! $activo->exists)
            <div>
                <label for="cantidad" class="mb-2 block text-sm font-bold text-slate-700">Cantidad de unidades</label>
                <input id="cantidad" name="cantidad" type="number" min="1" max="100" required x-model.number="cantidad" value="{{ old('cantidad', 1) }}" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                @error('cantidad')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                <p class="mt-2 text-xs text-slate-500">Se crearán registros separados, con un código individual para cada unidad (máximo 100 por operación).</p>
            </div>
        @endif
        <div>
            <label for="nombre" class="mb-2 block text-sm font-bold text-slate-700">Nombre / tipo del artículo</label>
            <input id="nombre" name="nombre" value="{{ old('nombre', $activo->nombre) }}" required maxlength="150" placeholder="Ej. Silla universitaria, Mini PC..." class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
            @error('nombre')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div x-show="grupo !== 'mobiliario'" x-cloak>
            <label for="marca" class="mb-2 block text-sm font-bold text-slate-700">Marca (opcional)</label>
            <input id="marca" name="marca" value="{{ old('marca', $activo->marca) }}" maxlength="100" :disabled="grupo === 'mobiliario'" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
        </div>
        <div x-show="grupo !== 'mobiliario'" x-cloak>
            <label for="modelo" class="mb-2 block text-sm font-bold text-slate-700">Modelo (opcional)</label>
            <input id="modelo" name="modelo" value="{{ old('modelo', $activo->modelo) }}" maxlength="100" :disabled="grupo === 'mobiliario'" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
        </div>
        <div x-show="grupo !== 'mobiliario'" x-cloak>
            <label for="numero_serie" class="mb-2 block text-sm font-bold text-slate-700">Número de serie (opcional)</label>
            <input id="numero_serie" name="numero_serie" value="{{ old('numero_serie', $activo->numero_serie) }}" maxlength="100" :disabled="grupo === 'mobiliario'" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
            @error('numero_serie')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="estado_uso" class="mb-2 block text-sm font-bold text-slate-700">Estado de uso</label>
            <select id="estado_uso" name="estado_uso" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                @foreach (['disponible', 'reservado', 'asignado', 'prestado', 'mantenimiento', 'baja', 'danado'] as $estado)
                    <option value="{{ $estado }}" @selected(old('estado_uso', $activo->estado_uso ?: 'disponible') === $estado)>{{ ucfirst($estado) }}</option>
                @endforeach
            </select>
            @error('estado_uso')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="ubicacion" class="mb-2 block text-sm font-bold text-slate-700">Ubicación</label>
            <input id="ubicacion" name="ubicacion" value="{{ old('ubicacion', $activo->ubicacion) }}" maxlength="150" placeholder="Ej. Laboratorio 2" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
        </div>
        <div>
            <label for="fecha_adquisicion" class="mb-2 block text-sm font-bold text-slate-700">Fecha de adquisición</label>
            <input id="fecha_adquisicion" name="fecha_adquisicion" type="date" value="{{ old('fecha_adquisicion', $activo->fecha_adquisicion?->format('Y-m-d')) }}" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
        </div>
        <div>
            <label for="fecha_baja" class="mb-2 block text-sm font-bold text-slate-700">Fecha de baja (si aplica)</label>
            <input id="fecha_baja" name="fecha_baja" type="date" value="{{ old('fecha_baja', $activo->fecha_baja?->format('Y-m-d')) }}" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
            @error('fecha_baja')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        </section>
    </div>

    <div x-show="tipo === 'equipo'" x-cloak class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-5">
        <h2 class="font-black text-[#272F7A]">Ficha técnica del equipo</h2>
        <p class="mt-1 text-sm text-slate-600">Computadoras, tablets, mini PCs y equipos de red se registran aquí para su seguimiento.</p>
        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <div>
                <label for="identificador" class="mb-2 block text-sm font-bold text-slate-700">Identificador (opcional)</label>
                <input id="identificador" name="identificador" value="{{ old('identificador', $activo->equipo?->identificador) }}" maxlength="50" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                <p class="mt-1 text-xs text-slate-500">Si se deja vacío, se utilizará el código patrimonial.</p>
            </div>
            <div>
                <label for="tipo_equipo" class="mb-2 block text-sm font-bold text-slate-700">Tipo de equipo</label>
                <select id="tipo_equipo" name="tipo_equipo" required :disabled="tipo !== 'equipo'" x-model="tipoEquipo" @change="cambiarTipoEquipo($event.target.value)" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                    <option value="cpu" :disabled="grupo === 'red'" @selected($tipoEquipoInicial === 'cpu')>Computadora (PC)</option>
                    <option value="tablet" :disabled="grupo === 'red'" @selected($tipoEquipoInicial === 'tablet')>Tablet</option>
                    <option value="mini_pc" :disabled="grupo === 'red'" @selected($tipoEquipoInicial === 'mini_pc')>Mini PC</option>
                    <option value="router" :disabled="grupo !== 'red'" @selected($tipoEquipoInicial === 'router')>Router</option>
                    <option value="wifi" :disabled="grupo !== 'red'" @selected($tipoEquipoInicial === 'wifi')>Equipo Wi-Fi</option>
                </select>
            </div>
            <div>
                <label for="modo_uso" class="mb-2 block text-sm font-bold text-slate-700">Modo de uso</label>
                <select id="modo_uso" name="modo_uso" required :disabled="tipo !== 'equipo'" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                    <option value="individual" @selected(old('modo_uso', $activo->equipo?->modo_uso ?? 'individual') === 'individual')>Individual</option>
                    <option value="grupal" @selected(old('modo_uso', $activo->equipo?->modo_uso) === 'grupal')>Grupal</option>
                </select>
            </div>
            <div>
                <label for="mac" class="mb-2 block text-sm font-bold text-slate-700">Dirección MAC (opcional)</label>
                <input id="mac" name="mac" value="{{ old('mac', $activo->equipo?->mac) }}" maxlength="17" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                @error('mac')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-3 text-sm font-bold text-slate-700">
                <input type="checkbox" name="es_servidor" value="1" @checked(old('es_servidor', $activo->equipo?->es_servidor)) class="rounded border-slate-300 text-[#272F7A]">
                Es el servidor único del sistema
            </label>
        </div>
        <div x-show="cantidad <= 1" class="mt-5 border-t border-indigo-200 pt-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-sm font-black text-slate-800">Periféricos y complementos compatibles</h3>
                    <p class="mt-1 text-xs text-slate-500">Los teclados y mouse pueden ser para PC, mini PC o tablet; monitores para PC y mini PC; apoyadores para tablet.</p>
                </div>
                <span class="rounded-full bg-white px-3 py-1.5 text-xs font-black text-[#272F7A] ring-1 ring-indigo-200"><span x-text="complementos.length"></span> seleccionados</span>
            </div>
            <div class="mt-4 space-y-4">
                @forelse ($complementos->groupBy(fn ($complemento) => $complemento->activo?->categoria?->id) as $complementosDeCategoria)
                    @php
                        $categoriaComplemento = $complementosDeCategoria->first()->activo?->categoria;
                    @endphp
                    <div class="rounded-xl border border-cyan-100 bg-white p-4">
                        <h4 class="mb-3 text-xs font-black uppercase tracking-wider text-cyan-800">{{ $categoriaComplemento?->nombre ?? 'Categoría sin nombre' }}</h4>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($complementosDeCategoria as $complemento)
                                <label
                                    x-show="(compatibilidadesPorComplemento['{{ $complemento->id }}'] || []).includes(tipoEquipo)"
                                    class="cursor-pointer rounded-xl border border-slate-200 bg-slate-50 p-3 transition hover:border-cyan-300 hover:bg-cyan-50/50"
                                    :class="complementos.includes('{{ $complemento->id }}') ? 'border-cyan-500 bg-cyan-50 ring-2 ring-cyan-100' : ''"
                                >
                                    <input type="checkbox" name="complementos[]" value="{{ $complemento->id }}" x-model="complementos" @change="complementosValidados = false" class="sr-only">
                                    <span class="block text-sm font-bold text-slate-800">{{ $complemento->activo?->nombre ?? $complemento->tipo_complemento }}</span>
                                    <span class="mt-1 block text-[11px] font-semibold text-slate-500">{{ $complemento->activo?->codigo }} · {{ $complemento->tipo_complemento }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500">No hay periféricos y complementos disponibles en categorías activas. Registra un artículo de tipo complemento para poder vincularlo.</p>
                @endforelse
            </div>
            @error('complementos')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        @if (! $activo->exists)
            <p x-show="cantidad > 1" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Las unidades se crearán sin periféricos ni complementos vinculados. Luego podrás vincular cada accesorio al equipo correspondiente.</p>
        @endif
    </div>

    <div x-show="tipo === 'complemento'" x-cloak class="rounded-2xl border border-cyan-100 bg-cyan-50/60 p-5">
        <h2 class="font-black text-cyan-800">Ficha técnica del periférico o complemento</h2>
        <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <div>
                <label for="tipo_complemento" class="mb-2 block text-sm font-bold text-slate-700">Tipo de periférico o complemento</label>
                <input id="tipo_complemento" name="tipo_complemento" value="{{ old('tipo_complemento', $activo->complemento?->tipo_complemento) }}" maxlength="60" placeholder="Teclado, mouse, monitor, apoyador..." @change="sugerirCompatibilidades($event.target.value)" required :disabled="tipo !== 'complemento'" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                @error('tipo_complemento')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="estado_fisico" class="mb-2 block text-sm font-bold text-slate-700">Estado físico</label>
                <select id="estado_fisico" name="estado_fisico" required :disabled="tipo !== 'complemento'" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                    <option value="bueno" @selected(old('estado_fisico', $activo->complemento?->estado_fisico ?? 'bueno') === 'bueno')>Bueno</option>
                    <option value="regular" @selected(old('estado_fisico', $activo->complemento?->estado_fisico) === 'regular')>Regular</option>
                    <option value="danado" @selected(old('estado_fisico', $activo->complemento?->estado_fisico) === 'danado')>Dañado</option>
                </select>
            </div>
        </div>
        <fieldset class="mt-5">
            <legend class="text-sm font-bold text-slate-700">Compatible con</legend>
            <p class="mt-1 text-xs text-slate-500">Al escribir monitor, teclado, mouse o apoyador se sugerirán los equipos correspondientes; puedes ajustar la selección.</p>
            <div class="mt-3 flex flex-wrap gap-3">
                @foreach (['cpu' => 'PC', 'tablet' => 'Tablet', 'mini_pc' => 'Mini PC', 'router' => 'Router', 'wifi' => 'Wi-Fi'] as $valor => $etiqueta)
                    <label class="flex items-center gap-2 rounded-xl border border-cyan-100 bg-white px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="tipos_equipo_compatibles[]" value="{{ $valor }}" x-model="compatibilidades" class="rounded border-slate-300 text-cyan-700">
                        {{ $etiqueta }}
                    </label>
                @endforeach
            </div>
            @error('tipos_equipo_compatibles')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('tipos_equipo_compatibles.*')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </fieldset>
    </div>

    <section class="space-y-5 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
        <div>
            <label for="observaciones" class="mb-2 block text-sm font-bold text-slate-700">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="4" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">{{ old('observaciones', $activo->observaciones) }}</textarea>
        </div>
        <div>
            <label for="imagen_referencial" class="mb-2 block text-sm font-bold text-slate-700">Fotografía referencial (opcional)</label>
            <input id="imagen_referencial" name="imagen_referencial" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-bold file:text-[#272F7A]">
            <p class="mt-1 text-xs text-slate-500">JPG, PNG o WebP; máximo 2 MB. Al crear varias unidades se guardará una copia para cada una.</p>
        </div>
    </section>
</div>
