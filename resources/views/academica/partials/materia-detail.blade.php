@php
    $esVigente = $gestion !== null && $gestion === $gestionVigente;
    $urlSemestre = $esDocente
        ? route('panel-docente.gestion-academica.semestre', ['semestre' => $semestre, 'gestion' => $gestion])
        : route('gestion-academica.semestre', ['semestre' => $semestre, 'gestion' => $gestion]);
@endphp

<div class="min-h-screen bg-gradient-to-br from-indigo-50/70 via-white to-blue-50/70">
    <main class="mx-auto max-w-[90rem] space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] p-6 text-white shadow-2xl shadow-indigo-950/20 sm:p-8">
            <div class="pointer-events-none absolute inset-0 dashboard-grid opacity-30"></div>
            <div class="relative space-y-5">
            <div class="flex flex-col gap-5">
                <div class="w-full min-w-0">
                    <p class="text-sm font-semibold text-cyan-200">{{ $esDocente ? 'Panel docente / Gestión Académica' : 'Gestión académica' }}</p>
                    <div class="mt-2 flex min-w-0 flex-col items-start gap-2">
                        <h1 class="w-full max-w-5xl whitespace-normal break-normal text-2xl font-black leading-tight tracking-tight sm:text-3xl">{{ $materia->nombre }}</h1>
                        @if ($materia->sigla)
                            <span class="rounded-lg bg-white/10 px-3 py-1 text-sm font-extrabold uppercase tracking-wide text-cyan-100 ring-1 ring-white/20">{{ $materia->sigla }}</span>
                        @else
                            <span class="rounded-lg bg-amber-200/15 px-3 py-1 text-xs font-semibold text-amber-100 ring-1 ring-amber-100/20">Sin sigla</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-indigo-100/80">{{ $semestre }} semestre · Gestión {{ $gestion ?? 'en espera' }}</p>
                </div>
                <div class="flex w-full flex-wrap items-center gap-3">
                    @if (! $esDocente && $gestion)
                        <form method="GET" action="{{ route('gestion-academica.materia', ['semestre' => $semestre, 'materia' => $materia]) }}" class="flex flex-wrap items-center gap-2">
                            <label for="gestion" class="text-xs font-bold uppercase tracking-wider text-cyan-200">Consultar gestión</label>
                            <select id="gestion" name="gestion" onchange="this.form.submit()" class="rounded-xl border-slate-300 bg-white text-sm font-semibold text-[#272F7A] shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($gestionesDisponibles as $opcionGestion)
                                    <option value="{{ $opcionGestion }}" class="text-[#272F7A]" @selected($gestion === $opcionGestion)>{{ $opcionGestion }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    <span class="rounded-xl px-4 py-2 text-sm font-bold {{ strtolower($materia->estado) === 'activo' ? 'bg-emerald-200/15 text-emerald-100 ring-1 ring-emerald-100/20' : 'bg-white/10 text-indigo-100 ring-1 ring-white/15' }}">
                        {{ strtolower($materia->estado) === 'activo' ? 'Materia activa' : 'Materia inactiva' }}
                    </span>
                    <a href="{{ $urlSemestre }}" class="inline-flex min-h-10 items-center rounded-lg border border-indigo-200 bg-white px-4 text-sm font-semibold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">
                        Volver al semestre
                    </a>
                </div>
            </div>

            @if ($errors->any())
                <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @if ($gestionVigente === null)
                <div role="status" class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    El sistema está en espera. Administración debe iniciar una gestión para habilitar horarios, inscripciones y calificaciones.
                </div>
            @elseif (! $esVigente)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    Consulta histórica de solo lectura para {{ $gestion }}. La gestión vigente es {{ $gestionVigente }}.
                </div>
            @endif
            </div>
        </header>

        <section aria-label="Acciones de la materia" class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-800">Acciones</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $esDocente ? 'Registra notas y envía el resultado de toda la materia para revisión administrativa.' : ($puedeCalificarComoDocente ? 'Administra la materia y registra notas en los cursos que tienes asignados.' : 'Administra la materia, sus horarios, inscripciones y resultados reportados.') }}
                    </p>
                </div>
                @if ($esAdministrador && $puedeEditarPeriodo)
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('materias.edit', ['materia' => $materia, 'gestion' => $gestion]) }}" class="inline-flex min-h-10 items-center rounded-lg bg-[#272F7A] px-4 text-sm font-semibold text-white transition hover:bg-[#4959A6]">Editar materia</a>
                        <form method="POST" action="{{ route('materias.status', $materia) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="estado" value="{{ strtolower($materia->estado) === 'activo' ? 'inactivo' : 'activo' }}">
                            <input type="hidden" name="gestion" value="{{ $gestion }}">
                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition {{ strtolower($materia->estado) === 'activo' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                                {{ strtolower($materia->estado) === 'activo' ? 'Inhabilitar materia' : 'Habilitar materia' }}
                            </button>
                        </form>
                        <a href="{{ route('horarios.create', ['semestre' => $semestre, 'materia_id' => $materia->id, 'gestion' => $gestion]) }}" class="inline-flex min-h-10 items-center rounded-lg bg-[#272F7A] px-4 text-sm font-semibold text-white hover:bg-[#4959A6]">Asignar horario</a>
                        <a href="{{ route('inscripciones.create', ['semestre' => $semestre, 'materia_id' => $materia->id, 'gestion' => $gestion]) }}" class="inline-flex min-h-10 items-center rounded-lg bg-[#272F7A] px-4 text-sm font-semibold text-white transition hover:bg-[#4959A6]">Inscribir estudiante</a>
                    </div>
                @elseif ($esDocente && $puedeReportarResultados)
                    <form method="POST" action="{{ route('panel-docente.inscripciones.report-results', $materia) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-gradient-to-r from-[#272F7A] to-[#4959A6] px-5 text-sm font-bold text-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                            Enviar resultados a administración
                            <span aria-hidden="true">→</span>
                        </button>
                    </form>
                @elseif ($esDocente && $inscripcionesCursando->isNotEmpty())
                    <div role="status" class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                        @if ($faltanCalificaciones > 0)
                            Faltan calificar {{ $faltanCalificaciones }} estudiante(s) antes del envío.
                        @else
                            Los resultados ya fueron enviados o no hay envíos pendientes.
                        @endif
                    </div>
                @endif
            </div>
        </section>

        <section aria-label="Horarios de la materia" class="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-4">
                <h2 class="text-lg font-extrabold text-slate-800">Horarios y docentes</h2>
                <p class="mt-1 text-sm text-slate-500">Programación de {{ $materia->nombre }} durante {{ $gestion ?? 'la gestión seleccionada' }}.</p>
            </div>
            @if ($materia->horarios->isEmpty())
                <p class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">Aún no hay horarios registrados para este periodo.</p>
            @else
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($materia->horarios as $horario)
                        <article class="rounded-xl border border-blue-200 bg-blue-50/70 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-bold text-slate-800">
                                        {{ trim(($horario->docente?->nombres ?? '').' '.($horario->docente?->apellido_paterno ?? '').' '.($horario->docente?->apellido_materno ?? '')) ?: 'Docente no disponible' }}
                                    </p>
                                    <p class="mt-2 text-sm text-slate-600">{{ $horario->dia_semana }} · {{ substr($horario->hora_inicio, 0, 5) }}–{{ substr($horario->hora_fin, 0, 5) }}</p>
                                    <p class="mt-1 text-sm text-slate-500">Aula: {{ $horario->aula ?: 'No asignada' }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ strtolower($horario->estado) === 'activo' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ strtolower($horario->estado) === 'activo' ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            @if ($esAdministrador && $puedeEditarPeriodo)
                                <div class="mt-4 flex flex-wrap gap-2 border-t border-blue-200 pt-3">
                                    <a href="{{ route('horarios.edit', ['horario' => $horario, 'gestion' => $gestion]) }}" class="inline-flex min-h-9 items-center rounded-lg bg-[#272F7A] px-3 text-xs font-semibold text-white transition hover:bg-[#4959A6]">Editar horario</a>
                                    <form method="POST" action="{{ route('horarios.status', $horario) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="estado" value="{{ strtolower($horario->estado) === 'activo' ? 'inactivo' : 'activo' }}">
                                        <input type="hidden" name="gestion" value="{{ $gestion }}">
                                        <button type="submit" class="inline-flex min-h-9 items-center justify-center rounded-lg px-3 text-xs font-semibold text-white transition {{ strtolower($horario->estado) === 'activo' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700' }}">{{ strtolower($horario->estado) === 'activo' ? 'Inhabilitar' : 'Habilitar' }}</button>
                                    </form>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section aria-label="Inscripciones y calificaciones" class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
            <div class="border-b border-indigo-50 bg-gradient-to-r from-indigo-50 to-blue-50/60 px-5 py-4 sm:px-6">
                <h2 class="text-lg font-extrabold text-slate-800">Estudiantes inscritos</h2>
                <p class="mt-1 text-sm text-slate-500">Parciales, promedio calculado y revisión del resultado para {{ $gestion ?? 'la gestión seleccionada' }}.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Estudiante</th>
                            <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Tipo</th>
                            <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Primer parcial</th>
                            <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Segundo parcial</th>
                            <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Promedio</th>
                            <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Resultado sugerido</th>
                            <th class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Revisión</th>
                            @if ($esAdministrador)
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($materia->inscripciones as $inscripcion)
                            @php
                                $puedeCalificar = $puedeCalificarComoDocente
                                    && $esVigente
                                    && $inscripcion->estado === 'Cursando'
                                    && $inscripcion->resultado_reportado === null;
                                $resultadoSugerido = $inscripcion->resultadoSegunNotaFinal();
                            @endphp
                            <tr class="align-top transition hover:bg-indigo-50/40">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-800">{{ trim(($inscripcion->estudiante?->nombres ?? '').' '.($inscripcion->estudiante?->apellido_paterno ?? '').' '.($inscripcion->estudiante?->apellido_materno ?? '')) ?: 'Estudiante no disponible' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $inscripcion->estudiante?->matricula ?? 'Sin matrícula' }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-slate-600">{{ $inscripcion->tipo }}</td>
                                <td class="px-4 py-4">
                                    @if ($puedeCalificar)
                                        <form method="POST" action="{{ route('inscripciones.register-partial', $inscripcion) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="parcial" value="1">
                                            <input type="number" name="nota" min="0" max="100" step="0.01" value="{{ $inscripcion->parcial_1 !== null ? number_format((float) $inscripcion->parcial_1, 2, '.', '') : '' }}" aria-label="Primer parcial de {{ $inscripcion->estudiante?->nombres }}" required class="w-24 rounded-lg border-slate-300 py-2 text-sm">
                                            <button type="submit" class="rounded-lg bg-[#272F7A] px-3 py-2 text-xs font-semibold text-white hover:bg-[#4959A6]">Guardar</button>
                                        </form>
                                    @else
                                        <span class="text-sm font-semibold text-slate-700">{{ $inscripcion->parcial_1 !== null ? number_format((float) $inscripcion->parcial_1, 2) : '—' }}</span>
                                    @endif
                                    @if ($inscripcion->primerParcialPor)
                                        <p class="mt-1 text-xs text-slate-400">{{ $inscripcion->primerParcialPor->name }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @if ($puedeCalificar && $inscripcion->parcial_1 !== null)
                                        <form method="POST" action="{{ route('inscripciones.register-partial', $inscripcion) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="parcial" value="2">
                                            <input type="number" name="nota" min="0" max="100" step="0.01" value="{{ $inscripcion->parcial_2 !== null ? number_format((float) $inscripcion->parcial_2, 2, '.', '') : '' }}" aria-label="Segundo parcial de {{ $inscripcion->estudiante?->nombres }}" required class="w-24 rounded-lg border-slate-300 py-2 text-sm">
                                            <button type="submit" class="rounded-lg bg-[#272F7A] px-3 py-2 text-xs font-semibold text-white hover:bg-[#4959A6]">Guardar</button>
                                        </form>
                                    @elseif ($puedeCalificar)
                                        <span class="text-xs text-slate-400">Registra P1 primero</span>
                                    @else
                                        <span class="text-sm font-semibold text-slate-700">{{ $inscripcion->parcial_2 !== null ? number_format((float) $inscripcion->parcial_2, 2) : '—' }}</span>
                                    @endif
                                    @if ($inscripcion->segundoParcialPor)
                                        <p class="mt-1 text-xs text-slate-400">{{ $inscripcion->segundoParcialPor->name }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-sm font-extrabold text-slate-800">{{ $inscripcion->nota_final !== null ? number_format((float) $inscripcion->nota_final, 2) : '—' }}</td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $resultadoSugerido === 'Aprobado' ? 'bg-emerald-50 text-emerald-700' : ($resultadoSugerido === 'Reprobado' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-500') }}">
                                        {{ $resultadoSugerido ?? 'Pendiente de parciales' }}
                                    </span>
                                    <p class="mt-1 text-xs text-slate-400">Estado oficial: {{ $inscripcion->estado }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($inscripcion->resultado_final)
                                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Revisado</span>
                                        <p class="mt-1 text-xs text-slate-500">Resultado oficial: {{ $inscripcion->resultado_final }} · {{ $inscripcion->fecha_revision?->format('d/m/Y') }}</p>
                                    @elseif ($inscripcion->resultado_reportado)
                                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">En revisión</span>
                                        <p class="mt-1 text-xs text-slate-500">{{ $inscripcion->resultado_reportado }} · {{ $inscripcion->fecha_reporte?->format('d/m/Y') }}</p>
                                    @else
                                        <span class="text-xs text-slate-400">No enviado</span>
                                    @endif
                                </td>
                                @if ($esAdministrador)
                                    <td class="px-4 py-4 text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            @if ($inscripcion->resultado_reportado && $inscripcion->estado === 'Cursando' && $puedeEditarPeriodo)
                                                <form method="POST" action="{{ route('inscripciones.status', $inscripcion) }}" class="flex items-center gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <select name="estado" aria-label="Confirmar resultado de inscripción" required class="rounded-lg border-slate-300 py-2 text-xs">
                                                        <option value="Aprobado" @selected($inscripcion->resultado_reportado === 'Aprobado')>Aprobado</option>
                                                        <option value="Reprobado" @selected($inscripcion->resultado_reportado === 'Reprobado')>Reprobado</option>
                                                    </select>
                                                    <button type="submit" class="rounded-lg bg-[#272F7A] px-3 py-2 text-xs font-semibold text-white hover:bg-[#4959A6]">Confirmar</button>
                                                </form>
                                            @endif
                                            @if ($inscripcion->resultado_reportado && $puedeEditarPeriodo)
                                                <details class="w-full max-w-sm text-left">
                                                    <summary class="cursor-pointer rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-[#272F7A] hover:bg-indigo-50">Corregir notas reportadas</summary>
                                                    <form method="POST" action="{{ route('inscripciones.corregir-notas', $inscripcion) }}" class="mt-2 space-y-3 rounded-xl border border-slate-200 bg-white p-3">
                                                        @csrf
                                                        @method('PATCH')
                                                        <div class="grid grid-cols-2 gap-3">
                                                            <div>
                                                                <label for="parcial_1_{{ $inscripcion->id }}" class="mb-1 block text-xs font-semibold text-slate-600">Primer parcial</label>
                                                                <input id="parcial_1_{{ $inscripcion->id }}" type="number" name="parcial_1" min="0" max="100" step="0.01" value="{{ old('parcial_1', number_format((float) $inscripcion->parcial_1, 2, '.', '')) }}" required class="w-full rounded-lg border-slate-300 py-2 text-sm">
                                                            </div>
                                                            <div>
                                                                <label for="parcial_2_{{ $inscripcion->id }}" class="mb-1 block text-xs font-semibold text-slate-600">Segundo parcial</label>
                                                                <input id="parcial_2_{{ $inscripcion->id }}" type="number" name="parcial_2" min="0" max="100" step="0.01" value="{{ old('parcial_2', number_format((float) $inscripcion->parcial_2, 2, '.', '')) }}" required class="w-full rounded-lg border-slate-300 py-2 text-sm">
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label for="motivo_{{ $inscripcion->id }}" class="mb-1 block text-xs font-semibold text-slate-600">Motivo de la corrección</label>
                                                            <textarea id="motivo_{{ $inscripcion->id }}" name="motivo" rows="2" minlength="10" maxlength="1000" required class="w-full rounded-lg border-slate-300 text-sm" placeholder="Explica el motivo (mínimo 10 caracteres)">{{ old('motivo') }}</textarea>
                                                        </div>
                                                        <button type="submit" class="rounded-lg bg-[#272F7A] px-3 py-2 text-xs font-semibold text-white hover:bg-[#4959A6]">Guardar corrección</button>
                                                    </form>
                                                </details>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $esAdministrador ? 8 : 7 }}" class="px-4 py-10 text-center text-sm text-slate-500">No hay estudiantes inscritos en esta materia durante {{ $gestion ?? 'la gestión seleccionada' }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($puedeCalificarComoDocente && $inscripcionesCursando->isNotEmpty())
                <div class="border-t border-slate-100 bg-slate-50 px-5 py-4 text-sm text-slate-600 sm:px-6">
                    El promedio es la media de los dos parciales. El resultado mostrado es una sugerencia; el estado oficial solo cambia después de la revisión administrativa.
                </div>
            @endif
        </section>
    </main>
</div>
