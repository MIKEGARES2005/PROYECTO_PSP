<x-admin-shell>
    <main class="min-h-screen bg-[#F4F7FC] px-4 py-6 text-slate-800 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-4xl space-y-6">
            <header class="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] px-6 py-8 text-white shadow-2xl shadow-indigo-950/20 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-9">
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl" aria-hidden="true"></div>
                <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-30" aria-hidden="true"></div>
                <div class="relative">
                    <p class="text-sm font-medium text-cyan-200">Gestión de inventario / Incidencias</p>
                    <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Registrar incidencia</h1>
                    <p class="mt-2 text-sm leading-6 text-indigo-100/80">Describe el problema para facilitar su seguimiento y resolución.</p>
                </div>
                <a href="{{ route('incidencias.index') }}" class="relative inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-semibold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">Volver a incidencias</a>
            </header>

            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-bold">Revisa los datos del formulario:</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (($esDocente && $asignaciones->isEmpty()) || (! $esDocente && $equipos->isEmpty()))
                <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    @if ($esDocente)
                        No tienes asignaciones activas en tus horarios para asociar una incidencia.
                    @else
                        No hay equipos registrados para asociar a una incidencia.
                    @endif
                </div>
            @endif

            <form action="{{ route('incidencias.store') }}" method="POST" class="space-y-6">
                @csrf
                <section class="space-y-5 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Equipo y asignación</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            @if ($esDocente)
                                Solo puedes reportar problemas de equipos asignados durante tus clases.
                            @else
                                Puedes relacionar el reporte con un préstamo activo o dejarlo sin asignación.
                            @endif
                        </p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        @unless ($esDocente)
                            <div>
                                <label for="equipo_id" class="mb-2 block text-sm font-semibold text-slate-700">Equipo afectado</label>
                                <select id="equipo_id" name="equipo_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                    <option value="">Selecciona un equipo</option>
                                    @foreach ($equipos as $equipo)
                                        <option value="{{ $equipo->id }}" @selected((string) old('equipo_id') === (string) $equipo->id)>{{ $equipo->activo?->codigo }} · {{ $equipo->activo?->marca }} {{ $equipo->activo?->modelo }}</option>
                                    @endforeach
                                </select>
                                @error('equipo_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endunless

                        <div class="{{ $esDocente ? 'sm:col-span-2' : '' }}">
                            <label for="asignacion_id" class="mb-2 block text-sm font-semibold text-slate-700">{{ $esDocente ? 'Equipo asignado en tu clase' : 'Asignación relacionada (opcional)' }}</label>
                            <select id="asignacion_id" name="asignacion_id" {{ $esDocente ? 'required' : '' }} class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <option value="">{{ $esDocente ? 'Selecciona una asignación activa' : 'Sin asignación' }}</option>
                                @foreach ($asignaciones as $asignacion)
                                    <option value="{{ $asignacion->id }}" data-equipo-id="{{ $asignacion->equipo_id }}" @selected((string) old('asignacion_id') === (string) $asignacion->id)>
                                        {{ $asignacion->equipo?->activo?->codigo }} · {{ $asignacion->estudiante?->nombreCompleto() ?? $asignacion->invitado?->nombreCompleto() }} · {{ $asignacion->fecha_entrega?->format('d/m/Y') ?? 'Sin fecha' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('asignacion_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="space-y-5 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Detalle del problema</h2>
                        <p class="mt-1 text-sm text-slate-500">Indica el tipo de incidencia y su prioridad.</p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="tipo" class="mb-2 block text-sm font-semibold text-slate-700">Tipo de incidencia</label>
                            <select id="tipo" name="tipo" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <option value="">Selecciona un tipo</option>
                                @foreach (['Hardware' => 'Hardware', 'Software' => 'Software', 'Red' => 'Red', 'Bateria' => 'Batería', 'Pantalla' => 'Pantalla', 'Periferico' => 'Periférico', 'Otro' => 'Otro'] as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            @error('tipo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="prioridad" class="mb-2 block text-sm font-semibold text-slate-700">Prioridad</label>
                            <select id="prioridad" name="prioridad" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                @foreach (['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'] as $valor => $prioridad)
                                    <option value="{{ $valor }}" @selected(strtolower(old('prioridad', 'media')) === $valor)>{{ $prioridad }}</option>
                                @endforeach
                            </select>
                            @error('prioridad')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="descripcion" class="mb-2 block text-sm font-semibold text-slate-700">Descripción</label>
                            <textarea id="descripcion" name="descripcion" rows="5" maxlength="5000" required placeholder="Describe qué ocurrió, cuándo sucede y qué comportamiento observaste..." class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">{{ old('descripcion') }}</textarea>
                            @error('descripcion')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @unless ($esDocente)
                            <div>
                                <label for="estado" class="mb-2 block text-sm font-semibold text-slate-700">Estado inicial</label>
                                <select id="estado" name="estado" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                    <option value="abierta" @selected(old('estado', 'abierta') === 'abierta')>Abierta</option>
                                    <option value="en_revision" @selected(old('estado') === 'en_revision')>En revisión</option>
                                </select>
                                @error('estado')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="fecha_reporte" class="mb-2 block text-sm font-semibold text-slate-700">Fecha de reporte</label>
                                <input id="fecha_reporte" name="fecha_reporte" type="datetime-local" value="{{ old('fecha_reporte', now()->format('Y-m-d\TH:i')) }}" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                @error('fecha_reporte')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endunless
                        <div class="sm:col-span-2">
                            <label for="observaciones" class="mb-2 block text-sm font-semibold text-slate-700">Observaciones adicionales (opcional)</label>
                            <textarea id="observaciones" name="observaciones" rows="3" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">{{ old('observaciones') }}</textarea>
                            @error('observaciones')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('incidencias.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancelar</a>
                    <button type="submit" @disabled(($esDocente && $asignaciones->isEmpty()) || (! $esDocente && $equipos->isEmpty())) class="inline-flex items-center justify-center rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4959A6] disabled:cursor-not-allowed disabled:opacity-50">Registrar incidencia</button>
                </div>
            </form>
        </div>
    </main>
    @unless ($esDocente)
        <script>
            const equipoSelect = document.getElementById('equipo_id');
            const asignacionSelect = document.getElementById('asignacion_id');
            const asignacionOptions = [...asignacionSelect.options].slice(1);

            function filtrarAsignaciones() {
                const equipoId = equipoSelect.value;

                for (const option of asignacionOptions) {
                    option.hidden = equipoId !== '' && option.dataset.equipoId !== equipoId;
                }

                const selectedOption = asignacionSelect.options[asignacionSelect.selectedIndex];
                if (selectedOption?.hidden) {
                    asignacionSelect.value = '';
                }
            }

            equipoSelect.addEventListener('change', filtrarAsignaciones);
            filtrarAsignaciones();
        </script>
    @endunless
</x-admin-shell>
