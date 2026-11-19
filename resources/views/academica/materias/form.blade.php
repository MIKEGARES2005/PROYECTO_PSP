<x-admin-layout>
    <x-admin-shell>
        @php
            $urlRetorno = $materia->exists
                ? route('gestion-academica.materia', ['semestre' => $materia->semestre, 'materia' => $materia, 'gestion' => $gestion])
                : ($semestreSeleccionado
                    ? route('gestion-academica.semestre', ['semestre' => $semestreSeleccionado, 'gestion' => $gestion])
                    : route('gestion-academica.index', ['gestion' => $gestion]));
        @endphp
        <div class="min-h-screen bg-[#F4F7FC] text-slate-800">
            <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <header class="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] px-6 py-7 text-white shadow-2xl shadow-indigo-950/20 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-8">
                    <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl" aria-hidden="true"></div>
                    <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-30" aria-hidden="true"></div>
                    <div class="relative">
                        <p class="text-sm font-medium text-cyan-200">Administración / Gestión académica / Materias</p>
                        <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">{{ $materia->exists ? 'Editar materia' : 'Crear materia' }}</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100/80">Completa los datos académicos para organizar esta materia en el plan de estudios.</p>
                    </div>
                    <a href="{{ $urlRetorno }}"
                       class="relative inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50 hover:text-[#4959A6]">
                        {{ $materia->exists ? 'Volver a la materia' : 'Volver al semestre' }}
                    </a>
                </header>

                @if ($errors->any())
                    <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p class="font-semibold">No se pudo guardar la materia. Revisa estos campos:</p>
                        <ul class="mt-2 list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST"
                      action="{{ $materia->exists ? route('materias.update', $materia) : route('materias.store') }}"
                      class="dashboard-rise space-y-6 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
                    @csrf
                    @if ($materia->exists)
                        @method('PUT')
                    @endif
                    <input type="hidden" name="gestion" value="{{ old('gestion', $gestion) }}">

                    <section class="space-y-5">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">Información de la materia</h2>
                            <p class="mt-1 text-sm text-slate-500">Define su nombre, sigla y semestre correspondiente.</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="nombre" class="mb-2 block text-sm font-semibold text-slate-700">Nombre de la materia</label>
                                <input id="nombre" name="nombre" value="{{ old('nombre', $materia->nombre) }}" required maxlength="150"
                                       class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                @error('nombre')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="sigla" class="mb-2 block text-sm font-semibold text-slate-700">Sigla</label>
                                <input id="sigla" name="sigla" value="{{ old('sigla', $materia->sigla) }}" maxlength="20" placeholder="Opcional, ej. PROG-I"
                                       class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm uppercase focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <p class="mt-1 text-xs text-slate-500">Opcional. Si la agregas, debe ser única.</p>
                                @error('sigla')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="semestre" class="mb-2 block text-sm font-semibold text-slate-700">Semestre</label>
                                <select id="semestre" name="semestre" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                    <option value="">Selecciona un semestre</option>
                                    @foreach ($semestres as $opcionSemestre)
                                        <option value="{{ $opcionSemestre }}" @selected(old('semestre', $materia->semestre ?: $semestreSeleccionado) === $opcionSemestre)>{{ $opcionSemestre }}</option>
                                    @endforeach
                                </select>
                                @error('semestre')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ $urlRetorno }}"
                           class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                            Cancelar
                        </a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-[#1E2A5A] to-[#272F7A] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:from-[#272F7A] hover:to-[#4959A6]">
                            {{ $materia->exists ? 'Guardar cambios' : 'Crear materia' }}
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </x-admin-shell>
</x-admin-layout>
