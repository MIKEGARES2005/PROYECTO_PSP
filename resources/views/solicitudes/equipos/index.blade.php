<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitudes de equipos · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Operación</p>
                <h1 class="mt-2 text-3xl font-black">Solicitudes de equipos</h1>
                <p class="mt-1 text-sm text-slate-500">Aprueba solicitudes y devoluciones pendientes.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-emerald-700 ring-1 ring-emerald-200">
                Volver al dashboard
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-3xl border border-emerald-100 bg-white p-5 shadow-sm sm:p-7">
            <h2 class="text-xl font-black">Solicitudes pendientes</h2>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @forelse ($requests->where('estado', 'pendiente') as $solicitud)
                    <article class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex justify-between gap-3">
                            <div>
                                <p class="font-black">{{ $solicitud->estudianteSolicitante?->nombreCompleto() }}</p>
                                <p class="text-sm text-slate-600">
                                    {{ $solicitud->horario?->materia?->nombre }} · {{ $solicitud->fecha_uso?->format('d/m/Y') }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ strtoupper($solicitud->tipo_equipo) }} · {{ $solicitud->horario?->docente?->nombreCompleto() ?: 'Sin docente' }}
                                </p>
                            </div>
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Pendiente</span>
                        </div>
                        <form method="POST" action="{{ route('solicitudes.equipos.approve', $solicitud) }}" class="mt-4 grid gap-2 sm:grid-cols-3">
                            @csrf
                            <select name="equipo_id" required class="rounded-xl border-slate-300 text-sm">
                                <option value="">Equipo</option>
                                @foreach ($equipment->where('tipo_equipo', $solicitud->tipo_equipo) as $equipo)
                                    <option value="{{ $equipo->id }}">{{ $equipo->activo?->codigo }} · {{ $equipo->activo?->nombre }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="fecha_devolucion_prevista" min="{{ now()->toDateString() }}" required class="rounded-xl border-slate-300 text-sm">
                            <select name="estado_fisico_entrega" class="rounded-xl border-slate-300 text-sm">
                                <option value="bueno">Bueno</option>
                                <option value="regular">Regular</option>
                                <option value="danado">Dañado</option>
                            </select>
                            <button class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-black text-white sm:col-span-2">Aprobar y asignar</button>
                            <button type="submit" formaction="{{ route('solicitudes.equipos.reject', $solicitud) }}" formmethod="POST" class="rounded-xl bg-rose-600 px-3 py-2 text-sm font-black text-white">
                                Rechazar
                            </button>
                            <textarea name="observaciones" class="rounded-xl border-slate-300 text-sm sm:col-span-3" rows="2" placeholder="Motivo si rechazas o nota adicional"></textarea>
                        </form>
                    </article>
                @empty
                    <p class="text-sm text-slate-500">No hay solicitudes pendientes.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-amber-100 bg-white p-5 shadow-sm sm:p-7">
            <h2 class="text-xl font-black">Devoluciones pendientes</h2>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @forelse ($returns as $asignacion)
                    <article class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
                        <p class="font-black">{{ $asignacion->estudiante?->nombreCompleto() }}</p>
                        <p class="text-sm text-slate-600">{{ $asignacion->equipo?->activo?->codigo }} · {{ $asignacion->horario?->materia?->nombre }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $asignacion->motivo_devolucion }}</p>
                        <form method="POST" action="{{ route('solicitudes.equipos.return.approve', $asignacion) }}" class="mt-4 flex gap-2">
                            @csrf
                            <select name="estado_fisico_devolucion" class="min-w-0 flex-1 rounded-xl border-slate-300 text-sm">
                                <option value="bueno">Recibido bueno</option>
                                <option value="regular">Recibido regular</option>
                                <option value="danado">Recibido dañado</option>
                            </select>
                            <button class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-black text-white">Aprobar devolución</button>
                        </form>
                    </article>
                @empty
                    <p class="text-sm text-slate-500">No hay devoluciones pendientes.</p>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
