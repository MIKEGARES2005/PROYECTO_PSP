<x-admin-shell>
    <main class="min-h-screen bg-gradient-to-br from-indigo-50/70 via-white to-blue-50/70">
        <div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-[#4959A6]">Actividad y monitoreo</p>
                    <h1 class="mt-1 text-3xl font-bold text-slate-800">Diagnósticos</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-500">Hallazgos automáticos generados a partir de las métricas que reportan las apps de PC y tablet.</p>
                </div>
            </header>

            <section aria-label="Revisión automática de dispositivos" class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Revisión automática de dispositivos</h2>
                        <p class="mt-1 max-w-3xl text-sm text-slate-500">Cada reporte de las apps PC y tablet ejecuta las reglas disponibles para CPU, RAM, almacenamiento, temperatura y batería. Se revisan las métricas que el dispositivo pueda proporcionar.</p>
                    </div>
                    <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-[#272F7A]">{{ $equiposRevision->total() }} equipos registrados</span>
                </div>

                @if ($equiposRevision->isEmpty())
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
                        No hay equipos dentro de tu ámbito para revisar.
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($equiposRevision as $equipo)
                            @php
                                [$revisionEtiqueta, $revisionEstilo] = match ($equipo->estado_revision) {
                                    'sin_datos' => ['Esperando primer reporte', 'bg-slate-100 text-slate-700'],
                                    'desconectado' => ['Sin conexión reciente', 'bg-amber-100 text-amber-800'],
                                    'con_alertas' => ['Requiere atención', 'bg-red-100 text-red-800'],
                                    'sin_metricas' => ['Reporte sin métricas evaluables', 'bg-amber-100 text-amber-800'],
                                    default => ['Sin alertas en esta lectura', 'bg-emerald-100 text-emerald-800'],
                                };
                                $heartbeat = $equipo->ultimoHeartbeat;
                                $metricasRevision = [
                                    'CPU' => $heartbeat?->cpu_uso === null ? null : $heartbeat->cpu_uso.'%',
                                    'RAM' => $heartbeat?->ram_uso === null ? null : $heartbeat->ram_uso.'%',
                                    'Disco' => $heartbeat?->almacenamiento_uso === null ? null : $heartbeat->almacenamiento_uso.'%',
                                    'Batería' => $heartbeat?->bateria === null ? null : $heartbeat->bateria.'%',
                                    'Temperatura' => $heartbeat?->temperatura === null ? null : $heartbeat->temperatura.' °C',
                                ];
                            @endphp
                            <article class="space-y-4 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-[#272F7A]">{{ $equipo->tipo_equipo === 'cpu' ? 'PC' : 'Tablet' }}</span>
                                        <h3 class="mt-2 truncate text-lg font-bold text-slate-800">{{ $equipo->identificador }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ $equipo->activo?->codigo ?? 'Activo sin código' }} · {{ $equipo->activo?->nombre ?? 'Activo sin nombre' }}</p>
                                    </div>
                                    <a href="{{ route('inventario.activos.show', $equipo->activo_id) }}" class="text-xs font-semibold text-[#4959A6] hover:underline">Ver equipo</a>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $heartbeat && $equipo->estado_revision !== 'desconectado' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $heartbeat ? ($equipo->estado_revision === 'desconectado' ? 'Desconectado' : 'Conectado') : 'Sin reportes' }}</span>
                                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $revisionEstilo }}">{{ $revisionEtiqueta }}</span>
                                </div>
                                <dl class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 text-sm">
                                    @foreach ($metricasRevision as $nombreMetrica => $valorMetrica)
                                        <div>
                                            <dt class="text-xs text-slate-500">{{ $nombreMetrica }}</dt>
                                            <dd class="font-semibold text-slate-800">{{ $valorMetrica ?? 'No reportada' }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                                <p class="border-t border-slate-100 pt-3 text-xs text-slate-500">Último reporte: {{ $heartbeat?->fecha_hora?->format('d/m/Y H:i:s') ?? 'Nunca' }}</p>
                            </article>
                        @endforeach
                    </div>
                    @if ($equiposRevision->hasPages())
                        <div class="rounded-xl border border-indigo-100 bg-white px-5 py-4">{{ $equiposRevision->links() }}</div>
                    @endif
                @endif
            </section>

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <section aria-label="Resumen de diagnósticos" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Hallazgos registrados</p>
                    <p class="mt-3 text-3xl font-bold text-[#272F7A]">{{ $estadisticas['total'] }}</p>
                </article>
                <article class="rounded-2xl border border-red-100 bg-gradient-to-br from-white to-red-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Críticos</p>
                    <p class="mt-3 text-3xl font-bold text-red-700">{{ $estadisticas['criticas'] }}</p>
                    <a href="{{ route('diagnosticos.index', array_merge(request()->except('page'), ['nivel' => 'critica'])) }}" class="mt-2 inline-flex text-xs font-semibold text-red-700 hover:underline">Ver críticos</a>
                </article>
                <article class="rounded-2xl border border-amber-100 bg-gradient-to-br from-white to-amber-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Advertencias</p>
                    <p class="mt-3 text-3xl font-bold text-amber-700">{{ $estadisticas['advertencias'] }}</p>
                    <a href="{{ route('diagnosticos.index', array_merge(request()->except('page'), ['nivel' => 'advertencia'])) }}" class="mt-2 inline-flex text-xs font-semibold text-amber-800 hover:underline">Ver advertencias</a>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Informativos</p>
                    <p class="mt-3 text-3xl font-bold text-slate-700">{{ $estadisticas['informativos'] }}</p>
                </article>
            </section>

            <section class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm sm:p-6">
                <form method="GET" action="{{ route('diagnosticos.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="buscar" class="mb-2 block text-sm font-semibold text-slate-700">Buscar</label>
                        <input id="buscar" name="buscar" type="search" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Código de equipo, tipo, nivel o descripción" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="tipo" class="mb-2 block text-sm font-semibold text-slate-700">Tipo de diagnóstico</label>
                        <select id="tipo" name="tipo" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos los tipos</option>
                            @foreach ($tipos as $tipo)
                                <option value="{{ $tipo }}" @selected(($filtros['tipo'] ?? '') === $tipo)>{{ $tipo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="nivel" class="mb-2 block text-sm font-semibold text-slate-700">Nivel</label>
                        <select id="nivel" name="nivel" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos los niveles</option>
                            @foreach ($niveles as $nivel)
                                <option value="{{ $nivel }}" @selected(($filtros['nivel'] ?? '') === $nivel)>{{ $nivel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="desde" class="mb-2 block text-sm font-semibold text-slate-700">Desde</label>
                            <input id="desde" name="desde" type="date" value="{{ $filtros['desde'] ?? '' }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="hasta" class="mb-2 block text-sm font-semibold text-slate-700">Hasta</label>
                            <input id="hasta" name="hasta" type="date" value="{{ $filtros['hasta'] ?? '' }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="flex flex-wrap items-end gap-3 sm:col-span-2 lg:col-span-3">
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#272F7A] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#4959A6]">Aplicar filtros</button>
                        @if (collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->isNotEmpty())
                            <a href="{{ route('diagnosticos.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Limpiar</a>
                        @endif
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-indigo-50 bg-gradient-to-r from-indigo-50 to-blue-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-bold text-slate-800">Historial de diagnósticos</h2>
                        <p class="mt-1 text-xs text-slate-500">Los registros más recientes aparecen primero.</p>
                    </div>
                    <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-[#272F7A]">{{ $diagnosticos->total() }} registros</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($diagnosticos as $diagnostico)
                        @php
                            $nivelNormalizado = strtolower($diagnostico->nivel);
                            $nivelEstilo = in_array($nivelNormalizado, ['critico', 'crítico'], true)
                                ? 'bg-red-50 text-red-700'
                                : (in_array($nivelNormalizado, ['alto', 'advertencia', 'warning'], true)
                                    ? 'bg-amber-50 text-amber-800'
                                    : 'bg-slate-100 text-slate-700');
                        @endphp
                        <article class="px-5 py-5 sm:px-6">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($diagnostico->equipo?->activo)
                                            <a href="{{ route('inventario.activos.show', $diagnostico->equipo->activo_id) }}" class="font-bold text-[#272F7A] hover:underline">{{ $diagnostico->equipo->activo->codigo }}</a>
                                        @else
                                            <span class="font-bold text-slate-600">{{ $diagnostico->equipo?->identificador ?? 'Equipo no disponible' }}</span>
                                        @endif
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $diagnostico->equipo?->tipo_equipo === 'cpu' ? 'PC' : 'Tablet' }}</span>
                                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-[#4959A6]">{{ $diagnostico->tipo }}</span>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $nivelEstilo }}">{{ $diagnostico->nivel }}</span>
                                    </div>
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Regla {{ $diagnostico->codigo_regla }}</p>
                                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $diagnostico->descripcion }}</p>
                                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                                        <span>Registrado: {{ $diagnostico->fecha_hora?->format('d/m/Y H:i:s') ?? 'Sin fecha' }}</span>
                                        @if ($diagnostico->heartbeat)
                                            <span>App: {{ data_get($diagnostico->heartbeat->metricas, 'cliente') === 'agente_pc' ? 'Monitor PC' : (data_get($diagnostico->heartbeat->metricas, 'cliente') === 'prueba_flutter' ? 'Monitor tablet' : 'Dispositivo') }}</span>
                                            @if (data_get($diagnostico->heartbeat->metricas, 'modelo'))
                                                <span>Modelo reportado: {{ data_get($diagnostico->heartbeat->metricas, 'modelo') }}</span>
                                            @endif
                                            @if ($diagnostico->heartbeat->cpu_uso !== null)
                                                <span>CPU: {{ $diagnostico->heartbeat->cpu_uso }}%</span>
                                            @endif
                                            @if ($diagnostico->heartbeat->ram_uso !== null)
                                                <span>RAM: {{ $diagnostico->heartbeat->ram_uso }}%</span>
                                            @endif
                                        @else
                                            <span>Sin heartbeat asociado</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="px-5 py-14 text-center">
                            <p class="text-lg font-bold text-slate-700">{{ collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->isNotEmpty() ? 'No hay diagnósticos con esos filtros' : 'Aún no hay diagnósticos registrados' }}</p>
                            <p class="mt-2 text-sm text-slate-500">Cuando la aplicación de las computadoras y tablets envíe resultados, aparecerán aquí.</p>
                        </div>
                    @endforelse
                </div>
                <div class="border-t border-slate-100 px-5 py-4">{{ $diagnosticos->links() }}</div>
            </section>
        </div>
    </main>
</x-admin-shell>
