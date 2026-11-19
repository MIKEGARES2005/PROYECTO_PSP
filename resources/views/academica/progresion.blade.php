<x-admin-layout>
    <x-admin-shell>
        <div class="min-h-screen bg-slate-50">
            <main class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <header class="flex flex-col gap-4 rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] p-6 text-white shadow-xl sm:flex-row sm:items-end sm:justify-between sm:p-8">
                    <div>
                        <p class="text-sm font-semibold text-cyan-200">Gestión académica / Cierre</p>
                        <h1 class="mt-2 text-2xl font-black sm:text-3xl">Progresión estudiantil</h1>
                        <p class="mt-2 text-sm text-indigo-100/80">Gestión {{ $gestion->codigo }} · Confirma o ajusta la recomendación para cada estudiante.</p>
                    </div>
                    <a href="{{ route('gestion-academica.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-indigo-200 bg-white px-4 text-sm font-bold text-[#272F7A] transition hover:bg-indigo-50">
                        Volver a Gestión académica
                    </a>
                </header>

                @if (session('success'))
                    <div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                @if ($inscripcionesPendientes > 0)
                    <div role="status" class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                        Aún faltan revisar {{ $inscripcionesPendientes }} inscripción(es). La progresión se habilitará cuando todos los resultados estén confirmados.
                    </div>
                @elseif ($progresiones->isEmpty())
                    <div role="status" class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-900">
                        No hay inscripciones en esta gestión para revisar progresiones.
                    </div>
                @else
                    <div class="flex flex-wrap gap-3">
                        <span class="rounded-full bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm">{{ $progresiones->count() }} estudiantes</span>
                        <span class="rounded-full px-4 py-2 text-sm font-bold shadow-sm {{ $decisionesPendientes === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $decisionesPendientes }} decisiones pendientes</span>
                    </div>

                    <section class="space-y-4" aria-label="Decisiones de progresión">
                        @foreach ($progresiones as $progresion)
                            @php
                                $estudiante = $progresion['estudiante'];
                                $nombreEstudiante = trim(($estudiante?->nombres ?? '').' '.($estudiante?->apellido_paterno ?? '').' '.($estudiante?->apellido_materno ?? ''));
                                $decisionLabels = [
                                    'avanza' => 'Avanza al siguiente semestre',
                                    'avanza_con_arrastres' => 'Avanza con materias de arrastre',
                                    'repite_semestre' => 'Repite el semestre',
                                    'retiro' => 'Egresado: finaliza cuarto semestre',
                                    'pendiente_materias_cuarto' => 'Continúa pendiente en cuarto semestre',
                                    'sin_cambio' => 'Mantiene su estado actual',
                                ];
                                $valorSeleccionado = old('decision', $progresion['decision_confirmada'] ?? $progresion['recomendacion']);
                            @endphp
                            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h2 class="text-lg font-black text-slate-800">{{ $nombreEstudiante !== '' ? $nombreEstudiante : 'Estudiante no disponible' }}</h2>
                                        <p class="mt-1 text-sm text-slate-500">{{ $estudiante?->matricula ?? 'Sin matrícula' }} · {{ $estudiante?->semestre ?? 'Semestre no disponible' }} semestre</p>
                                        <p class="mt-3 text-sm font-semibold text-slate-700">Recomendación del sistema: {{ $decisionLabels[$progresion['recomendacion']] ?? $progresion['recomendacion'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Materias aprobadas: {{ $progresion['materias_aprobadas'] }} · Reprobadas: {{ $progresion['materias_reprobadas'] }}</p>
                                    </div>
                                    @if ($progresion['decision_confirmada'] !== null)
                                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Confirmada: {{ $decisionLabels[$progresion['decision_confirmada']] ?? $progresion['decision_confirmada'] }}</span>
                                    @else
                                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">Pendiente de confirmación</span>
                                    @endif
                                </div>

                                <div class="grid gap-5 p-5 lg:grid-cols-[1fr_1.2fr]">
                                    <div>
                                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Resultados revisados</h3>
                                        <ul class="mt-3 space-y-2">
                                            @foreach ($progresion['inscripciones'] as $inscripcion)
                                                <li class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                                    <span class="min-w-0 truncate text-slate-700">{{ $inscripcion->materia?->nombre ?? 'Materia no disponible' }}</span>
                                                    <span class="shrink-0 font-bold {{ $inscripcion->resultado_final === 'Aprobado' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $inscripcion->resultado_final }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>

                                    <form method="POST" action="{{ route('gestion-academica.progresion.confirmar', $estudiante) }}" class="space-y-3">
                                        @csrf
                                        <div>
                                            <label for="decision_{{ $estudiante->id }}" class="mb-1 block text-sm font-semibold text-slate-700">Decisión administrativa</label>
                                            <select id="decision_{{ $estudiante->id }}" name="decision" required class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @foreach ($progresion['opciones'] as $opcion)
                                                    <option value="{{ $opcion }}" @selected($valorSeleccionado === $opcion)>{{ $decisionLabels[$opcion] ?? $opcion }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="motivo_{{ $estudiante->id }}" class="mb-1 block text-sm font-semibold text-slate-700">Motivo si cambias la recomendación</label>
                                            <textarea id="motivo_{{ $estudiante->id }}" name="motivo" rows="2" maxlength="1000" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Obligatorio solo si eliges otra decisión">{{ old('motivo', $progresion['motivo']) }}</textarea>
                                        </div>
                                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#272F7A] px-4 py-2 text-sm font-bold text-white transition hover:bg-[#4959A6]">
                                            Confirmar decisión
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </section>
                @endif
            </main>
        </div>
    </x-admin-shell>
</x-admin-layout>
