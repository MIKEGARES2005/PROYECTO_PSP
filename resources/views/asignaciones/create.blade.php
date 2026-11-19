<x-admin-shell>
    <main class="min-h-screen bg-[#F4F7FC] px-4 py-6 text-slate-800 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-4xl space-y-6">
            <header class="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] px-6 py-8 text-white shadow-2xl shadow-indigo-950/20 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-9">
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl" aria-hidden="true"></div>
                <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-30" aria-hidden="true"></div>
                <div class="relative">
                    <p class="text-sm font-medium text-cyan-200">Gestión de inventario / Asignaciones</p>
                    <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Nueva asignación</h1>
                    <p class="mt-2 text-sm leading-6 text-indigo-100/80">Registra el préstamo de un equipo a un estudiante inscrito en la materia.</p>
                </div>
                <a href="{{ route('asignaciones.index') }}" class="relative inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-semibold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">Volver a asignaciones</a>
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

            @if ($equipos->isEmpty() || $estudiantes->isEmpty() || $horarios->isEmpty())
                <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    No se puede registrar una asignación todavía. Se requiere al menos un equipo disponible, un estudiante activo con inscripción vigente y un horario activo.
                </div>
            @endif

            <form action="{{ route('asignaciones.store') }}" method="POST" class="space-y-6">
                @csrf
                <section class="space-y-5 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Personas y actividad académica</h2>
                        <p class="mt-1 text-sm text-slate-500">El estudiante debe tener una inscripción activa en la materia elegida.</p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="semestre" class="mb-2 block text-sm font-semibold text-slate-700">Semestre</label>
                            <select id="semestre" name="semestre" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <option value="">Selecciona primero un semestre</option>
                                @foreach ($semestres as $nombre)
                                    <option value="{{ $nombre }}" @selected((string) old('semestre') === (string) $nombre)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-slate-500">Al elegirlo se mostrarán únicamente sus estudiantes, materias y horarios.</p>
                            @error('semestre')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="estudiante_id" class="mb-2 block text-sm font-semibold text-slate-700">Estudiante</label>
                            <select id="estudiante_id" name="estudiante_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <option value="">Selecciona un estudiante</option>
                                @foreach ($estudiantes as $estudiante)
                                    <option value="{{ $estudiante->id }}" data-semestre="{{ $estudiante->semestre_nombre }}" data-materias="{{ $estudiante->inscripciones->pluck('materia_id')->implode(',') }}" @selected((string) old('estudiante_id') === (string) $estudiante->id)>
                                        {{ $estudiante->nombres }} {{ $estudiante->apellido_paterno }} {{ $estudiante->apellido_materno }} · {{ $estudiante->matricula }}
                                    </option>
                                @endforeach
                            </select>
                            @error('estudiante_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="materia_id" class="mb-2 block text-sm font-semibold text-slate-700">Materia</label>
                            <select id="materia_id" name="materia_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <option value="">Selecciona una materia</option>
                                @foreach ($materias as $materia)
                                    <option value="{{ $materia->id }}" data-semestre="{{ $materia->semestre_nombre }}" @selected((string) old('materia_id') === (string) $materia->id)>{{ $materia->nombre }} · {{ $materia->sigla }}</option>
                                @endforeach
                            </select>
                            @error('materia_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="horario_id" class="mb-2 block text-sm font-semibold text-slate-700">Horario</label>
                            <select id="horario_id" name="horario_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <option value="">Selecciona un horario</option>
                                @foreach ($horarios as $horario)
                                    <option value="{{ $horario->id }}" data-semestre="{{ \App\Models\Materia::nombreSemestre($horario->semestre) }}" data-materia="{{ $horario->materia_id }}" @selected((string) old('horario_id') === (string) $horario->id)>
                                        {{ $horario->materia_nombre }} · Semestre {{ \App\Models\Materia::nombreSemestre($horario->semestre) }} · {{ $horario->dia_semana }} {{ substr($horario->hora_inicio, 0, 5) }}–{{ substr($horario->hora_fin, 0, 5) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('horario_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="space-y-5 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Equipo y periodo</h2>
                        <p class="mt-1 text-sm text-slate-500">Los equipos grupales pueden tener más de una asignación activa. La conexión de la app es informativa y no cambia la disponibilidad patrimonial.</p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="equipo_id" class="mb-2 block text-sm font-semibold text-slate-700">Equipo</label>
                            <select id="equipo_id" name="equipo_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <option value="">Selecciona un equipo</option>
                                @foreach ($equipos as $equipo)
                                    @php
                                        $ultimaConexion = $equipo->ultimoHeartbeat?->fecha_hora;
                                        $estadoConexionApp = $ultimaConexion === null
                                            ? 'sin reportes'
                                            : ($ultimaConexion->between(now()->subMinutes(\App\Models\Heartbeat::ONLINE_TIMEOUT_MINUTES), now()) ? 'conectada' : 'desconectada');
                                    @endphp
                                    <option value="{{ $equipo->id }}" @selected((string) old('equipo_id') === (string) $equipo->id)>
                                        {{ $equipo->activo?->codigo }} · {{ $equipo->activo?->nombre }} · Uso {{ $equipo->modo_uso }} · App {{ $estadoConexionApp }}
                                    </option>
                                @endforeach
                            </select>
                            @error('equipo_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="modalidad" class="mb-2 block text-sm font-semibold text-slate-700">Modalidad</label>
                            <select id="modalidad" name="modalidad" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]"><option value="individual" @selected(old('modalidad') === 'individual')>Individual</option><option value="grupal" @selected(old('modalidad') === 'grupal')>Grupal</option></select>
                            @error('modalidad')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="codigo_grupo" class="mb-2 block text-sm font-semibold text-slate-700">Código de grupo (si aplica)</label>
                            <input id="codigo_grupo" name="codigo_grupo" value="{{ old('codigo_grupo') }}" maxlength="30" placeholder="Ejemplo: GRUPO-A" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                            @error('codigo_grupo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="fecha_entrega" class="mb-2 block text-sm font-semibold text-slate-700">Fecha de entrega</label>
                            <input id="fecha_entrega" name="fecha_entrega" type="datetime-local" value="{{ old('fecha_entrega', now()->format('Y-m-d\TH:i')) }}" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                            @error('fecha_entrega')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="fecha_devolucion_prevista" class="mb-2 block text-sm font-semibold text-slate-700">Devolución prevista</label>
                            <input id="fecha_devolucion_prevista" name="fecha_devolucion_prevista" type="datetime-local" value="{{ old('fecha_devolucion_prevista') }}" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                            @error('fecha_devolucion_prevista')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="observaciones" class="mb-2 block text-sm font-semibold text-slate-700">Observaciones (opcional)</label>
                            <textarea id="observaciones" name="observaciones" rows="4" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[#4959A6] focus:ring-[#4959A6]">{{ old('observaciones') }}</textarea>
                            @error('observaciones')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('asignaciones.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancelar</a>
                    <button type="submit" @disabled($equipos->isEmpty() || $estudiantes->isEmpty() || $horarios->isEmpty()) class="inline-flex items-center justify-center rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4959A6] disabled:cursor-not-allowed disabled:opacity-50">Crear asignación</button>
                </div>
            </form>
        </div>
    </main>
    <script>
        const semestreSelect = document.getElementById('semestre');
        const estudianteSelect = document.getElementById('estudiante_id');
        const materiaSelect = document.getElementById('materia_id');
        const horarioSelect = document.getElementById('horario_id');

        function filtrarPorSemestre() {
            const semestre = semestreSelect.value;
            Array.from(materiaSelect.options).forEach((option, index) => {
                if (index === 0) {
                    return;
                }
                const visible = semestre !== '' && option.dataset.semestre === semestre;
                option.hidden = !visible;
                option.disabled = !visible;
            });
            if (materiaSelect.selectedOptions[0]?.disabled) {
                materiaSelect.value = '';
            }
            filtrarEstudiantes();
            filtrarHorarios();
        }

        function filtrarEstudiantes() {
            const semestre = semestreSelect.value;
            const materia = materiaSelect.value;
            Array.from(estudianteSelect.options).forEach((option, index) => {
                if (index === 0) {
                    return;
                }
                const materias = (option.dataset.materias ?? '').split(',').filter(Boolean);
                const visible = semestre !== ''
                    && option.dataset.semestre === semestre
                    && (materia === '' || materias.includes(materia));
                option.hidden = !visible;
                option.disabled = !visible;
            });
            if (estudianteSelect.selectedOptions[0]?.disabled) {
                estudianteSelect.value = '';
            }
        }

        function filtrarHorarios() {
            const semestre = semestreSelect.value;
            const materia = materiaSelect.value;
            Array.from(horarioSelect.options).forEach((option, index) => {
                if (index === 0) {
                    return;
                }
                const visible = semestre !== '' && materia !== '' && option.dataset.semestre === semestre && option.dataset.materia === materia;
                option.hidden = !visible;
                option.disabled = !visible;
            });
            if (horarioSelect.selectedOptions[0]?.disabled) {
                horarioSelect.value = '';
            }
            filtrarEstudiantes();
        }

        semestreSelect.addEventListener('change', filtrarPorSemestre);
        materiaSelect.addEventListener('change', filtrarHorarios);
        filtrarPorSemestre();
    </script>
</x-admin-shell>
