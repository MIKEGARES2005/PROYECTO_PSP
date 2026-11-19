<x-admin-shell>
    <main class="min-h-screen bg-gradient-to-br from-indigo-50/70 via-white to-blue-50/70">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-[#4959A6]">Gestión de inventario / Incidencias</p>
                    <h1 class="mt-1 text-3xl font-bold text-slate-800">Incidencias</h1>
                    <p class="mt-2 text-sm text-slate-500">Da seguimiento a reportes manuales y automáticos relacionados con los equipos y sus apps.</p>
                </div>
                <a href="{{ route('incidencias.create') }}" class="inline-flex items-center justify-center rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4959A6]">Registrar incidencia</a>
            </header>

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section aria-label="Resumen de incidencias" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-amber-100 bg-gradient-to-br from-white to-amber-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Pendientes de atención</p>
                    <p class="mt-3 text-3xl font-bold text-amber-700">{{ $estadisticas['pendientes'] }}</p>
                </article>
                <article class="rounded-2xl border border-red-100 bg-gradient-to-br from-white to-red-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Prioridad alta o crítica</p>
                    <p class="mt-3 text-3xl font-bold text-red-700">{{ $estadisticas['prioritarias'] }}</p>
                </article>
                <article class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Reportes automáticos</p>
                    <p class="mt-3 text-3xl font-bold text-[#272F7A]">{{ $estadisticas['automaticas'] }}</p>
                </article>
                <article class="rounded-2xl border border-sky-100 bg-gradient-to-br from-white to-sky-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Reportadas desde app</p>
                    <p class="mt-3 text-3xl font-bold text-sky-700">{{ $estadisticas['app'] }}</p>
                </article>
                <article class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Resueltas</p>
                    <p class="mt-3 text-3xl font-bold text-emerald-700">{{ $estadisticas['resueltas'] }}</p>
                </article>
            </section>

            <section class="rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm sm:p-5">
                <form method="GET" action="{{ route('incidencias.index') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_11rem_11rem_12rem_auto_auto]">
                    <label for="buscar" class="sr-only">Buscar incidencias</label>
                    <input id="buscar" name="buscar" type="search" value="{{ request('buscar') }}" placeholder="Buscar por equipo, tipo, estudiante o descripción..." class="min-h-11 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <label for="estado" class="sr-only">Filtrar por estado</label>
                    <select id="estado" name="estado" class="min-h-11 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos los estados</option>
                        <option value="pendientes" @selected(request('estado') === 'pendientes')>Pendientes</option>
                        <option value="resueltas" @selected(request('estado') === 'resueltas')>Resueltas</option>
                    </select>
                    <label for="prioridad" class="sr-only">Filtrar por prioridad</label>
                    <select id="prioridad" name="prioridad" class="min-h-11 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todas las prioridades</option>
                        @foreach (['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'] as $valor => $prioridad)
                            <option value="{{ $valor }}" @selected(($filtros['prioridad'] ?? '') === $valor)>{{ $prioridad }}</option>
                        @endforeach
                    </select>
                    <label for="origen" class="sr-only">Filtrar por origen</label>
                    <select id="origen" name="origen" class="min-h-11 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos los orígenes</option>
                        <option value="manual" @selected(($filtros['origen'] ?? '') === 'manual')>Manual</option>
                        <option value="automatica" @selected(($filtros['origen'] ?? '') === 'automatica')>Automática</option>
                        <option value="estudiante" @selected(($filtros['origen'] ?? '') === 'estudiante')>Reportada desde portal</option>
                        <option value="app" @selected(($filtros['origen'] ?? '') === 'app')>Reportada desde app</option>
                    </select>
                    <button type="submit" class="min-h-11 rounded-xl bg-[#272F7A] px-5 text-sm font-semibold text-white transition hover:bg-[#4959A6]">Buscar</button>
                    @if (collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== ''))
                        <a href="{{ route('incidencias.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Limpiar</a>
                    @endif
                </form>
            </section>

            <section class="grid gap-5 lg:grid-cols-2">
                @forelse ($incidencias as $incidencia)
                    @php
                        $equipo = $incidencia->activo?->equipo;
                        $heartbeat = $equipo?->ultimoHeartbeat;
                        $estadoResuelto = in_array($incidencia->estado, ['resuelta', 'descartada'], true);
                        $origenEtiqueta = match ($incidencia->origen) {
                            'automatica' => 'Generada por diagnóstico',
                            'estudiante' => 'Reportada desde portal',
                            'app' => 'Reportada desde app',
                            default => 'Registro manual',
                        };
                        $prioridadColor = match (strtolower($incidencia->prioridad)) {
                            'critica' => 'bg-red-100 text-red-800',
                            'alta' => 'bg-orange-100 text-orange-800',
                            'media' => 'bg-amber-100 text-amber-800',
                            default => 'bg-sky-100 text-sky-800',
                        };
                    @endphp
                    <article class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-indigo-50 bg-gradient-to-r from-indigo-50 to-blue-50/60 px-5 py-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-[#4959A6]">{{ $incidencia->tipo }} · {{ $origenEtiqueta }}</p>
                                @if ($incidencia->activo)
                                    <h2 class="mt-1 text-lg font-bold text-slate-800">
                                        @if ($equipo)
                                            <a href="{{ route('inventario.activos.show', $incidencia->activo_id) }}" class="hover:text-[#4959A6] hover:underline">{{ $incidencia->activo->codigo }}</a>
                                        @else
                                            {{ $incidencia->activo->codigo }}
                                        @endif
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-600">{{ $incidencia->activo->nombre }} · {{ $incidencia->activo->marca }} {{ $incidencia->activo->modelo }}</p>
                                @else
                                    <h2 class="mt-1 text-lg font-bold text-slate-800">Activo no disponible</h2>
                                @endif
                                @if ($equipo)
                                    <p class="mt-1 text-xs text-slate-500">{{ $equipo->identificador }} · {{ $equipo->tipo_equipo === 'cpu' ? 'PC' : 'Tablet' }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $estadoResuelto ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst(str_replace('_', ' ', $incidencia->estado)) }}</span>
                                <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $prioridadColor }}">{{ $incidencia->prioridad === 'critica' ? 'Crítica' : ucfirst($incidencia->prioridad) }}</span>
                            </div>
                        </div>

                        <div class="space-y-5 p-5">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Descripción</p>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $incidencia->descripcion }}</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Asignación</p>
                                    @if ($incidencia->asignacion)
                                        <p class="mt-1 font-semibold text-slate-800">#{{ $incidencia->asignacion->id }} · {{ $incidencia->asignacion->estudiante?->nombreCompleto() ?? $incidencia->asignacion->invitado?->nombreCompleto() ?? 'Titular no disponible' }}</p>
                                        @if ($incidencia->asignacion->estudiante)
                                            <p class="mt-1 text-sm text-slate-500">Matrícula: {{ $incidencia->asignacion->estudiante->matricula }}</p>
                                        @endif
                                    @else
                                        <p class="mt-1 text-sm font-medium text-slate-600">Sin asignación asociada</p>
                                    @endif
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4">
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Seguimiento</p>
                                    <p class="mt-1 text-sm text-slate-700">Reportada: {{ $incidencia->fecha_reporte?->format('d/m/Y H:i') ?? 'Sin fecha' }}</p>
                                    <p class="mt-1 text-sm text-slate-700">Resuelta: {{ $incidencia->fecha_resolucion?->format('d/m/Y H:i') ?? 'Pendiente' }}</p>
                                    @if ($incidencia->reportadoPor)
                                        <p class="mt-1 text-sm text-slate-700">Reportada por: {{ $incidencia->reportadoPor->nombreCompleto() }}</p>
                                    @endif
                                    @if ($incidencia->resueltoPor)
                                        <p class="mt-1 text-sm text-slate-700">Atendida por: {{ $incidencia->resueltoPor->nombreCompleto() }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($equipo)
                                @php
                                    $ahora = now();
                                    $ultimaFechaHeartbeat = $heartbeat?->fecha_hora;
                                    $equipoConectado = $ultimaFechaHeartbeat !== null
                                        && $ultimaFechaHeartbeat->greaterThanOrEqualTo($ahora->copy()->subMinutes(\App\Models\Heartbeat::ONLINE_TIMEOUT_MINUTES))
                                        && $ultimaFechaHeartbeat->lessThanOrEqualTo($ahora);
                                @endphp
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-sky-100 bg-sky-50/70 p-4 text-xs text-sky-900">
                                    <span class="font-bold">{{ $equipoConectado ? 'App conectada' : ($heartbeat ? 'App sin conexión' : 'App sin reportes') }}</span>
                                    <span>{{ $equipo->token_hash ? 'Dispositivo vinculado' : 'Dispositivo no vinculado' }}</span>
                                    <span>Último reporte: {{ $heartbeat?->fecha_hora?->format('d/m/Y H:i:s') ?? 'Nunca' }}</span>
                                    @if ($heartbeat)
                                        <span>CPU {{ $heartbeat->cpu_uso !== null ? $heartbeat->cpu_uso.'%' : '—' }}</span>
                                        <span>RAM {{ $heartbeat->ram_uso !== null ? $heartbeat->ram_uso.'%' : '—' }}</span>
                                    @endif
                                    @if ($incidencia->alerta)
                                        <span>Regla {{ $incidencia->alerta->codigo_regla }}</span>
                                    @endif
                                </div>
                            @else
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">El activo todavía no está vinculado a una app compatible.</div>
                            @endif

                            @if ($incidencia->observaciones)
                                <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4">
                                    <p class="text-xs font-bold uppercase tracking-wider text-[#4959A6]">Observaciones</p>
                                    <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $incidencia->observaciones }}</p>
                                </div>
                            @endif

                            @if (auth()->user()->isAdministrador())
                                <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                                    @unless ($estadoResuelto)
                                        <form action="{{ route('incidencias.resolver', $incidencia->id) }}" method="POST" onsubmit="return confirm('¿Marcar esta incidencia como resuelta?')">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="min-h-10 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">Marcar como resuelta</button>
                                        </form>
                                    @endunless
                                    <form action="{{ route('incidencias.destroy', $incidencia->id) }}" method="POST" onsubmit="return confirm('¿Eliminar esta incidencia? Esta acción no se puede deshacer.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="min-h-10 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100">Eliminar</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-indigo-200 bg-white px-6 py-14 text-center lg:col-span-2">
                        <p class="text-lg font-bold text-slate-700">{{ collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '') ? 'No encontramos incidencias con esos filtros' : 'Aún no hay incidencias registradas' }}</p>
                        <p class="mt-2 text-sm text-slate-500">{{ collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '') ? 'Prueba otro término o limpia los filtros.' : 'Los reportes y alertas vinculados a los activos se mostrarán aquí.' }}</p>
                    </div>
                @endforelse
            </section>

            <div>{{ $incidencias->links() }}</div>
        </div>
    </main>
</x-admin-shell>
