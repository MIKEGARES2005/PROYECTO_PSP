<x-admin-layout>
    <x-admin-shell>
        <div class="min-h-screen bg-[#F4F7FC] text-slate-800">
            <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <header class="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] px-6 py-7 text-white shadow-2xl shadow-indigo-950/20 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-8">
                    <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl" aria-hidden="true"></div>
                    <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-30" aria-hidden="true"></div>
                    <div class="relative">
                        <p class="text-sm font-medium text-cyan-200">Administración / Gestión académica / Inscripciones</p>
                        <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Registrar inscripción</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100/80">Relaciona al estudiante con una materia y completa los datos de su inscripción.</p>
                    </div>
                    <a href="{{ $semestre ? route('gestion-academica.semestre', ['semestre' => $semestre, 'gestion' => $inscripcion->gestion]) : route('gestion-academica.index', ['gestion' => $inscripcion->gestion]) }}"
                       class="relative inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50 hover:text-[#4959A6]">
                        Volver a Gestión académica
                    </a>
                </header>

                @if ($errors->any())
                    <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p class="font-semibold">No se pudo guardar la inscripción. Revisa estos campos:</p>
                        <ul class="mt-2 list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST"
                      action="{{ route('inscripciones.store') }}"
                      class="dashboard-rise space-y-6 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
                    @csrf

                    <section class="space-y-5">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">Datos de inscripción</h2>
                            <p class="mt-1 text-sm text-slate-500">Selecciona la gestión, el estudiante y la materia que cursará.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="gestion" class="mb-2 block text-sm font-semibold text-slate-700">Gestión académica</label>
                                <input id="gestion" name="gestion" value="{{ old('gestion', $inscripcion->gestion) }}" required
                                       pattern="(I|II)-[0-9]{4}" placeholder="Ej. II-2026"
                                       class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                <p class="mt-1 text-xs text-slate-500">Formato: periodo y año, por ejemplo I-2026 o II-2026.</p>
                                @error('gestion')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="tipo" class="mb-2 block text-sm font-semibold text-slate-700">Tipo de inscripción</label>
                                <select id="tipo" name="tipo" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                    <option value="">Selecciona un tipo</option>
                                    @foreach (['Normal', 'Arrastre'] as $tipo)
                                        <option value="{{ $tipo }}" @selected(old('tipo', $inscripcion->tipo) === $tipo)>{{ $tipo }}</option>
                                    @endforeach
                                </select>
                                @error('tipo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="estudiante_id" class="mb-2 block text-sm font-semibold text-slate-700">Estudiante</label>
                                <select id="estudiante_id" name="estudiante_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                    <option value="">Selecciona un estudiante</option>
                                    @foreach ($estudiantes as $estudiante)
                                        <option value="{{ $estudiante->id }}" @selected(old('estudiante_id', $inscripcion->estudiante_id) == $estudiante->id)>
                                            {{ $estudiante->nombres }} {{ $estudiante->apellido_paterno }} {{ $estudiante->apellido_materno }} · {{ $estudiante->matricula }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('estudiante_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="materia_id" class="mb-2 block text-sm font-semibold text-slate-700">Materia</label>
                                <select id="materia_id" name="materia_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                    <option value="">Selecciona una materia</option>
                                    @foreach ($materias as $materia)
                                        <option value="{{ $materia->id }}" @selected(old('materia_id', $inscripcion->materia_id) == $materia->id)>
                                            {{ $materia->sigla ? $materia->sigla.' · ' : '' }}{{ $materia->nombre }} · {{ $materia->semestre }}{{ strtolower($materia->estado) === 'activo' || strtolower($materia->estado) === 'activa' ? '' : ' (inactiva)' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('materia_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="fecha_inscripcion" class="mb-2 block text-sm font-semibold text-slate-700">Fecha de inscripción</label>
                                <input id="fecha_inscripcion" name="fecha_inscripcion" type="date"
                                       value="{{ old('fecha_inscripcion', $inscripcion->fecha_inscripcion?->format('Y-m-d')) }}"
                                       required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                                @error('fecha_inscripcion')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ $semestre ? route('gestion-academica.semestre', ['semestre' => $semestre, 'gestion' => $inscripcion->gestion]) : route('gestion-academica.index', ['gestion' => $inscripcion->gestion]) }}"
                           class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                            Cancelar
                        </a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-[#1E2A5A] to-[#272F7A] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:from-[#272F7A] hover:to-[#4959A6]">
                            Registrar inscripción
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </x-admin-shell>
</x-admin-layout>
