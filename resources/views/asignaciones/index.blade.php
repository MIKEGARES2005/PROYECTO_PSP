<x-admin-shell>
    <main class="min-h-screen bg-gradient-to-br from-indigo-50/70 via-white to-blue-50/70 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="text-sm font-semibold text-[#4959A6]">Gestión de inventario / Asignaciones y préstamos</p><h1 class="mt-1 text-3xl font-bold text-slate-800">Asignaciones y préstamos</h1><p class="mt-2 text-sm text-slate-500">Asigna equipos a estudiantes o préstalos a invitados con control de devolución.</p></div>
                <div class="flex flex-col gap-3 sm:flex-row"><a href="{{ route('asignaciones.create') }}" class="inline-flex items-center justify-center rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4959A6]">Nueva asignación</a>@if (auth()->user()->isAdministrador())<a href="{{ route('prestamos.create') }}" class="inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-semibold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">Crear un nuevo préstamo</a>@endif</div>
            </header>
            @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <section aria-label="Resumen de asignaciones" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Asignaciones abiertas</p><p class="mt-3 text-3xl font-bold text-[#272F7A]">{{ $estadisticas['abiertas'] }}</p></article>
                <article class="rounded-2xl border border-blue-100 bg-gradient-to-br from-white to-blue-50 p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Pendientes de entrega</p><p class="mt-3 text-3xl font-bold text-blue-700">{{ $estadisticas['pendientes_entrega'] }}</p></article>
                <article class="rounded-2xl border border-red-100 bg-gradient-to-br from-white to-red-50 p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Vencidas</p><p class="mt-3 text-3xl font-bold text-red-700">{{ $estadisticas['vencidas'] }}</p></article>
                <article class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50 p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Equipos conectados</p><p class="mt-3 text-3xl font-bold text-emerald-700">{{ $estadisticas['dispositivos_conectados'] }}</p><p class="mt-2 text-xs text-slate-500">Reporte recibido en los últimos {{ \App\Models\Heartbeat::ONLINE_TIMEOUT_MINUTES }} minutos</p></article>
            </section>
            <section class="rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm sm:p-5">
                <form method="GET" action="{{ route('asignaciones.index') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_15rem_auto_auto]">
                    <label for="buscar" class="sr-only">Buscar</label>
                    <input id="buscar" name="buscar" type="search" value="{{ request('buscar') }}" placeholder="Buscar equipo, estudiante, invitado o materia" class="min-h-11 rounded-xl border-slate-300 text-sm shadow-sm">
                    <label for="estado" class="sr-only">Estado</label>
                    <select id="estado" name="estado" class="min-h-11 rounded-xl border-slate-300 text-sm shadow-sm">
                        <option value="">Todos los estados</option>
                        @foreach (['pendiente_entrega' => 'Pendiente de entrega', 'activa' => 'Activa', 'devolucion_pendiente' => 'Devolución pendiente', 'devuelta' => 'Devuelta', 'vencida' => 'Vencida', 'con_incidencia' => 'Con incidencia', 'cancelada' => 'Cancelada'] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <button class="min-h-11 rounded-xl bg-[#272F7A] px-5 text-sm font-semibold text-white">Buscar</button>
                    @if (request()->filled('buscar') || request()->filled('estado'))
                        <a href="{{ route('asignaciones.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-600">Limpiar</a>
                    @endif
                </form>
            </section>
            <section class="grid gap-5 lg:grid-cols-2">
                @forelse ($asignaciones as $asignacion)
                    @php
                        $activo = $asignacion->equipo?->activo ?? $asignacion->complemento?->activo;
                        $heartbeat = $asignacion->equipo?->ultimoHeartbeat;
                        $ahora = now();
                        $ultimaFechaHeartbeat = $heartbeat?->fecha_hora;
                        $equipoConectado = $ultimaFechaHeartbeat !== null
                            && $ultimaFechaHeartbeat->greaterThanOrEqualTo($ahora->copy()->subMinutes(\App\Models\Heartbeat::ONLINE_TIMEOUT_MINUTES))
                            && $ultimaFechaHeartbeat->lessThanOrEqualTo($ahora);
                        $estadoEstilo = match ($asignacion->estado) {
                            'vencida', 'con_incidencia' => 'bg-red-100 text-red-800',
                            'pendiente_entrega', 'devolucion_pendiente' => 'bg-amber-100 text-amber-800',
                            'devuelta', 'cancelada' => 'bg-slate-200 text-slate-700',
                            default => 'bg-emerald-100 text-emerald-800',
                        };
                    @endphp
                    <article class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-indigo-50 bg-gradient-to-r from-indigo-50 to-blue-50/60 px-5 py-4"><div><p class="text-xs font-bold uppercase tracking-wider text-[#4959A6]">{{ $asignacion->tipo === 'prestado' ? 'Préstamo a invitado' : 'Asignación estudiantil' }} · {{ $asignacion->codigo }}</p><h2 class="mt-1 text-xl font-bold text-slate-800">{{ $activo?->codigo ?? 'Recurso no disponible' }}</h2><p class="mt-1 text-sm text-slate-600">{{ $activo?->nombre }}</p></div><span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $estadoEstilo }}">{{ ucfirst(str_replace('_', ' ', $asignacion->estado)) }}</span></div>
                        @if ($asignacion->equipo)
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs">
                                <span class="font-bold text-slate-700">{{ $asignacion->equipo->tipo_equipo === 'cpu' ? 'PC' : 'Tablet' }} · {{ $asignacion->equipo->identificador }}</span>
                                <span class="rounded-full px-2.5 py-1 font-bold {{ $equipoConectado ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">{{ $equipoConectado ? 'App conectada' : ($heartbeat ? 'App sin conexión' : 'Sin reportes') }}</span>
                                <span class="rounded-full px-2.5 py-1 font-bold {{ $asignacion->equipo->token_hash ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700' }}">{{ $asignacion->equipo->token_hash ? 'Vinculada' : 'No vinculada' }}</span>
                                <span class="text-slate-500">Último reporte: {{ $heartbeat?->fecha_hora?->format('d/m/Y H:i:s') ?? 'Nunca' }}</span>
                            </div>
                        @else
                            <div class="border-b border-slate-100 bg-slate-50 px-5 py-3 text-xs text-slate-500">Este complemento no tiene monitoreo de app.</div>
                        @endif
                        <div class="space-y-5 p-5"><div class="grid gap-4 sm:grid-cols-2"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $asignacion->invitado ? 'Invitado' : 'Estudiante' }}</p><p class="mt-1 font-semibold text-slate-800">@if ($asignacion->invitado){{ $asignacion->invitado->nombreCompleto() }}@else{{ $asignacion->estudiante?->nombreCompleto() }}@endif</p><p class="mt-1 text-sm text-slate-500">{{ $asignacion->invitado ? 'CI: '.$asignacion->invitado->ci : 'Matrícula: '.($asignacion->estudiante?->matricula ?? 'No registrada') }}</p><p class="text-sm text-slate-500">Modalidad: {{ ucfirst($asignacion->modalidad) }}</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Actividad</p><p class="mt-1 font-semibold text-slate-800">{{ $asignacion->horario?->materia?->nombre ?? 'Préstamo externo' }}</p><p class="mt-1 text-sm text-slate-500">Grupo: {{ $asignacion->codigo_grupo ?: 'No registrado' }}</p>@if ($asignacion->horario)<p class="text-sm text-slate-500">{{ $asignacion->horario->dia_semana }} · {{ substr($asignacion->horario->hora_inicio, 0, 5) }} - {{ substr($asignacion->horario->hora_fin, 0, 5) }}</p>@endif</div></div><dl class="grid gap-3 sm:grid-cols-2"><div><dt class="text-xs font-semibold text-slate-500">Entrega</dt><dd class="mt-1 text-sm font-medium text-slate-700">{{ $asignacion->fecha_entrega?->format('d/m/Y H:i') ?? 'No registrada' }}</dd></div><div><dt class="text-xs font-semibold text-slate-500">Devolución prevista</dt><dd class="mt-1 text-sm font-medium text-slate-700">{{ $asignacion->fecha_devolucion_prevista?->format('d/m/Y H:i') ?? 'Pendiente' }}</dd></div></dl>@if ($asignacion->observaciones)<div class="rounded-xl border border-amber-100 bg-amber-50/70 p-4"><p class="text-xs font-bold uppercase tracking-wider text-amber-800">Observaciones</p><p class="mt-1 whitespace-pre-line text-sm text-amber-900">{{ $asignacion->observaciones }}</p></div>@endif
                            @if ($asignacion->esta_abierta)<div class="flex flex-col gap-3 border-t border-slate-100 pt-4"><form action="{{ route('asignaciones.finalizar', $asignacion->id) }}" method="POST" onsubmit="return confirm('¿Confirmas la devolución?')">@csrf @method('PUT')<button class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-800">Registrar devolución</button></form>@if ($asignacion->tipo === 'asignado')<form action="{{ route('asignaciones.cambiar-equipo', $asignacion->id) }}" method="POST" class="flex flex-col gap-2 sm:flex-row">@csrf<select name="equipo_id" required class="min-h-11 flex-1 rounded-xl border-slate-300 text-sm"><option value="">Cambiar equipo</option>@foreach ($equiposDisponibles as $equipo)<option value="{{ $equipo->id }}">{{ $equipo->activo?->codigo }} · {{ $equipo->activo?->nombre }} · {{ ucfirst($equipo->modo_uso) }}</option>@endforeach</select><button class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-bold text-[#272F7A]">Cambiar equipo</button></form>@endif</div>@endif
                        </div>
                    </article>
                @empty<div class="rounded-2xl border border-dashed border-indigo-200 bg-white px-6 py-14 text-center lg:col-span-2"><p class="text-lg font-bold text-slate-700">No hay asignaciones o préstamos registrados</p><p class="mt-2 text-sm text-slate-500">Crea una asignación estudiantil o un préstamo para un invitado.</p></div>@endforelse
            </section>
            <div>{{ $asignaciones->links() }}</div>
        </div>
    </main>
</x-admin-shell>
