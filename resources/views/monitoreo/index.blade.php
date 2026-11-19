<x-admin-shell>
    <main class="min-h-screen bg-gradient-to-br from-indigo-50/70 via-white to-blue-50/70">
        <div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-[#4959A6]">Actividad y monitoreo</p>
                    <h1 class="mt-1 text-3xl font-bold text-slate-800">Monitoreo de equipos</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-500">Últimas métricas recibidas desde las computadoras y tablets del laboratorio.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    @if (auth()->user()->isDocente() && ! auth()->user()->isAdministrador())
                        <a href="{{ route('asignaciones.create') }}" class="inline-flex items-center justify-center rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4959A6]">Nueva asignación</a>
                    @endif
                </div>
            </header>

            @if (auth()->user()->isAdministrador())
                <section aria-label="Vincular aplicación a un equipo" class="rounded-2xl border border-indigo-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">¿La app te pide el código del equipo?</h2>
                            <p class="mt-1 text-sm leading-6 text-slate-600">No ingreses el código patrimonial del inventario. En las tarjetas de abajo, elige el PC o tablet y pulsa <strong>Vincular este dispositivo</strong>; aquí aparecerá el código temporal para la app.</p>
                            <p class="mt-2 text-xs text-slate-500">El código es de un solo uso y vence en 10 minutos. Si ya existe un vínculo, generar uno nuevo lo reemplaza.</p>
                        </div>
                        <a href="#equipos-vinculacion" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#272F7A] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#4959A6]">Elegir equipo</a>
                    </div>
                    @if (session('pairing_code.codigo'))
                        <div role="status" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm font-bold text-amber-950">Código de vinculación para {{ session('pairing_code.equipo_identificador') }}{{ session('pairing_code.codigo_patrimonial') ? ' · activo '.session('pairing_code.codigo_patrimonial') : '' }}</p>
                            <p class="mt-2 select-all font-mono text-2xl font-black tracking-[0.18em] text-amber-950">{{ session('pairing_code.codigo') }}</p>
                            <p class="mt-1 text-xs text-amber-900">Ingresa este código temporal en la app instalada en ese equipo. No es el código patrimonial ni una contraseña.</p>
                        </div>
                    @endif
                </section>
            @endif

            <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900">
                Un equipo se considera conectado si reportó durante los últimos {{ \App\Models\Heartbeat::ONLINE_TIMEOUT_MINUTES }} minutos. Si nunca envió información, se muestra como “Sin datos”.
            </div>

            @if (session('success'))
                <div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <section aria-label="Resumen del monitoreo" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Equipos registrados</p>
                    <p class="mt-3 text-3xl font-bold text-[#272F7A]">{{ $estadisticas['total'] }}</p>
                </article>
                <article class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Conectados</p>
                    <p class="mt-3 text-3xl font-bold text-emerald-700">{{ $estadisticas['conectados'] }}</p>
                    <p class="mt-2 text-xs text-slate-500">Heartbeat reciente</p>
                </article>
                <article class="rounded-2xl border border-red-100 bg-gradient-to-br from-white to-red-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Desconectados</p>
                    <p class="mt-3 text-3xl font-bold text-red-700">{{ $estadisticas['desconectados'] }}</p>
                    <p class="mt-2 text-xs text-slate-500">Sin comunicación reciente</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Sin datos</p>
                    <p class="mt-3 text-3xl font-bold text-slate-700">{{ $estadisticas['sin_datos'] }}</p>
                    <p class="mt-2 text-xs text-slate-500">Aún no han reportado</p>
                </article>
                <article class="rounded-2xl border border-amber-100 bg-gradient-to-br from-white to-amber-50 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Alertas abiertas</p>
                    <p class="mt-3 text-3xl font-bold text-amber-700">{{ $estadisticas['alertas_abiertas'] }}</p>
                    <a href="{{ route('diagnosticos.index') }}" class="mt-2 inline-flex text-xs font-semibold text-amber-800 hover:underline">Ver diagnósticos</a>
                </article>
            </section>

            <section class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm sm:p-6">
                <form method="GET" action="{{ route('monitoreo.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_15rem_auto_auto] lg:items-end">
                    <div>
                        <label for="buscar" class="mb-2 block text-sm font-semibold text-slate-700">Buscar equipo</label>
                        <input id="buscar" name="buscar" type="search" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Código, marca, modelo o serie" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="estado" class="mb-2 block text-sm font-semibold text-slate-700">Conexión</label>
                        <select id="estado" name="estado" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos los estados</option>
                            <option value="conectados" @selected(($filtros['estado'] ?? '') === 'conectados')>Conectados</option>
                            <option value="desconectados" @selected(($filtros['estado'] ?? '') === 'desconectados')>Desconectados</option>
                            <option value="sin_datos" @selected(($filtros['estado'] ?? '') === 'sin_datos')>Sin datos</option>
                        </select>
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#272F7A] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#4959A6]">Filtrar</button>
                    @if (($filtros['buscar'] ?? '') !== '' || ($filtros['estado'] ?? '') !== '')
                        <a href="{{ route('monitoreo.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Limpiar</a>
                    @endif
                </form>
            </section>

            @if (auth()->user()->isAdministrador())
                <section id="equipos-vinculacion" aria-label="Equipos para vincular" class="space-y-4">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Elige la PC o tablet que vas a vincular</h2>
                            <p class="mt-1 text-sm text-slate-500">Selecciona el equipo por su identificador y genera su código temporal desde aquí.</p>
                        </div>
                        <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-[#272F7A]">{{ $equipos->total() }} equipos en esta vista</span>
                    </div>

                    @if ($equipos->isEmpty())
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                            @if ($estadisticas['total'] > 0)
                                No hay equipos que coincidan con los filtros. <a href="{{ route('monitoreo.index') }}" class="font-bold underline">Limpiar filtros</a> para verlos todos.
                            @else
                                Todavía no hay PCs ni tablets registrados como equipos de inventario.
                                <a href="{{ route('inventario.activos.create') }}" class="ml-1 font-bold underline">Registrar un equipo</a>.
                            @endif
                        </div>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($equipos as $equipo)
                                @php
                                    $estadoVinculacion = $equipo->token_hash
                                        ? 'Vinculado'
                                        : ($equipo->codigo_vinculacion_expira_en?->isFuture() ? 'Código pendiente' : 'Listo para vincular');
                                    $estadoConexionTarjeta = match ($equipo->estado_conexion) {
                                        'conectado' => ['Conectada', 'bg-emerald-100 text-emerald-800'],
                                        'desconectado' => ['Desconectada', 'bg-red-100 text-red-800'],
                                        default => ['Sin reportes', 'bg-slate-100 text-slate-700'],
                                    };
                                @endphp
                                <article class="flex h-full flex-col justify-between gap-4 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                                    <div class="space-y-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-[#272F7A]">{{ $equipo->tipo_equipo === 'cpu' ? 'PC' : 'Tablet' }}</span>
                                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $estadoConexionTarjeta[1] }}">{{ $estadoConexionTarjeta[0] }}</span>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-slate-800">{{ $equipo->identificador }}</h3>
                                            <p class="mt-1 text-sm text-slate-600">{{ $equipo->activo?->codigo }} · {{ $equipo->activo?->nombre ?? 'Activo sin nombre' }}</p>
                                        </div>
                                        <p class="text-xs text-slate-500">Vinculación: <span class="font-semibold text-slate-700">{{ $estadoVinculacion }}</span></p>
                                    </div>
                                    <form method="POST" action="{{ route('monitoreo.equipos.vinculacion.store', $equipo) }}" onsubmit="return confirm('{{ $equipo->token_hash || $equipo->codigo_vinculacion_hash ? 'Generar otro código revocará la credencial o código vigente. ¿Continuar?' : 'Generar un código de vinculación para este equipo?' }}')">
                                        @csrf
                                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-[#272F7A] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#4959A6]">
                                            {{ $equipo->token_hash || $equipo->codigo_vinculacion_hash ? 'Generar nuevo código' : 'Vincular este dispositivo' }}
                                        </button>
                                    </form>
                                    @if ($equipo->token_hash || $equipo->codigo_vinculacion_hash)
                                        <form method="POST" action="{{ route('monitoreo.equipos.vinculacion.destroy', $equipo) }}" onsubmit="return confirm('La app dejará de comunicarse con el sistema. ¿Desvincular este equipo?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">Desvincular dispositivo</button>
                                        </form>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            <section id="equipos-monitoreo" class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-indigo-50 bg-gradient-to-r from-indigo-50 to-blue-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-bold text-slate-800">Estado y métricas por equipo</h2>
                        <p class="mt-1 text-xs text-slate-500">Los valores corresponden al heartbeat más reciente de cada equipo.</p>
                    </div>
                    <p class="text-xs text-slate-500">Último reporte general:
                        <span class="font-semibold text-slate-700">{{ $ultimaActualizacion?->format('d/m/Y H:i:s') ?? 'Sin datos registrados' }}</span>
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Equipo</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Conexión</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Asignación actual</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">CPU / RAM</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Almacenamiento</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Batería / Temperatura</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Último recibido</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($equipos as $equipo)
                                @php
                                    $estadoEstilo = match ($equipo->estado_conexion) {
                                        'conectado' => 'bg-emerald-50 text-emerald-700',
                                        'desconectado' => 'bg-red-50 text-red-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                    $estadoEtiqueta = match ($equipo->estado_conexion) {
                                        'conectado' => 'Conectado',
                                        'desconectado' => 'Desconectado',
                                        default => 'Sin datos',
                                    };
                                    $heartbeat = $equipo->ultimoHeartbeat;
                                    $metricas = $heartbeat?->metricas ?? [];
                                @endphp
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <a href="{{ route('inventario.activos.show', $equipo->activo_id) }}" class="font-bold text-[#272F7A] hover:underline">{{ $equipo->activo?->codigo ?? $equipo->identificador }}</a>
                                        <p class="mt-1 text-sm text-slate-600">{{ $equipo->activo?->marca }} {{ $equipo->activo?->modelo }}</p>
                                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $equipo->tipo_equipo === 'cpu' ? 'PC' : 'Tablet' }} · {{ data_get($metricas, 'cliente') === 'agente_pc' ? 'Monitor PC' : (data_get($metricas, 'cliente') === 'prueba_flutter' ? 'Monitor tablet' : 'App vinculada') }}</p>
                                        @if (data_get($metricas, 'nombre') || data_get($metricas, 'plataforma'))
                                            <p class="mt-1 text-xs text-slate-500">{{ data_get($metricas, 'nombre') }}{{ data_get($metricas, 'plataforma') ? ' · '.strtoupper(data_get($metricas, 'plataforma')) : '' }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $estadoEstilo }}">{{ $estadoEtiqueta }}</span>
                                        @if ($equipo->alertas_abiertas_count > 0)
                                            <p class="mt-2 text-xs font-bold text-amber-700">{{ $equipo->alertas_abiertas_count }} alerta(s)</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-700">
                                        @if ($equipo->asignaciones->isNotEmpty())
                                            <div class="space-y-3">
                                                @foreach ($equipo->asignaciones as $asignacion)
                                                    <div class="border-b border-slate-100 pb-2 last:border-0 last:pb-0">
                                                        <p class="font-semibold">{{ $asignacion->estudiante?->nombreCompleto() ?? $asignacion->invitado?->nombreCompleto() ?? 'Titular registrado' }}</p>
                                                        <p class="mt-1 text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $asignacion->estado)) }}</p>
                                                        @if ($asignacion->horario)
                                                            <p class="mt-1 text-xs text-slate-600">Materia: {{ $asignacion->horario->materia?->nombre ?? 'No registrada' }}</p>
                                                            <p class="mt-1 text-xs text-slate-600">Docente: {{ $asignacion->horario->docente?->nombreCompleto() ?? 'No registrado' }}</p>
                                                        @else
                                                            <p class="mt-1 text-xs text-slate-600">Sin horario académico asociado</p>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif ($equipo->activo?->estado_uso === 'disponible')
                                            <span class="font-semibold text-emerald-700">Disponible para asignación</span>
                                        @elseif ($equipo->activo?->estado_uso === 'asignado')
                                            <span class="font-semibold text-amber-700">Marcado como asignado, sin asignación abierta registrada</span>
                                        @else
                                            <span class="text-slate-500">No disponible para asignación</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
                                        <p>CPU: <span class="font-semibold">{{ $heartbeat?->cpu_uso !== null ? $heartbeat->cpu_uso.'%' : '—' }}</span></p>
                                        <p class="mt-1">RAM: <span class="font-semibold">{{ $heartbeat?->ram_uso !== null ? $heartbeat->ram_uso.'%' : '—' }}</span></p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-700">{{ $heartbeat?->almacenamiento_uso !== null ? $heartbeat->almacenamiento_uso.'%' : '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
                                        <p>Batería: <span class="font-semibold">{{ $heartbeat?->bateria !== null ? $heartbeat->bateria.'%' : '—' }}</span></p>
                                        <p class="mt-1">Temp.: <span class="font-semibold">{{ $heartbeat?->temperatura !== null ? $heartbeat->temperatura.' °C' : '—' }}</span></p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $heartbeat?->fecha_hora?->format('d/m/Y H:i:s') ?? 'Sin reportes' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">
                                        @if (($filtros['buscar'] ?? '') !== '' || ($filtros['estado'] ?? '') !== '')
                                            No hay equipos que coincidan con los filtros.
                                        @else
                                            Aún no hay equipos registrados.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-4">{{ $equipos->links() }}</div>
            </section>
        </div>
    </main>
</x-admin-shell>
