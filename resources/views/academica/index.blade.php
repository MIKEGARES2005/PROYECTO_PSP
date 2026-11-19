<x-admin-layout>
    <x-admin-shell>
    <div class="min-h-screen bg-slate-50">
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold text-[#4959A6]">Académica</p>
                    <h1 class="mt-2 text-3xl font-bold text-slate-800">Gestión académica</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-500">
                        Selecciona un semestre para consultar sus materias, docentes, horarios e inscripciones del periodo vigente.
                    </p>
                </div>
                <div class="flex flex-col items-stretch gap-3 self-start sm:self-auto">
                    <div class="inline-flex items-center gap-3 rounded-2xl border border-indigo-100 bg-white px-4 py-3 shadow-sm">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-[#272F7A]" aria-hidden="true">◷</span>
                        <span>
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Estado académico</span>
                            <span class="mt-0.5 block text-lg font-bold text-slate-800">{{ $gestion ?? 'En espera' }}</span>
                        </span>
                    </div>
                    @if ($esAdministrador && $gestion !== null)
                        <a href="{{ route('inscripciones.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-indigo-200 bg-white px-4 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">
                            Consultar inscripciones
                        </a>
                    @endif
                </div>
            </header>

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if ($gestion === null)
                <section class="rounded-3xl border border-sky-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="max-w-3xl">
                        <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-sky-800">Sistema en espera</span>
                        @if ($esAdministrador)
                            <h2 class="mt-3 text-xl font-black text-slate-800">Iniciar el siguiente periodo</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Administración decide cuándo comienzan las clases. Selecciona el periodo y escribe el año; las fechas se asignarán automáticamente.</p>
                            <form method="POST" action="{{ route('gestion-academica.iniciar') }}" class="mt-5 grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                                @csrf
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="periodo" class="mb-2 block text-sm font-semibold text-slate-700">Periodo</label>
                                        <select id="periodo" name="periodo" required class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="1" @selected(old('periodo') === '1')>I · Febrero a julio</option>
                                            <option value="2" @selected(old('periodo') === '2')>II · Agosto a noviembre</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="anio" class="mb-2 block text-sm font-semibold text-slate-700">Año</label>
                                        <input id="anio" name="anio" type="number" min="2024" max="2050" value="{{ old('anio', now()->year) }}" required class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                </div>
                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#272F7A] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#4959A6]">Iniciar gestión</button>
                            </form>
                        @else
                            <h2 class="mt-3 text-xl font-black text-slate-800">En espera de una nueva gestión</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">La siguiente gestión se habilitará cuando administración decida iniciar el periodo de clases.</p>
                        @endif
                    </div>
                </section>
            @else
                <section x-data="{ confirmar: false, confirmarGeneracion: false, semestreInscripciones: @js($vistaPreviaInscripciones['resumen'][0]['nombre'] ?? '') }" x-on:keydown.escape.window="confirmar = false; confirmarGeneracion = false" class="rounded-3xl border border-indigo-100 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-lg font-black text-slate-800">Cierre de {{ $gestion }}</h2>
                                <span class="rounded-full px-3 py-1 text-xs font-black {{ $cierre['todos_revisados'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $cierre['inscripciones_pendientes'] }} inscripciones pendientes
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-black {{ $cierre['decisiones_pendientes'] === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $cierre['decisiones_pendientes'] }} progresiones por confirmar
                                </span>
                            </div>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Primero se revisan las notas y luego administración confirma o ajusta la progresión de cada estudiante. El cierre aplica esas decisiones, archiva las notas y deja el sistema en espera.</p>
                        </div>
                        @if ($esAdministrador)
                            <form method="POST" action="{{ route('gestion-academica.cerrar') }}" @submit.prevent="confirmar = true">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded-xl px-5 py-3 text-sm font-black shadow-sm transition {{ $cierre['todos_revisados'] ? 'bg-[#272F7A] text-white hover:bg-[#4959A6]' : 'cursor-not-allowed bg-slate-100 text-slate-400' }}" {{ $cierre['todos_revisados'] ? '' : 'disabled' }}>
                                    Concluir gestión
                                </button>
                            </form>
                        @endif
                    </div>
                    @if ($esAdministrador)
                        <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                        <button type="button" @click="confirmarGeneracion = true" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#272F7A] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#4959A6]">
                            Generar inscripciones
                        </button>
                        <a href="{{ route('gestion-academica.progresion') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-indigo-200 bg-white px-4 py-2 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">
                            Revisar progresión por estudiante
                        </a>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Genera las inscripciones después de programar horarios activos para todas las materias requeridas.</p>
                    @endif
                    @if (! $cierre['todos_revisados'])
                        @php
                            $semestresPendientes = collect($cierre['semestres'])
                                ->filter(fn (array $semestre): bool => $semestre['revisadas'] < $semestre['total'])
                                ->pluck('nombre')
                                ->implode(', ');
                        @endphp
                        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                            @if ($cierre['inscripciones_pendientes'] > 0)
                                Falta revisar {{ $cierre['inscripciones_pendientes'] }} inscripción(es) y registrar su resultado final.
                            @else
                                Todos los resultados están revisados; falta confirmar {{ $cierre['decisiones_pendientes'] }} decisión(es) de progresión.
                            @endif
                            @if ($semestresPendientes !== '')
                                Semestres con materias pendientes: {{ $semestresPendientes }}.
                            @endif
                            @if (! $esAdministrador)
                                Administración podrá concluir la gestión cuando estén revisadas.
                            @endif
                        </p>
                    @elseif (! $esAdministrador)
                        <p class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status">
                            Todas las inscripciones tienen resultado final. La gestión permanece activa hasta que administración decida concluirla.
                        </p>
                    @endif
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($cierre['semestres'] as $resumenCierre)
                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-3">
                                <div class="flex items-center justify-between gap-2 text-xs font-black text-slate-700"><span>{{ $resumenCierre['nombre'] }}</span><span>{{ $resumenCierre['porcentaje'] }}%</span></div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full {{ $resumenCierre['porcentaje'] === 100 ? 'bg-emerald-500' : 'bg-amber-400' }}" style="width: {{ $resumenCierre['porcentaje'] }}%"></div></div>
                                <p class="mt-2 text-[11px] text-slate-500">{{ $resumenCierre['revisadas'] }}/{{ $resumenCierre['total'] }} materias revisadas</p>
                            </div>
                        @endforeach
                    </div>
                    @if ($esAdministrador)
                    <div x-cloak x-show="confirmar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true">
                        <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                            <h3 class="text-lg font-black text-slate-900">Confirmar archivo y cierre</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Las inscripciones de {{ $gestion }} se copiarán al historial y se quitarán de las inscripciones activas. Se aplicará la progresión confirmada para cada estudiante. Después, inicia la nueva gestión, prepara sus horarios y genera las inscripciones normales y de arrastre.</p>
                            <div class="mt-5 flex justify-end gap-3"><button type="button" @click="confirmar = false" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700">Cancelar</button><button type="button" @click="$root.querySelector('form').submit()" class="rounded-xl bg-[#272F7A] px-4 py-2.5 text-sm font-bold text-white">Confirmar cierre</button></div>
                        </div>
                    </div>
                    <template x-teleport="body">
                    <div x-cloak x-show="confirmarGeneracion" x-effect="document.body.classList.toggle('overflow-hidden', confirmarGeneracion)" class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto overscroll-contain bg-slate-950/60 p-3 backdrop-blur-sm sm:p-5" role="dialog" aria-modal="true" aria-labelledby="vista-previa-inscripciones-titulo" @click.self="confirmarGeneracion = false">
                        <section class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl sm:rounded-3xl">
                            <header class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 px-4 py-3.5 sm:px-6 sm:py-4">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-black uppercase tracking-wide text-indigo-600">Revisión previa · {{ $gestion }}</p>
                                    <h3 id="vista-previa-inscripciones-titulo" class="mt-1 text-lg font-black leading-tight text-slate-900 sm:text-xl">Inscripciones por semestre</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-500 sm:text-sm">Selecciona un semestre para revisar sus estudiantes y materias. Los arrastres indican el semestre al que pertenecen.</p>
                                </div>
                                <button type="button" @click="confirmarGeneracion = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Cerrar vista previa">
                                    <span aria-hidden="true" class="text-2xl leading-none">&times;</span>
                                </button>
                            </header>

                            <div class="grid shrink-0 grid-cols-2 gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3 sm:grid-cols-4 sm:gap-3 sm:px-6">
                                <div class="rounded-xl border border-slate-100 bg-white px-3 py-2"><p class="text-lg font-black text-slate-800">{{ $vistaPreviaInscripciones['total_estudiantes'] }}</p><p class="text-[11px] leading-4 text-slate-500">Estudiantes</p></div>
                                <div class="rounded-xl border border-slate-100 bg-white px-3 py-2"><p class="text-lg font-black text-indigo-700">{{ $vistaPreviaInscripciones['total_nuevas'] }}</p><p class="text-[11px] leading-4 text-slate-500">Inscripciones nuevas</p></div>
                                <div class="rounded-xl border border-slate-100 bg-white px-3 py-2"><p class="text-lg font-black text-slate-700">{{ $vistaPreviaInscripciones['nuevas_normales'] }}</p><p class="text-[11px] leading-4 text-slate-500">Materias regulares</p></div>
                                <div class="rounded-xl border border-slate-100 bg-white px-3 py-2"><p class="text-lg font-black text-amber-700">{{ $vistaPreviaInscripciones['nuevos_arrastres'] }}</p><p class="text-[11px] leading-4 text-slate-500">Materias de arrastre</p></div>
                            </div>

                            @if (count($vistaPreviaInscripciones['resumen']) > 0)
                                <nav aria-label="Filtrar por semestre" class="flex shrink-0 gap-2 overflow-x-auto border-b border-slate-100 px-4 py-2.5 sm:px-6">
                                    @foreach ($vistaPreviaInscripciones['resumen'] as $indice => $grupo)
                                        <button
                                            id="tab-semestre-{{ $indice }}"
                                            type="button"
                                            aria-controls="panel-semestre-{{ $indice }}"
                                            :aria-pressed="semestreInscripciones === @js($grupo['nombre'])"
                                            @click="semestreInscripciones = @js($grupo['nombre'])"
                                            :class="semestreInscripciones === @js($grupo['nombre']) ? 'border-indigo-700 bg-indigo-50 text-indigo-800' : 'border-transparent bg-slate-50 text-slate-600 hover:bg-slate-100'"
                                            class="shrink-0 rounded-xl border px-3.5 py-2 text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
                                        >
                                            {{ $grupo['nombre'] }}
                                            <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[11px]">{{ count($grupo['estudiantes']) }}</span>
                                        </button>
                                    @endforeach
                                </nav>
                            @endif

                            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain p-3 sm:p-5">
                                @forelse ($vistaPreviaInscripciones['resumen'] as $indice => $grupo)
                                    <section
                                        id="panel-semestre-{{ $indice }}"
                                        x-cloak
                                        x-show="semestreInscripciones === @js($grupo['nombre'])"
                                        role="region"
                                        aria-labelledby="tab-semestre-{{ $indice }}"
                                        class="space-y-3"
                                    >
                                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-indigo-50 px-3.5 py-2.5">
                                            <h4 class="font-black text-indigo-950">{{ $grupo['nombre'] }} semestre</h4>
                                            <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-indigo-700">{{ count($grupo['estudiantes']) }} estudiantes</span>
                                        </div>
                                        @foreach ($grupo['estudiantes'] as $estudiante)
                                            <article class="grid min-w-0 gap-3 rounded-xl border border-slate-200 p-3.5 sm:p-4 md:grid-cols-[minmax(10rem,0.8fr)_2fr]">
                                                <div class="min-w-0">
                                                    <p class="break-words text-sm font-bold text-slate-800">{{ $estudiante['nombre'] !== '' ? $estudiante['nombre'] : 'Nombre no disponible' }}</p>
                                                    <p class="mt-0.5 break-all text-xs text-slate-500">Matrícula: {{ $estudiante['matricula'] !== '' ? $estudiante['matricula'] : 'Sin matrícula' }}</p>
                                                </div>
                                                @if (count($estudiante['materias']) > 0)
                                                    <ul class="grid min-w-0 gap-2 sm:grid-cols-2">
                                                        @foreach ($estudiante['materias'] as $materia)
                                                            <li class="min-w-0 rounded-lg border px-3 py-2 {{ $materia['tipo'] === 'Arrastre' ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' }}">
                                                                <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                                                                    <span class="min-w-0 break-words text-xs font-bold text-slate-800">{{ $materia['nombre'] }}</span>
                                                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $materia['tipo'] === 'Arrastre' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800' }}">{{ $materia['tipo'] }}</span>
                                                                </div>
                                                                @if ($materia['tipo'] === 'Arrastre')
                                                                    <div class="mt-1 flex flex-wrap gap-1.5 text-[10px]">
                                                                        <span class="text-slate-500">Semestre de origen: {{ $materia['semestre'] }}</span>
                                                                    </div>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <p class="text-sm text-slate-500">No hay materias activas configuradas para este estudiante.</p>
                                                @endif
                                            </article>
                                        @endforeach
                                    </section>
                                @empty
                                    <p class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">No hay estudiantes disponibles para esta generación.</p>
                                @endforelse

                                @if ($vistaPreviaInscripciones['todos_ya_inscritos'])
                                    <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-5 text-emerald-900">
                                        Todos los estudiantes ya tienen sus materias requeridas inscritas. No hay inscripciones nuevas para generar.
                                    </p>
                                @elseif ($vistaPreviaInscripciones['total_existentes'] > 0)
                                    <p class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs leading-5 text-sky-900">
                                        Las {{ $vistaPreviaInscripciones['total_existentes'] }} inscripción(es) existentes no aparecen en la lista y no se volverán a crear.
                                    </p>
                                @endif
                                @foreach ($vistaPreviaInscripciones['advertencias'] as $advertencia)
                                    <p role="alert" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">{{ $advertencia }}</p>
                                @endforeach
                            </div>

                            <footer class="flex shrink-0 flex-col-reverse gap-2 border-t border-slate-100 bg-white p-3 sm:flex-row sm:justify-end sm:p-4">
                                <button type="button" @click="confirmarGeneracion = false" class="rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-200">Revisar después</button>
                                <form method="POST" action="{{ route('gestion-academica.inscripciones.generar') }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-xl px-5 py-3 text-sm font-black text-white shadow-sm transition sm:w-auto {{ $vistaPreviaInscripciones['puede_generar'] ? 'bg-[#272F7A] hover:bg-[#4959A6]' : 'cursor-not-allowed bg-slate-300' }}" {{ $vistaPreviaInscripciones['puede_generar'] ? '' : 'disabled' }}>
                                        Continuar y generar
                                    </button>
                                </form>
                            </footer>
                        </section>
                    </div>
                    </template>
                    @endif
                </section>
            @endif

            @if ($gestionesArchivadas->isNotEmpty())
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-800">Historial de gestiones cerradas</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($gestionesArchivadas as $gestionArchivada)
                            <a href="{{ route('gestion-academica.historial', $gestionArchivada->gestion) }}" class="rounded-xl bg-indigo-50 px-4 py-2 text-sm font-bold text-[#272F7A] ring-1 ring-indigo-100 transition hover:bg-indigo-100">{{ $gestionArchivada->gestion }}</a>
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="mb-5 flex flex-col gap-1">
                <h2 class="text-xl font-bold text-slate-800">Semestres del laboratorio</h2>
                <p class="text-sm text-slate-500">Elige una tarjeta para abrir su información académica.</p>
            </div>
            <div class="mb-6 flex gap-3 rounded-2xl border border-indigo-100 bg-indigo-50 px-4 py-4 text-sm text-indigo-900">
                <span class="mt-0.5 text-lg" aria-hidden="true">ⓘ</span>
                <p>
                Los resultados reportados por el docente requieren revisión administrativa. El avance, la repetición y la condición de arrastre se aplican al cerrar la gestión.
                </p>
            </div>

            <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3" aria-label="Seleccionar semestre">
                @foreach ($semestres as $indice => $resumen)
                    <a href="{{ route('gestion-academica.semestre', ['semestre' => $resumen['nombre'], 'gestion' => $gestion]) }}"
                       class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#272F7A] via-indigo-500 to-cyan-400"></div>
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100 text-xl font-extrabold text-[#272F7A] ring-1 ring-indigo-100">
                                0{{ $indice + 1 }}
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-100">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ $gestion }}
                            </span>
                        </div>
                        <h3 class="mt-5 text-2xl font-extrabold tracking-tight text-slate-800 group-hover:text-[#272F7A]">{{ $resumen['nombre'] }} semestre</h3>
                        <p class="mt-2 min-h-10 text-sm leading-5 text-slate-500">
                            @if (auth()->user()->isDocente())
                                Consulta tus materias, horarios y estudiantes asignados.
                            @else
                                Administra las materias, horarios e inscripciones de este semestre.
                            @endif
                        </p>
                        <div class="mt-6 grid grid-cols-3 gap-2">
                            <div class="rounded-xl bg-slate-50 px-3 py-3 transition group-hover:bg-indigo-50/70">
                                <p class="text-2xl font-extrabold text-slate-800">{{ $resumen['materias'] }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Materias</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-3 transition group-hover:bg-indigo-50/70">
                                <p class="text-2xl font-extrabold text-slate-800">{{ $resumen['horarios'] }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Horarios</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-3 transition group-hover:bg-indigo-50/70">
                                <p class="text-2xl font-extrabold text-slate-800">{{ $resumen['inscripciones'] }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Inscritos</p>
                            </div>
                        </div>
                        <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                            <span class="text-sm font-bold text-[#4959A6] group-hover:text-[#272F7A]">Abrir semestre</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-50 text-lg font-semibold text-[#272F7A] transition group-hover:translate-x-1 group-hover:bg-[#272F7A] group-hover:text-white" aria-hidden="true">→</span>
                        </div>
                    </a>
                @endforeach
            </section>
        </main>
    </div>
    </x-admin-shell>
</x-admin-layout>
