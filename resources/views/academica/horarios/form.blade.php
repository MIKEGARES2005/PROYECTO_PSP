<x-admin-layout>
    <x-admin-shell>
        @php
            $materiaIdSeleccionada = old('materia_id', $horario->materia_id);
            $materiaSeleccionada = $materias->firstWhere('id', $materiaIdSeleccionada);
            $urlRetorno = $materiaSeleccionada
                ? route('gestion-academica.materia', [
                    'semestre' => $materiaSeleccionada->semestre,
                    'materia' => $materiaSeleccionada,
                    'gestion' => $gestion,
                ])
                : ($semestre
                    ? route('gestion-academica.semestre', ['semestre' => $semestre, 'gestion' => $gestion])
                    : route('gestion-academica.index', ['gestion' => $gestion]));
        @endphp
        <div class="min-h-screen bg-[#F4F7FC] text-slate-800">
            <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <header class="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] px-6 py-7 text-white shadow-2xl shadow-indigo-950/20 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-8">
                    <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl" aria-hidden="true"></div>
                    <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-30" aria-hidden="true"></div>
                    <div class="relative">
                        <p class="text-sm font-medium text-cyan-200">Administración / Gestión académica / Horarios</p>
                        <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">{{ $horario->exists ? 'Editar horario' : 'Crear horario' }}</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100/80">Asigna la materia, el docente y el bloque de clases para esta gestión.</p>
                    </div>
                    <a href="{{ $urlRetorno }}"
                       class="relative inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50 hover:text-[#4959A6]">
                        {{ $materiaSeleccionada ? 'Volver a la materia' : 'Volver a Gestión académica' }}
                    </a>
                </header>

                @if ($errors->any())
                    <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p class="font-semibold">No se pudo guardar el horario. Revisa estos campos:</p>
                        <ul class="mt-2 list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST"
                      action="{{ $horario->exists ? route('horarios.update', $horario) : route('horarios.store') }}"
                      class="dashboard-rise space-y-6 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
                    @csrf
                    @if ($horario->exists)
                        @method('PUT')
                    @endif
                    <input type="hidden" name="gestion" value="{{ $gestion }}">

                    <section class="space-y-5">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">Asignación académica</h2>
                            <p class="mt-1 text-sm text-slate-500">Selecciona la materia y la persona docente responsable.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="materia_id" class="mb-2 block text-sm font-semibold text-slate-700">Materia</label>
                                <select id="materia_id" name="materia_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                    <option value="">Selecciona una materia</option>
                                    @foreach ($materias as $materia)
                                        <option value="{{ $materia->id }}" @selected(old('materia_id', $horario->materia_id) == $materia->id)>
                                            {{ $materia->sigla ? $materia->sigla.' · ' : '' }}{{ $materia->nombre }} · {{ $materia->semestre }}{{ strtolower($materia->estado) === 'activo' || strtolower($materia->estado) === 'activa' ? '' : ' (inactiva)' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('materia_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="docente_id" class="mb-2 block text-sm font-semibold text-slate-700">Docente</label>
                                <select id="docente_id" name="docente_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                    <option value="">Selecciona un docente</option>
                                    @foreach ($docentes as $docente)
                                        <option value="{{ $docente->id }}" @selected(old('docente_id', $horario->docente_id) == $docente->id)>
                                            {{ $docente->nombres }} {{ $docente->apellido_paterno }} {{ $docente->apellido_materno }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('docente_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section class="space-y-5 border-t border-slate-100 pt-5">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">Bloque de clases</h2>
                            <p class="mt-1 text-sm text-slate-500">Indica el día y las horas en que se dictará la materia.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="dia_semana" class="mb-2 block text-sm font-semibold text-slate-700">Día de la semana</label>
                                <select id="dia_semana" name="dia_semana" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                    <option value="">Selecciona un día</option>
                                    @foreach ($diasSemana as $dia)
                                        <option value="{{ $dia }}" @selected(old('dia_semana', $horario->dia_semana) === $dia)>{{ $dia }}</option>
                                    @endforeach
                                </select>
                                @error('dia_semana')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="aula" class="mb-2 block text-sm font-semibold text-slate-700">Aula <span class="font-normal text-slate-400">(opcional)</span></label>
                                <input id="aula" name="aula" value="{{ old('aula', $horario->aula) }}" maxlength="100"
                                       class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                @error('aula')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="hora_inicio" class="mb-2 block text-sm font-semibold text-slate-700">Hora de inicio</label>
                                <input id="hora_inicio" name="hora_inicio" type="time" required
                                       value="{{ old('hora_inicio', $horario->hora_inicio ? substr($horario->hora_inicio, 0, 5) : '') }}"
                                       class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                @error('hora_inicio')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="hora_fin" class="mb-2 block text-sm font-semibold text-slate-700">Hora de finalización</label>
                                <input id="hora_fin" name="hora_fin" type="time" required
                                       value="{{ old('hora_fin', $horario->hora_fin ? substr($horario->hora_fin, 0, 5) : '') }}"
                                       class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                @error('hora_fin')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ $urlRetorno }}"
                           class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                            Cancelar
                        </a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-[#1E2A5A] to-[#272F7A] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:from-[#272F7A] hover:to-[#4959A6]">
                            {{ $horario->exists ? 'Guardar cambios' : 'Crear horario' }}
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </x-admin-shell>
</x-admin-layout>
