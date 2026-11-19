<div class="min-h-screen bg-gradient-to-br from-indigo-50/70 via-white to-blue-50/70">
    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] px-6 py-7 text-white shadow-2xl shadow-indigo-950/20 sm:px-8 sm:py-8">
            <div class="pointer-events-none absolute inset-0 dashboard-grid opacity-30"></div>
            <div class="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-cyan-200">{{ $esDocente ? 'Panel docente / Gestión Académica' : 'Gestión académica' }}</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ $semestre }} semestre</h1>
                <p class="mt-2 text-sm text-indigo-100/80">
                    {{ $esDocente ? 'Materias asignadas a tu perfil para esta gestión.' : 'Selecciona una materia para consultar horarios e inscripciones.' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if (! $esDocente && $gestiones->isNotEmpty())
                    <form method="GET" action="{{ route('gestion-academica.semestre', $semestre) }}" class="flex items-center gap-2">
                        <label for="gestion" class="text-xs font-bold uppercase tracking-wider text-indigo-100">Gestión</label>
                        <select id="gestion" name="gestion" onchange="this.form.submit()" class="rounded-xl border-slate-300 bg-white text-sm font-bold text-[#272F7A] shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($gestiones as $opcionGestion)
                                <option value="{{ $opcionGestion }}" class="text-[#272F7A]" @selected($gestion === $opcionGestion)>{{ $opcionGestion }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <a href="{{ $esDocente ? route('panel-docente.estudiantes') : route('gestion-academica.index') }}"
                   class="inline-flex items-center rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                    Volver a semestres
                </a>
                @if ($puedeCrearMateria)
                    <a href="{{ route('materias.create', ['semestre' => $semestre, 'gestion' => $gestion]) }}"
                       class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#272F7A] hover:bg-indigo-50">Nueva materia</a>
                @endif
            </div>
            </div>
        </header>

        @if (session('success'))
            <div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($gestion === null)
            <div role="status" class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-900">
                No hay una gestión activa. {{ $esDocente ? 'Tus materias aparecerán cuando administración inicie el próximo periodo.' : 'Administración debe iniciar un periodo para habilitar horarios e inscripciones.' }}
            </div>
        @elseif ($gestion !== $gestionVigente)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700">
                Consulta histórica de solo lectura para {{ $gestion }}. La gestión vigente es {{ $gestionVigente }}.
            </div>
        @endif

        @if ($materias->isEmpty())
            <section class="rounded-3xl border border-dashed border-indigo-200 bg-white px-6 py-14 text-center">
                <p class="text-lg font-bold text-slate-700">{{ $esDocente ? 'No tienes materias asignadas en este semestre' : 'Todavía no hay materias en este semestre' }}</p>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $esDocente ? 'Cuando administración asigne tus horarios para esta gestión, aparecerán aquí.' : 'Puedes crear una materia para comenzar a organizar el semestre.' }}
                </p>
                @if ($puedeCrearMateria)
                    <a href="{{ route('materias.create', ['semestre' => $semestre, 'gestion' => $gestion]) }}" class="mt-5 inline-flex rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white hover:bg-[#4959A6]">Crear materia</a>
                @endif
            </section>
        @else
            <section aria-label="Materias del semestre" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($materias as $materia)
                    <a href="{{ $esDocente
                        ? route('panel-docente.gestion-academica.materia', ['semestre' => $semestre, 'materia' => $materia, 'gestion' => $gestion])
                        : route('gestion-academica.materia', ['semestre' => $semestre, 'materia' => $materia, 'gestion' => $gestion]) }}"
                       class="group flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200">
                        <div class="flex items-start justify-between gap-4 bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] p-6 text-white">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-lg font-extrabold text-cyan-100 ring-1 ring-white/20" aria-hidden="true">▤</span>
                            <span class="rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold text-white ring-1 ring-white/20">{{ $gestion ?? 'En espera' }}</span>
                        </div>
                        <div class="flex flex-1 flex-col p-6 pt-5">
                        <h2 class="text-xl font-extrabold text-slate-800 group-hover:text-[#272F7A]">{{ $materia->nombre }}</h2>
                        <p class="mt-1 text-sm font-semibold text-[#4959A6]">{{ $materia->sigla ?: 'Sin sigla' }}</p>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-slate-50 px-3 py-3 group-hover:bg-indigo-50/70">
                                <p class="text-2xl font-extrabold text-slate-800">{{ $materia->horarios_count }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Horarios</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-3 group-hover:bg-indigo-50/70">
                                <p class="text-2xl font-extrabold text-slate-800">{{ $materia->inscripciones_count }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Inscritos</p>
                            </div>
                        </div>
                        <div class="mt-auto flex items-center justify-between border-t border-slate-100 pt-4">
                            <span class="text-sm font-bold text-[#4959A6] group-hover:text-[#272F7A]">Ver materia</span>
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-50 text-lg font-semibold text-[#272F7A] transition group-hover:translate-x-1 group-hover:bg-[#272F7A] group-hover:text-white" aria-hidden="true">→</span>
                        </div>
                        </div>
                    </a>
                @endforeach
            </section>
        @endif
    </main>
</div>
