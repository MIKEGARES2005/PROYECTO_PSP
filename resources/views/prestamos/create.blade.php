<x-admin-layout>
    <x-admin-shell>
    <main class="min-h-screen bg-[#F4F7FC] px-4 py-6 text-slate-800 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-4xl space-y-6">
            <header class="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] px-6 py-8 text-white shadow-2xl shadow-indigo-950/20 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-9">
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl" aria-hidden="true"></div>
                <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-30" aria-hidden="true"></div>
                <div class="relative">
                    <p class="text-sm font-medium text-cyan-200">Gestión de inventario / Préstamos</p>
                    <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Crear un nuevo préstamo</h1>
                    <p class="mt-2 text-sm leading-6 text-indigo-100/80">Registra la entrega de un equipo individual a un invitado.</p>
                </div>
                <a href="{{ route('asignaciones.index') }}" class="relative inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-semibold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">Volver a asignaciones</a>
            </header>

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if ($equipos->isEmpty() || $invitados->isEmpty())
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Se necesita al menos un invitado activo y un equipo individual disponible.</div>
            @endif

            <form action="{{ route('prestamos.store') }}" method="POST" class="space-y-6">
                @csrf
                <section class="space-y-5 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
                    <div><h2 class="text-lg font-bold text-slate-800">Titular y equipo</h2><p class="mt-1 text-sm text-slate-500">Los préstamos son exclusivos para invitados y equipos individuales. La conexión de la app es informativa y no cambia la disponibilidad patrimonial.</p></div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="invitado_id" class="mb-2 block text-sm font-semibold text-slate-700">Invitado</label>
                            <select id="invitado_id" name="invitado_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <option value="">Selecciona un invitado</option>
                                @foreach ($invitados as $invitado)
                                    <option value="{{ $invitado->id }}" @selected((string) old('invitado_id') === (string) $invitado->id)>{{ $invitado->nombreCompleto() }} · CI {{ $invitado->ci }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="equipo_id" class="mb-2 block text-sm font-semibold text-slate-700">Equipo disponible</label>
                            <select id="equipo_id" name="equipo_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <option value="">Selecciona un equipo</option>
                                @foreach ($equipos as $equipo)
                                    @php
                                        $ultimaConexion = $equipo->ultimoHeartbeat?->fecha_hora;
                                        $estadoConexionApp = $ultimaConexion === null
                                            ? 'sin reportes'
                                            : ($ultimaConexion->between(now()->subMinutes(\App\Models\Heartbeat::ONLINE_TIMEOUT_MINUTES), now()) ? 'conectada' : 'desconectada');
                                    @endphp
                                    <option value="{{ $equipo->id }}" @selected((string) old('equipo_id') === (string) $equipo->id)>{{ $equipo->activo?->codigo }} · {{ $equipo->activo?->nombre }} · {{ strtoupper($equipo->tipo_equipo) }} · App {{ $estadoConexionApp }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <section class="space-y-5 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
                    <div><h2 class="text-lg font-bold text-slate-800">Entrega y devolución</h2><p class="mt-1 text-sm text-slate-500">El estado físico se registra al momento de entregar el equipo.</p></div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div><label for="fecha_entrega" class="mb-2 block text-sm font-semibold text-slate-700">Fecha de entrega</label><input id="fecha_entrega" name="fecha_entrega" type="datetime-local" value="{{ old('fecha_entrega', now()->format('Y-m-d\\TH:i')) }}" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]"></div>
                        <div><label for="fecha_devolucion_prevista" class="mb-2 block text-sm font-semibold text-slate-700">Devolución prevista</label><input id="fecha_devolucion_prevista" name="fecha_devolucion_prevista" type="datetime-local" value="{{ old('fecha_devolucion_prevista') }}" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]"></div>
                        <div><label for="estado_fisico_entrega" class="mb-2 block text-sm font-semibold text-slate-700">Estado físico de entrega</label><select id="estado_fisico_entrega" name="estado_fisico_entrega" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]"><option value="bueno">Bueno</option><option value="regular">Regular</option><option value="danado">Dañado</option></select></div>
                        <div><label for="observaciones" class="mb-2 block text-sm font-semibold text-slate-700">Observaciones</label><textarea id="observaciones" name="observaciones" rows="3" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">{{ old('observaciones') }}</textarea></div>
                    </div>
                </section>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><a href="{{ route('asignaciones.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancelar</a><button type="submit" @disabled($equipos->isEmpty() || $invitados->isEmpty()) class="inline-flex items-center justify-center rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4959A6] disabled:cursor-not-allowed disabled:opacity-50">Registrar préstamo</button></div>
            </form>
        </div>
    </main>
    </x-admin-shell>
</x-admin-layout>
