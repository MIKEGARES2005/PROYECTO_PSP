<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Mi seguimiento · {{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-emerald-50 font-sans antialiased text-slate-800">
        <header class="border-b border-emerald-100 bg-white/90 shadow-sm">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700" aria-hidden="true">
                        <x-icon name="book" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Portal del estudiante</p>
                        <p class="truncate text-sm font-black text-slate-800">Mi seguimiento estudiantil</p>
                    </div>
                </div>
                <a href="{{ route('estudiante.dashboard') }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2.5 text-xs font-bold text-emerald-800 ring-1 ring-emerald-200 transition hover:bg-emerald-100 sm:px-4 sm:text-sm">
                    <span aria-hidden="true">←</span>
                    <span>Volver al panel</span>
                </a>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-7 px-4 py-7 sm:px-6 sm:py-9">
            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-800 via-emerald-700 to-teal-600 p-6 text-white shadow-xl shadow-emerald-900/10 sm:p-8">
                <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full border-[28px] border-white/10"></div>
                <div class="pointer-events-none absolute -bottom-28 right-36 h-56 w-56 rounded-full bg-teal-300/10 blur-2xl"></div>
                <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold text-emerald-50 ring-1 ring-white/20">
                            <span class="h-2 w-2 rounded-full {{ $gestion ? 'bg-emerald-300' : 'bg-amber-300' }}"></span>
                            {{ $gestion ?? 'En espera' }}
                        </span>
                        <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">Tu progreso académico</h1>
                        <p class="mt-2 text-sm text-emerald-50 sm:text-base">Consulta tus materias, calificaciones y horarios en un solo lugar.</p>
                    </div>
                    <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15 backdrop-blur-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-100">Estudiante</p>
                        <p class="mt-1 text-sm font-bold text-white">{{ $student->nombreCompleto() }}</p>
                    </div>
                </div>
            </section>

            @if ($gestion === null)
                <div role="status" class="flex gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm leading-6 text-sky-900 shadow-sm">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100 font-black text-sky-700" aria-hidden="true">i</span>
                    <p><span class="font-black">Sistema en espera.</span> Tus materias y horarios estarán disponibles cuando administración inicie y prepare la siguiente gestión.</p>
                </div>
            @endif

            @if ($decisionProgresion !== null)
                @php
                    $decisionLabels = [
                        'avanza' => 'Avanzas al siguiente semestre',
                        'avanza_con_arrastres' => 'Avanzas con materias de arrastre',
                        'repite_semestre' => 'Repites el semestre',
                        'retiro' => 'Egresado: finalizaste cuarto semestre',
                        'pendiente_materias_cuarto' => 'Continúas pendiente en cuarto semestre',
                        'sin_cambio' => 'Mantienes tu estado actual',
                    ];
                @endphp
                <section class="relative overflow-hidden rounded-2xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-white p-5 shadow-sm sm:p-6" aria-label="Decisión final de progresión">
                    <div class="absolute inset-y-0 left-0 w-1.5 bg-indigo-500"></div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-indigo-600">Decisión administrativa final</p>
                            <p class="mt-2 text-lg font-black text-indigo-950">{{ $decisionLabels[$decisionProgresion->decision_confirmada] ?? $decisionProgresion->decision_confirmada }}</p>
                        </div>
                        <span class="w-fit rounded-xl bg-white px-3 py-2 text-xs font-bold text-indigo-800 ring-1 ring-indigo-200">Gestión {{ $decisionProgresion->gestion }}</span>
                    </div>
                    @if ($decisionProgresion->motivo)
                        <p class="mt-4 border-t border-indigo-100 pt-3 text-sm leading-6 text-indigo-800"><span class="font-bold">Motivo registrado:</span> {{ $decisionProgresion->motivo }}</p>
                    @endif
                </section>
            @endif

            @php
                $materiasAprobadas = $inscripciones->where('resultado_final', 'Aprobado')->count();
                $materiasReprobadas = $inscripciones->where('resultado_final', 'Reprobado')->count();
                $materiasEnCurso = $inscripciones->where('estado', 'Cursando')->count();
            @endphp
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4" aria-label="Resumen académico">
                @foreach ([
                    ['label' => 'Materias inscritas', 'value' => $inscripciones->count(), 'valueClass' => 'text-emerald-700'],
                    ['label' => 'En curso', 'value' => $materiasEnCurso, 'valueClass' => 'text-sky-700'],
                    ['label' => 'Aprobadas', 'value' => $materiasAprobadas, 'valueClass' => 'text-teal-700'],
                    ['label' => 'Reprobadas', 'value' => $materiasReprobadas, 'valueClass' => 'text-amber-700'],
                ] as $resumen)
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-xs">{{ $resumen['label'] }}</p>
                        <p class="mt-2 text-3xl font-black tracking-tight {{ $resumen['valueClass'] }}">{{ $resumen['value'] }}</p>
                    </article>
                @endforeach
            </section>

            <section class="space-y-4" aria-label="Materias y calificaciones">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">Tu gestión</p>
                        <h2 class="mt-1 text-xl font-black text-slate-900 sm:text-2xl">Materias y calificaciones</h2>
                    </div>
                    <p class="text-xs font-semibold text-slate-500">{{ $gestion ?? 'Sin gestión activa' }}</p>
                </div>

                @forelse ($inscripciones as $inscripcion)
                    @php
                        $horario = $inscripcion->materia?->horarios->first();
                        $resultado = $inscripcion->resultado_final ?: 'Cursando';
                        $resultadoClases = match ($resultado) {
                            'Aprobado' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                            'Reprobado' => 'bg-rose-50 text-rose-800 ring-rose-200',
                            default => 'bg-sky-50 text-sky-800 ring-sky-200',
                        };
                    @endphp
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-emerald-200 hover:shadow-md">
                        <div class="flex flex-col gap-4 border-b border-slate-100 bg-gradient-to-r from-emerald-50/80 to-white p-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-xs font-black text-emerald-800" aria-hidden="true">{{ $inscripcion->materia?->sigla ?: 'MAT' }}</span>
                                <div class="min-w-0">
                                    <h3 class="text-base font-black leading-6 text-slate-900 sm:text-lg">{{ $inscripcion->materia?->nombre ?: 'Materia sin nombre' }}</h3>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $inscripcion->materia?->semestre ?: 'Semestre no disponible' }} · {{ $inscripcion->materia?->sigla ?: 'Sin sigla' }}</p>
                                </div>
                            </div>
                            <span class="w-fit shrink-0 rounded-full px-3 py-1.5 text-xs font-black ring-1 {{ $resultadoClases }}">{{ $resultado }}</span>
                        </div>

                        <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[1fr_1.2fr]">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Docente</p>
                                    <p class="mt-1.5 text-sm font-bold text-slate-800">{{ $horario?->docente?->nombreCompleto() ?: 'Pendiente de asignación' }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Horario y aula</p>
                                    @if ($horario)
                                        <p class="mt-1.5 text-sm font-bold text-slate-800">{{ \App\Models\Horario::DIAS[$horario->dia_semana] ?? $horario->dia_semana }} · {{ substr((string) $horario->hora_inicio, 0, 5) }}–{{ substr((string) $horario->hora_fin, 0, 5) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Aula {{ $horario->aula ?: '—' }}</p>
                                    @else
                                        <p class="mt-1.5 text-sm font-semibold text-slate-500">Sin horario registrado</p>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <p class="mb-3 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Calificaciones</p>
                                <div class="grid grid-cols-3 gap-2 sm:gap-3">
                                    @foreach ([['label' => 'P1:', 'value' => $inscripcion->parcial_1], ['label' => 'P2:', 'value' => $inscripcion->parcial_2], ['label' => 'Final:', 'value' => $inscripcion->nota_final]] as $nota)
                                        <div class="rounded-xl border border-slate-200 bg-white p-3 text-center sm:p-4">
                                            <p class="text-sm font-bold text-slate-500">{{ $nota['label'] }} <span class="font-black text-slate-900">{{ $nota['value'] ?? '—' }}</span></p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center shadow-sm">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-xl font-black text-slate-500" aria-hidden="true">—</span>
                        <h3 class="mt-4 text-base font-black text-slate-800">Aún no hay materias para mostrar</h3>
                        <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-slate-500">Tus inscripciones, calificaciones y horarios aparecerán aquí cuando estén disponibles para la gestión vigente.</p>
                    </div>
                @endforelse
            </section>
        </main>
    </body>
</html>
