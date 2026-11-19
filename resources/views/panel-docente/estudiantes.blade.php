<x-docente-layout>
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-[#4959A6]">Panel docente / Académico</p>
            <h2 class="mt-1 text-3xl font-bold text-slate-800">Gestión Académica</h2>
            <p class="mt-2 text-sm text-slate-500">Selecciona uno de tus semestres para consultar las materias que tienes asignadas.</p>
        </div>
        <span class="inline-flex w-fit rounded-xl bg-indigo-100 px-4 py-2 text-sm font-bold text-[#272F7A]">
            {{ $gestion === null ? 'Sistema en espera' : 'Gestión '.$gestion }}
        </span>
    </section>

    @if ($gestion === null)
        <div role="status" class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-900">
            No hay una gestión activa. Tus semestres y materias aparecerán cuando administración inicie el próximo periodo.
        </div>
    @elseif ($semestres->isEmpty())
        <div class="rounded-2xl border border-dashed border-indigo-200 bg-white px-6 py-14 text-center">
            <p class="text-lg font-bold text-slate-700">Aún no tienes materias asignadas</p>
            <p class="mt-2 text-sm text-slate-500">Cuando administración asigne tus horarios para esta gestión, aquí aparecerán los semestres correspondientes.</p>
        </div>
    @else
        <section aria-label="Semestres con materias asignadas" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($semestres as $semestre)
                <a href="{{ route('panel-docente.gestion-academica.semestre', ['semestre' => $semestre['nombre'], 'gestion' => $gestion]) }}"
                   class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200">
                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#272F7A] via-indigo-500 to-cyan-400"></div>
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100 text-xl font-extrabold text-[#272F7A] ring-1 ring-indigo-100">
                            0{{ $semestre['numero'] }}
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-100">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            {{ $gestion }}
                        </span>
                    </div>
                    <h3 class="mt-5 text-2xl font-extrabold tracking-tight text-slate-800 group-hover:text-[#272F7A]">{{ $semestre['nombre'] }} semestre</h3>
                    <p class="mt-2 text-sm leading-5 text-slate-500">
                        {{ $semestre['materias']->count() }} {{ $semestre['materias']->count() === 1 ? 'materia asignada' : 'materias asignadas' }}
                    </p>
                    <ul class="mt-5 grid gap-2">
                        @foreach ($semestre['materias'] as $materia)
                            <li class="rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 transition group-hover:bg-indigo-50/70">
                                @if ($materia->sigla)
                                    <span class="font-extrabold text-[#4959A6]">{{ $materia->sigla }} · </span>
                                @endif
                                {{ $materia->nombre }}
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                        <span class="text-sm font-bold text-[#4959A6] group-hover:text-[#272F7A]">Abrir semestre</span>
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-50 text-lg font-semibold text-[#272F7A] transition group-hover:translate-x-1 group-hover:bg-[#272F7A] group-hover:text-white" aria-hidden="true">→</span>
                    </div>
                </a>
            @endforeach
        </section>
    @endif
</x-docente-layout>
