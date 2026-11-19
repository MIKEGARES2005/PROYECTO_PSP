<x-admin-layout>
    <x-admin-shell>
    <div class="min-h-screen bg-slate-50">
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-[#4959A6]">Gestión académica</p>
                    <h1 class="mt-2 text-3xl font-bold text-slate-800">Inscripciones</h1>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ $gestionActiva
                            ? 'Inscripciones registradas en la gestión '.$gestionActiva->codigo.'.'
                            : 'No hay una gestión activa. El listado se habilitará al iniciar un nuevo periodo.' }}
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <a href="{{ route('gestion-academica.index') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-semibold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">
                        Volver a Gestión académica
                    </a>
                    @if (auth()->user()->isAdministrador())
                        <a href="{{ route('inscripciones.create') }}"
                           class="inline-flex items-center justify-center rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4959A6]">
                            Registrar inscripción
                        </a>
                    @endif
                </div>
            </header>

            <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-label="Filtrar inscripciones">
                <form
                    method="GET"
                    action="{{ route('inscripciones.index') }}"
                    x-data="{
                        semestre: @js($filtros['semestre'] ?? ''),
                        materiaId: @js((string) ($filtros['materia_id'] ?? '')),
                        materias: @js($materiasParaFiltro),
                        get materiasFiltradas() {
                            return this.materias.filter((materia) =>
                                this.semestre === '' || materia.semestre.toLowerCase() === this.semestre.toLowerCase()
                            );
                        },
                        limpiarMateriaSiNoCorresponde() {
                            if (this.materiaId && !this.materiasFiltradas.some((materia) => String(materia.id) === String(this.materiaId))) {
                                this.materiaId = '';
                            }
                        }
                    }"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(12rem,0.8fr)_minmax(16rem,1.2fr)_auto] lg:items-end"
                >
                    <div>
                        <label for="filtro_semestre" class="mb-1.5 block text-sm font-semibold text-slate-700">Semestre</label>
                        <select id="filtro_semestre" name="semestre" x-model="semestre" x-on:change="limpiarMateriaSiNoCorresponde()" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos los semestres</option>
                            @foreach (\App\Models\Materia::SEMESTRES as $semestre)
                                <option value="{{ $semestre }}" @selected(($filtros['semestre'] ?? '') === $semestre)>{{ $semestre }} semestre</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filtro_materia" class="mb-1.5 block text-sm font-semibold text-slate-700">Materia</label>
                        <select id="filtro_materia" name="materia_id" x-model="materiaId" x-bind:disabled="materiasFiltradas.length === 0" class="w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100">
                            <option value="" x-text="semestre ? 'Todas las materias de este semestre' : 'Todas las materias'"></option>
                            <template x-for="materia in materiasFiltradas" :key="materia.id">
                                <option x-bind:value="materia.id" x-text="`${materia.nombre} · ${materia.semestre} semestre`"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-1">
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#272F7A] px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#4959A6]">
                            Aplicar filtros
                        </button>
                        @if (filled($filtros['semestre'] ?? null) || filled($filtros['materia_id'] ?? null))
                            <a href="{{ route('inscripciones.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50">
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
                <p class="mt-3 text-xs text-slate-500">
                    Mostrando {{ $inscripciones->total() }} inscripción(es){{ $gestionActiva ? ' de '.$gestionActiva->codigo : '' }}.
                </p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[800px] text-left">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Estudiante</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Matrícula</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Materia</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Semestre</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Tipo</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Fecha</th>
                                <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($inscripciones as $inscripcion)
                                @php($estiloEstado = match (strtolower((string) $inscripcion->estado)) {
                                    'cursando' => 'bg-sky-50 text-sky-700',
                                    'aprobado' => 'bg-emerald-50 text-emerald-700',
                                    'reprobado' => 'bg-rose-50 text-rose-700',
                                    default => 'bg-slate-100 text-slate-600',
                                })
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 font-semibold text-slate-800">
                                        {{ trim(($inscripcion->estudiante?->nombres ?? '').' '.($inscripcion->estudiante?->apellido_paterno ?? '').' '.($inscripcion->estudiante?->apellido_materno ?? '')) ?: 'Estudiante no disponible' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $inscripcion->estudiante?->matricula ?? '—' }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $inscripcion->materia?->nombre ?? 'Materia no disponible' }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $inscripcion->materia?->semestre_nombre ?? '—' }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $inscripcion->tipo }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $inscripcion->fecha_inscripcion?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-6 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $estiloEstado }}">
                                            {{ $inscripcion->estado }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500">
                                        {{ $gestionActiva
                                            ? 'No hay inscripciones registradas en esta gestión.'
                                            : 'No hay inscripciones para mostrar mientras el sistema está en espera.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-6 py-4">{{ $inscripciones->links() }}</div>
            </section>
        </main>
    </div>
    </x-admin-shell>
</x-admin-layout>
