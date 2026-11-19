<x-admin-shell>
    <main class="min-h-screen bg-gradient-to-br from-indigo-50/70 via-white to-blue-50/70">
        <div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-[#4959A6]">Administración / Análisis</p>
                    <h1 class="mt-1 text-3xl font-bold text-slate-800">Reportes del sistema</h1>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ $esAdministrador ? 'Resumen del inventario, actividad institucional y resultados académicos por gestión.' : 'Resumen de tus asignaciones e incidencias vinculadas.' }}
                    </p>
                </div>
                @if ($esAdministrador && $gestionesAcademicas->isNotEmpty())
                    <form method="GET" action="{{ route('reportes.index') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-indigo-100 bg-white p-3 shadow-sm">
                        @foreach (['desde', 'hasta', 'tipo_periodo', 'fecha_periodo'] as $filtroPreservado)
                            @if ($filtros[$filtroPreservado] ?? null)
                                <input type="hidden" name="{{ $filtroPreservado }}" value="{{ $filtros[$filtroPreservado] }}">
                            @endif
                        @endforeach
                        <div>
                            <label for="gestion-academica" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Gestión</label>
                            <select id="gestion-academica" name="gestion" class="min-h-11 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($gestionesAcademicas as $opcionGestion)
                                    <option value="{{ $opcionGestion }}" @selected($gestionAcademica === $opcionGestion)>{{ $opcionGestion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#272F7A] px-5 text-sm font-semibold text-white transition hover:bg-[#4959A6]">Ver reporte</button>
                    </form>
                @endif
            </header>

            <section class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-4">
                    <h2 class="font-bold text-slate-800">Filtrar actividad por fechas</h2>
                    <p class="mt-1 text-sm text-slate-500">Desde/Hasta filtra asignaciones e incidencias. El PDF usa la frecuencia y fecha de referencia; el inventario muestra el estado actual.</p>
                </div>
                <form method="GET" action="{{ route('reportes.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                    @if ($esAdministrador && $gestionAcademica)
                        <input type="hidden" name="gestion" value="{{ $gestionAcademica }}">
                    @endif
                    <div>
                        <label for="desde" class="mb-2 block text-sm font-semibold text-slate-700">Desde</label>
                        <input id="desde" name="desde" type="date" value="{{ $filtros['desde'] ?? '' }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="hasta" class="mb-2 block text-sm font-semibold text-slate-700">Hasta</label>
                        <input id="hasta" name="hasta" type="date" value="{{ $filtros['hasta'] ?? '' }}" class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="tipo-periodo-pdf" class="mb-2 block text-sm font-semibold text-slate-700">Frecuencia del PDF</label>
                        <select id="tipo-periodo-pdf" name="tipo_periodo" required class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="semanal" @selected($tipoPeriodo === 'semanal')>Semanal</option>
                            <option value="mensual" @selected($tipoPeriodo === 'mensual')>Mensual</option>
                            <option value="anual" @selected($tipoPeriodo === 'anual')>Anual</option>
                        </select>
                    </div>
                    <div>
                        <label for="fecha-periodo-pdf" class="mb-2 block text-sm font-semibold text-slate-700">Fecha de referencia</label>
                        <input id="fecha-periodo-pdf" name="fecha_periodo" type="date" value="{{ $fechaPeriodo }}" required class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#272F7A] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#4959A6]">Aplicar periodo</button>
                        <button type="submit" formaction="{{ route('reportes.pdf') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800">Generar PDF</button>
                        @if (($filtros['desde'] ?? null) || ($filtros['hasta'] ?? null))
                            <a href="{{ route('reportes.index', $esAdministrador && $gestionAcademica ? ['gestion' => $gestionAcademica] : []) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Limpiar</a>
                        @endif
                    </div>
                </form>
                <p class="mt-3 text-xs text-slate-500">El PDF usará la semana de lunes a domingo, el mes calendario o el año que contenga la fecha de referencia.</p>
                @if ($errors->any())
                    <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($esAdministrador)
                <section aria-label="Reporte académico" class="space-y-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">Gestión académica</p>
                        <h2 class="mt-1 text-2xl font-extrabold text-slate-800">Reporte académico</h2>
                        <p class="mt-1 text-sm text-slate-500">Consulta inscripciones, calificaciones y resultados por materia y periodo.</p>
                    </div>

                    @if ($gestionAcademica === null)
                        <div role="status" class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-5 text-sm text-sky-900">
                            No hay gestiones académicas ni inscripciones archivadas para consultar todavía.
                        </div>
                    @elseif ($reporteAcademico === null || ($reporteAcademico['total'] === 0 && ! $reporteAcademico['tiene_movimientos']))
                        <div role="status" class="rounded-2xl border border-dashed border-indigo-200 bg-white px-5 py-7 text-sm text-slate-600">
                            No hay inscripciones registradas para la gestión {{ $gestionAcademica }}.
                        </div>
                    @else
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-[#272F7A]">{{ $gestionAcademica }}</span>
                            <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $reporteAcademico['archivado'] ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $reporteAcademico['archivado'] ? 'Periodo archivado' : 'Periodo en curso' }}
                            </span>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <article class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                                <p class="text-sm font-semibold text-slate-500">{{ $reporteAcademico['archivado'] ? 'Inscripciones archivadas' : 'Total de inscripciones' }}</p>
                                <p class="mt-3 text-3xl font-extrabold text-[#272F7A]">{{ $reporteAcademico['total'] }}</p>
                                <p class="mt-2 text-xs text-slate-500">{{ $reporteAcademico['materias']->count() }} materias con registros</p>
                            </article>
                            @if ($reporteAcademico['archivado'])
                                <article class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50 p-5 shadow-sm">
                                    <p class="text-sm font-semibold text-slate-500">Aprobados</p>
                                    <p class="mt-3 text-3xl font-extrabold text-emerald-700">{{ $reporteAcademico['aprobados'] }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Resultados finales confirmados</p>
                                </article>
                                <article class="rounded-2xl border border-rose-100 bg-gradient-to-br from-white to-rose-50 p-5 shadow-sm">
                                    <p class="text-sm font-semibold text-slate-500">Reprobados</p>
                                    <p class="mt-3 text-3xl font-extrabold text-rose-700">{{ $reporteAcademico['reprobados'] }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Resultados finales confirmados</p>
                                </article>
                                <article class="rounded-2xl border border-cyan-100 bg-gradient-to-br from-white to-cyan-50 p-5 shadow-sm">
                                    <p class="text-sm font-semibold text-slate-500">Promedio final</p>
                                    <p class="mt-3 text-3xl font-extrabold text-cyan-800">{{ $reporteAcademico['promedio'] !== null ? number_format((float) $reporteAcademico['promedio'], 2) : '—' }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Media de las notas archivadas</p>
                                </article>
                                <article class="rounded-2xl border border-violet-100 bg-gradient-to-br from-white to-violet-50 p-5 shadow-sm">
                                    <p class="text-sm font-semibold text-slate-500">Estudiantes que progresan</p>
                                    <p class="mt-3 text-3xl font-extrabold text-violet-700">{{ $reporteAcademico['progresan'] }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Avanza o avanza con arrastres</p>
                                </article>
                            @else
                                <article class="rounded-2xl border border-blue-100 bg-gradient-to-br from-white to-blue-50 p-5 shadow-sm">
                                    <p class="text-sm font-semibold text-slate-500">Cursando</p>
                                    <p class="mt-3 text-3xl font-extrabold text-blue-700">{{ $reporteAcademico['cursando'] }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Inscripciones aún activas</p>
                                </article>
                                <article class="rounded-2xl border border-amber-100 bg-gradient-to-br from-white to-amber-50 p-5 shadow-sm">
                                    <p class="text-sm font-semibold text-slate-500">En revisión administrativa</p>
                                    <p class="mt-3 text-3xl font-extrabold text-amber-700">{{ $reporteAcademico['en_revision'] }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Resultados enviados por docentes</p>
                                </article>
                                <article class="rounded-2xl border border-orange-100 bg-gradient-to-br from-white to-orange-50 p-5 shadow-sm">
                                    <p class="text-sm font-semibold text-slate-500">Calificaciones pendientes</p>
                                    <p class="mt-3 text-3xl font-extrabold text-orange-700">{{ $reporteAcademico['calificaciones_pendientes'] }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Falta al menos un parcial</p>
                                </article>
                                <article class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50 p-5 shadow-sm">
                                    <p class="text-sm font-semibold text-slate-500">Aprobados</p>
                                    <p class="mt-3 text-3xl font-extrabold text-emerald-700">{{ $reporteAcademico['aprobados'] }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Confirmados por administración</p>
                                </article>
                                <article class="rounded-2xl border border-rose-100 bg-gradient-to-br from-white to-rose-50 p-5 shadow-sm">
                                    <p class="text-sm font-semibold text-slate-500">Reprobados</p>
                                    <p class="mt-3 text-3xl font-extrabold text-rose-700">{{ $reporteAcademico['reprobados'] }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Confirmados por administración</p>
                                </article>
                            @endif
                        </div>

                        <article class="rounded-2xl border border-violet-100 bg-white p-5 shadow-sm">
                            <h3 class="font-bold text-slate-800">Progresión estudiantil y seguimiento</h3>
                            <p class="mt-1 text-xs text-slate-500">Decisiones administrativas confirmadas y eventos registrados durante esta gestión.</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                @foreach ([['label' => 'Avanzan', 'value' => $reporteAcademico['progresion']['avanzan'], 'class' => 'text-emerald-700'], ['label' => 'Avanzan con arrastres', 'value' => $reporteAcademico['progresion']['avanzan_con_arrastres'], 'class' => 'text-indigo-700'], ['label' => 'Repiten semestre', 'value' => $reporteAcademico['progresion']['repiten'], 'class' => 'text-rose-700'], ['label' => 'Egresados', 'value' => $reporteAcademico['progresion']['egresados'], 'class' => 'text-violet-700'], ['label' => 'Congelamientos registrados', 'value' => $reporteAcademico['progresion']['congelamientos'], 'class' => 'text-amber-700']] as $datoProgresion)
                                    <div class="rounded-xl bg-slate-50 p-3">
                                        <p class="text-xs font-semibold text-slate-500">{{ $datoProgresion['label'] }}</p>
                                        <p class="mt-2 text-2xl font-black {{ $datoProgresion['class'] }}">{{ $datoProgresion['value'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </article>

                        <article class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
                            <div class="border-b border-indigo-50 bg-gradient-to-r from-indigo-50 to-blue-50/60 px-5 py-4">
                                <h3 class="font-bold text-slate-800">Resultados por materia</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $reporteAcademico['archivado'] ? 'Resumen de resultados y progresión de la gestión archivada.' : 'Seguimiento de inscripciones, calificaciones y decisiones administrativas.' }}</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[760px] text-left">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Semestre</th>
                                            <th class="px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-500">Materia</th>
                                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Inscritos</th>
                                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Aprobados</th>
                                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Reprobados</th>
                                            @if ($reporteAcademico['archivado'])
                                                <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Progresan</th>
                                            @else
                                                <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">En revisión</th>
                                                <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Notas pendientes</th>
                                            @endif
                                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Promedio</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($reporteAcademico['materias'] as $materia)
                                            <tr class="hover:bg-indigo-50/40">
                                                <td class="px-5 py-4 text-sm text-slate-600">{{ $materia->semestre }}</td>
                                                <td class="px-5 py-4">
                                                    <p class="font-semibold text-slate-800">{{ $materia->materia }}</p>
                                                    @if (! empty($materia->sigla))
                                                        <p class="mt-1 text-xs font-semibold text-[#4959A6]">{{ $materia->sigla }}</p>
                                                    @endif
                                                </td>
                                                <td class="px-5 py-4 text-right text-sm font-semibold text-slate-700">{{ $materia->total }}</td>
                                                <td class="px-5 py-4 text-right text-sm font-semibold text-emerald-700">{{ $materia->aprobados }}</td>
                                                <td class="px-5 py-4 text-right text-sm font-semibold text-rose-700">{{ $materia->reprobados }}</td>
                                                @if ($reporteAcademico['archivado'])
                                                    <td class="px-5 py-4 text-right text-sm font-semibold text-violet-700">{{ $materia->progresan }}</td>
                                                @else
                                                    <td class="px-5 py-4 text-right text-sm font-semibold text-amber-700">{{ $materia->en_revision }}</td>
                                                    <td class="px-5 py-4 text-right text-sm font-semibold text-orange-700">{{ $materia->calificaciones_pendientes }}</td>
                                                @endif
                                                <td class="px-5 py-4 text-right text-sm font-bold text-slate-700">{{ $materia->promedio !== null ? number_format((float) $materia->promedio, 2) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    @endif
                </section>
            @endif

            <section aria-label="Resumen del inventario" class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Inventario actual</h2>
                    <p class="mt-1 text-sm text-slate-500">Estado de los equipos y accesorios registrados.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Total de equipos</p>
                        <p class="mt-3 text-3xl font-bold text-[#272F7A]">{{ $estadisticas['totalEquipos'] }}</p>
                        <a href="{{ route('equipos.index') }}" class="mt-3 inline-flex text-sm font-semibold text-[#4959A6] hover:underline">Ver equipos →</a>
                    </article>
                    <article class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50 p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Disponibles</p>
                        <p class="mt-3 text-3xl font-bold text-emerald-700">{{ $estadisticas['equiposDisponibles'] }}</p>
                        <p class="mt-3 text-sm text-slate-500">Listos para utilizarse</p>
                    </article>
                    <article class="rounded-2xl border border-blue-100 bg-gradient-to-br from-white to-blue-50 p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">En uso</p>
                        <p class="mt-3 text-3xl font-bold text-blue-700">{{ $estadisticas['equiposEnUso'] }}</p>
                        <p class="mt-3 text-sm text-slate-500">Actualmente asignados</p>
                    </article>
                    <article class="rounded-2xl border border-amber-100 bg-gradient-to-br from-white to-amber-50 p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Otros estados</p>
                        <p class="mt-3 text-3xl font-bold text-amber-700">{{ $estadisticas['equiposOtros'] }}</p>
                        <p class="mt-3 text-sm text-slate-500">Mantenimiento u otros</p>
                    </article>
                    <article class="rounded-2xl border border-violet-100 bg-white p-5 shadow-sm sm:col-span-2 xl:col-span-1">
                        <p class="text-sm font-medium text-slate-500">Accesorios registrados</p>
                        <p class="mt-3 text-3xl font-bold text-violet-700">{{ $estadisticas['totalAccesorios'] }}</p>
                        <a href="{{ route('inventario.index') }}" class="mt-3 inline-flex text-sm font-semibold text-[#4959A6] hover:underline">Ver inventario →</a>
                    </article>
                </div>
            </section>

            <section aria-label="Estado de apps y diagnósticos" class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Estado de apps y diagnósticos</h2>
                    <p class="mt-1 text-sm text-slate-500">Conectividad vigente según el último heartbeat y resultados registrados para tus equipos visibles.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Apps conectadas</p>
                        <p class="mt-3 text-3xl font-bold text-emerald-700">{{ $estadisticas['dispositivosConectados'] }}</p>
                    </article>
                    <article class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Apps desconectadas</p>
                        <p class="mt-3 text-3xl font-bold text-amber-700">{{ $estadisticas['dispositivosDesconectados'] }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Sin reportes todavía</p>
                        <p class="mt-3 text-3xl font-bold text-slate-700">{{ $estadisticas['dispositivosSinReportes'] }}</p>
                    </article>
                    <article class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Diagnósticos registrados</p>
                        <p class="mt-3 text-3xl font-bold text-indigo-700">{{ $estadisticas['totalDiagnosticos'] }}</p>
                    </article>
                    <article class="rounded-2xl border border-rose-100 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Diagnósticos críticos</p>
                        <p class="mt-3 text-3xl font-bold text-rose-700">{{ $estadisticas['diagnosticosCriticos'] }}</p>
                    </article>
                    <article class="rounded-2xl border border-orange-100 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Alertas abiertas</p>
                        <p class="mt-3 text-3xl font-bold text-orange-700">{{ $estadisticas['alertasAbiertas'] }}</p>
                    </article>
                </div>
            </section>

            <section aria-label="Resumen de actividad" class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Actividad del periodo</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        @if (($filtros['desde'] ?? null) || ($filtros['hasta'] ?? null))
                            Resultados según las fechas seleccionadas.
                        @else
                            Resumen de toda la actividad registrada.
                        @endif
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-2">
                    <article class="rounded-2xl border border-indigo-100 bg-gradient-to-br from-white to-indigo-50 p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-slate-500">Asignaciones</p>
                            <span class="rounded-xl bg-indigo-100 px-3 py-1 text-xs font-bold text-[#272F7A]">Total {{ $estadisticas['totalAsignaciones'] }}</span>
                        </div>
                        <div class="mt-4 flex gap-6">
                            <p class="text-sm text-slate-600">Activas <span class="ml-1 font-bold text-emerald-700">{{ $estadisticas['asignacionesActivas'] }}</span></p>
                            <p class="text-sm text-slate-600">Finalizadas <span class="ml-1 font-bold text-slate-700">{{ $estadisticas['asignacionesFinalizadas'] }}</span></p>
                        </div>
                        <a href="{{ route('asignaciones.index') }}" class="mt-4 inline-flex text-sm font-semibold text-[#4959A6] hover:underline">Ver asignaciones →</a>
                    </article>
                    <article class="rounded-2xl border border-amber-100 bg-gradient-to-br from-white to-amber-50 p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-slate-500">Incidencias</p>
                            <span class="rounded-xl bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Total {{ $estadisticas['totalIncidencias'] }}</span>
                        </div>
                        <div class="mt-4 flex gap-6">
                            <p class="text-sm text-slate-600">Pendientes <span class="ml-1 font-bold text-amber-700">{{ $estadisticas['incidenciasPendientes'] }}</span></p>
                            <p class="text-sm text-slate-600">Resueltas <span class="ml-1 font-bold text-emerald-700">{{ $estadisticas['incidenciasResueltas'] }}</span></p>
                            <p class="text-sm text-slate-600">Desde apps <span class="ml-1 font-bold text-indigo-700">{{ $estadisticas['incidenciasApp'] }}</span></p>
                        </div>
                        <a href="{{ route('incidencias.index') }}" class="mt-4 inline-flex text-sm font-semibold text-[#4959A6] hover:underline">Ver incidencias →</a>
                    </article>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <article class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-indigo-50 bg-gradient-to-r from-indigo-50 to-blue-50/60 px-5 py-4">
                        <div>
                            <h2 class="font-bold text-slate-800">Asignaciones recientes</h2>
                            <p class="mt-1 text-xs text-slate-500">Hasta cinco registros del periodo</p>
                        </div>
                        <a href="{{ route('asignaciones.index') }}" class="text-sm font-semibold text-[#4959A6] hover:underline">Ver todas</a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($asignacionesRecientes as $asignacion)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <div class="min-w-0">
                                    @php
                                        $activoAsignado = $asignacion->equipo?->activo ?? $asignacion->complemento?->activo;
                                        $personaAsignada = $asignacion->estudiante?->nombreCompleto() ?? $asignacion->invitado?->nombreCompleto();
                                        $materiaAsignada = $asignacion->horario?->materia;
                                    @endphp
                                    <p class="font-semibold text-slate-800">{{ $activoAsignado?->codigo ?? 'Recurso no disponible' }} <span class="font-normal text-slate-500">· {{ $activoAsignado?->marca }} {{ $activoAsignado?->modelo }}</span></p>
                                    <p class="mt-1 text-sm text-slate-600">{{ $personaAsignada ?? 'Persona no disponible' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $materiaAsignada?->sigla ? $materiaAsignada->sigla.' · ' : '' }}{{ $materiaAsignada?->nombre ?? 'Sin materia asociada' }} · {{ $asignacion->fecha_entrega?->format('d/m/Y H:i') ?? $asignacion->created_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ in_array($asignacion->estado, ['activa', 'devolucion_pendiente', 'vencida', 'con_incidencia'], true) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ str_replace('_', ' ', ucfirst($asignacion->estado)) }}</span>
                            </div>
                        @empty
                            <p class="px-5 py-10 text-center text-sm text-slate-500">No hay asignaciones en este periodo.</p>
                        @endforelse
                    </div>
                </article>

                <article class="overflow-hidden rounded-2xl border border-amber-100 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-amber-50 bg-gradient-to-r from-amber-50 to-orange-50/60 px-5 py-4">
                        <div>
                            <h2 class="font-bold text-slate-800">Incidencias recientes</h2>
                            <p class="mt-1 text-xs text-slate-500">Hasta cinco reportes del periodo</p>
                        </div>
                        <a href="{{ route('incidencias.index') }}" class="text-sm font-semibold text-[#4959A6] hover:underline">Ver todas</a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($incidenciasRecientes as $incidencia)
                            <div class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800">{{ $incidencia->activo?->codigo ?? 'Activo no disponible' }} <span class="font-normal text-slate-500">· {{ $incidencia->tipo }}</span></p>
                                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $incidencia->descripcion }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Reportada: {{ $incidencia->fecha_reporte?->format('d/m/Y H:i') ?? 'Sin fecha' }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">{{ $incidencia->prioridad === 'critica' ? 'Crítica' : ucfirst($incidencia->prioridad) }}</span>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ strtolower($incidencia->estado) === 'resuelta' ? 'bg-emerald-50 text-emerald-700' : 'bg-orange-50 text-orange-700' }}">{{ $incidencia->estado }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="px-5 py-10 text-center text-sm text-slate-500">No hay incidencias en este periodo.</p>
                        @endforelse
                    </div>
                </article>
            </section>
        </div>
    </main>
</x-admin-shell>
