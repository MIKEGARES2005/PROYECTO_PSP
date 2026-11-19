<x-docente-layout>
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-sky-700">Gestión Académica / Seguimiento</p>
            <h2 class="mt-1 text-3xl font-bold text-slate-800">Seguimiento estudiantil</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Notifica a administración observaciones o situaciones de estudiantes inscritos en tus materias activas.</p>
        </div>
        <span class="inline-flex w-fit rounded-xl bg-sky-100 px-4 py-2 text-sm font-bold text-sky-800">{{ $gestionVigente ? 'Gestión '.$gestionVigente : 'Sistema en espera' }}</span>
    </section>

    <div role="status" class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm leading-6 text-sky-900">
        Tus reportes se envían a administración para su revisión. Solo administración puede aprobar acciones y actualizar el historial o estado del estudiante.
    </div>

    @if (session('success'))
        <div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-2xl border border-sky-100 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('panel-docente.seguimiento') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="buscar" class="sr-only">Buscar estudiante</label>
                <input id="buscar" name="buscar" type="search" value="{{ $buscar }}" placeholder="Buscar por nombre, CI o matrícula" class="min-h-11 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            </div>
            @if ($semestres !== [])
                <div class="sm:w-52">
                    <label for="semestre" class="mb-1.5 block text-xs font-bold text-slate-600">Semestre de la materia</label>
                    <select id="semestre" name="semestre" class="min-h-11 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Todos los semestres</option>
                        @foreach ($semestres as $semestre)
                            <option value="{{ $semestre }}" @selected($semestreSeleccionado === $semestre)>{{ $semestre }} semestre</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="min-h-11 rounded-xl bg-sky-700 px-5 text-sm font-semibold text-white transition hover:bg-sky-800">Aplicar filtros</button>
            @if ($buscar !== '' || $semestreSeleccionado !== '')
                <a href="{{ route('panel-docente.seguimiento') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Limpiar</a>
            @endif
        </form>
    </section>

    <section aria-label="Estudiantes de las materias activas" class="space-y-4">
        @forelse ($estudiantes as $estudiante)
            <article class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-sky-50 bg-gradient-to-r from-sky-50 to-white p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">{{ $estudiante->nombreCompleto() }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Matrícula: {{ $estudiante->matricula ?: 'No registrada' }} · Semestre: {{ $estudiante->semestre }}</p>
                    </div>
                    <span class="inline-flex w-fit rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-800">{{ $estudiante->inscripciones->count() }} materia(s) contigo</span>
                </div>

                <div class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-wide text-slate-500">Materias activas</h4>
                        <ul class="mt-3 space-y-2">
                            @foreach ($estudiante->inscripciones as $inscripcion)
                                <li class="rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                                    {{ $inscripcion->materia?->sigla ? $inscripcion->materia->sigla.' · ' : '' }}{{ $inscripcion->materia?->nombre }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if ($gestionVigente !== null)
                        <form method="POST" action="{{ route('panel-docente.seguimiento.report', $estudiante) }}" class="grid gap-3 sm:grid-cols-2">
                            @csrf
                            <div>
                                <label for="materia_id_{{ $estudiante->id }}" class="mb-1.5 block text-xs font-bold text-slate-600">Materia</label>
                                <select id="materia_id_{{ $estudiante->id }}" name="materia_id" required class="w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                                    @foreach ($estudiante->inscripciones as $inscripcion)
                                        <option value="{{ $inscripcion->materia_id }}">{{ $inscripcion->materia?->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="tipo_evento_{{ $estudiante->id }}" class="mb-1.5 block text-xs font-bold text-slate-600">Evento para reportar</label>
                                <select id="tipo_evento_{{ $estudiante->id }}" name="tipo_evento" required class="w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                                    @foreach ($eventos as $tipo => $nombre)
                                        <option value="{{ $tipo }}">{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="fecha_evento_{{ $estudiante->id }}" class="mb-1.5 block text-xs font-bold text-slate-600">Fecha del evento</label>
                                <input id="fecha_evento_{{ $estudiante->id }}" name="fecha_evento" type="date" value="{{ now()->toDateString() }}" required class="w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            </div>
                            <div class="sm:row-span-2">
                                <label for="descripcion_{{ $estudiante->id }}" class="mb-1.5 block text-xs font-bold text-slate-600">Descripción</label>
                                <textarea id="descripcion_{{ $estudiante->id }}" name="descripcion" rows="3" maxlength="1000" required placeholder="Describe la situación para que administración la revise." class="w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500"></textarea>
                            </div>
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-sky-800 sm:col-span-2">
                                <x-icon name="bell" class="h-4 w-4" />
                                Enviar reporte a administración
                            </button>
                        </form>
                    @else
                        <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">Los reportes estarán disponibles cuando administración active la gestión académica.</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-sky-200 bg-white px-6 py-14 text-center">
                <p class="text-lg font-bold text-slate-700">{{ $buscar !== '' || $semestreSeleccionado !== '' ? 'No encontramos estudiantes con esos filtros' : 'No tienes estudiantes inscritos en tus materias activas' }}</p>
                <p class="mt-2 text-sm text-slate-500">Solo aparecen estudiantes cursando en materias que tienes asignadas durante la gestión vigente.</p>
            </div>
        @endforelse
    </section>

    <div>{{ $estudiantes->links() }}</div>
</x-docente-layout>
