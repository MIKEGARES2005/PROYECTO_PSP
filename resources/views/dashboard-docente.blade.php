<x-docente-layout>
    @if (session('teacher_welcome'))
        <div x-data="{ abierto: {{ session('staff_login_welcome') ? 'false' : 'true' }} }" x-on:staff-welcome-finished.window="abierto = true" x-show="abierto" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="bienvenida-docente">
            <div class="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-sky-900/10">
                <div class="bg-gradient-to-r from-sky-700 to-blue-600 px-6 py-7 text-white sm:px-8">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-sky-100">Acceso autorizado</p>
                    <h2 id="bienvenida-docente" class="mt-2 text-2xl font-extrabold">¡Bienvenido al panel docente!</h2>
                </div>
                <div class="space-y-4 px-6 py-6 text-sm leading-6 text-slate-600 sm:px-8">
                    <p>Desde este panel podrás revisar tus materias, estudiantes y actividad académica.</p>
                    @if (session('teacher_recovery_welcome'))
                        <p>Para proteger tu cuenta, actualiza tu nombre de usuario y contraseña temporales desde <strong>Mi cuenta</strong>. Tus preguntas de seguridad se conservan; no necesitas registrarlas de nuevo.</p>
                    @else
                        <p>Para proteger tu cuenta, cambia tu nombre de usuario y contraseña temporales desde <strong>Mi cuenta</strong>, y registra tus preguntas de seguridad desde el menú de usuario.</p>
                    @endif
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-amber-900">
                        @if (session('teacher_recovery_welcome'))
                            <strong>Importante:</strong> solo debes actualizar las credenciales temporales; tus preguntas y respuestas guardadas permanecerán intactas.
                        @else
                            <strong>Importante:</strong> completa estos pasos antes de acceder a las demás secciones del sistema.
                        @endif
                    </div>
                    <button type="button" @click="abierto = false" class="inline-flex w-full items-center justify-center rounded-xl bg-sky-700 px-5 py-3 font-bold text-white shadow-sm transition hover:bg-sky-800">Entendido, ir al panel de inicio</button>
                </div>
            </div>
        </div>
    @endif

    <section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-sky-600 via-blue-600 to-blue-800 px-6 py-7 text-white shadow-xl shadow-sky-900/15 sm:px-8 sm:py-9">
        <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-40" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-20 -top-28 h-72 w-72 rounded-full bg-sky-300/25 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-sky-50 backdrop-blur">
                    <x-icon name="academic-cap" class="h-3.5 w-3.5" />
                    ITIBB · Informática Industrial
                </p>
                <h2 class="mt-4 text-2xl font-black tracking-tight sm:text-3xl">¡Bienvenido, {{ auth()->user()->name }}!</h2>
                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-50/90">Este panel presenta únicamente tus materias y la actividad relacionada con ellas.</p>
            </div>
            <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center lg:flex-col lg:items-end">
                <span class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold text-white backdrop-blur">
                    <x-icon name="calendar" class="h-4 w-4 text-sky-100" />
                    {{ $gestionVigenteCodigo === null ? 'Sistema en espera' : 'Gestión vigente '.$gestionVigenteCodigo }}
                </span>
            </div>
        </div>
    </section>

    <section aria-label="Resumen docente" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <article class="group rounded-2xl border border-sky-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-sky-100/70">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-slate-500">Mis materias</p>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-600 ring-1 ring-sky-100"><x-icon name="book" class="h-5 w-5" /></span>
            </div>
            <p class="mt-3 text-3xl font-black tracking-tight text-sky-700">{{ $estadisticas['materias'] }}</p>
        </article>
        <article class="group rounded-2xl border border-sky-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-sky-100/70">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-slate-500">Estudiantes cursando</p>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 ring-1 ring-blue-100"><x-icon name="users" class="h-5 w-5" /></span>
            </div>
            <p class="mt-3 text-3xl font-black tracking-tight text-blue-700">{{ $estadisticas['estudiantes'] }}</p>
            <a href="{{ route('panel-docente.estudiantes') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 transition hover:bg-sky-600 hover:text-white">Panel estudiantil <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a>
        </article>
        <article class="group rounded-2xl border border-sky-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-sky-100/70">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-slate-500">Asignaciones activas</p>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100"><x-icon name="assignment" class="h-5 w-5" /></span>
            </div>
            <p class="mt-3 text-3xl font-black tracking-tight text-indigo-700">{{ $estadisticas['asignaciones_activas'] }}</p>
            <a href="{{ route('asignaciones.index') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 transition hover:bg-sky-600 hover:text-white">Ver asignaciones <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a>
        </article>
        <article class="group rounded-2xl border border-sky-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-sky-100/70">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-slate-500">Equipos conectados</p>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100"><x-icon name="monitor" class="h-5 w-5" /></span>
            </div>
            <p class="mt-3 text-3xl font-black tracking-tight text-emerald-700">{{ $estadisticas['equipos_conectados'] }}</p>
            <a href="{{ route('monitoreo.index') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 transition hover:bg-sky-600 hover:text-white">Abrir monitoreo <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a>
        </article>
        <article class="group rounded-2xl border border-sky-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-sky-100/70">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-slate-500">Incidencias pendientes</p>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 ring-1 ring-amber-100"><x-icon name="incident" class="h-5 w-5" /></span>
            </div>
            <p class="mt-3 text-3xl font-black tracking-tight text-amber-700">{{ $estadisticas['incidencias_pendientes'] }}</p>
            <a href="{{ route('incidencias.index') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 transition hover:bg-sky-600 hover:text-white">Ver incidencias <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-sky-100 bg-gradient-to-r from-sky-50 to-blue-50/60 px-5 py-5 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm ring-1 ring-sky-100"><x-icon name="calendar" class="h-5 w-5" /></span>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Mi horario</h3>
                        <p class="mt-1 text-sm text-slate-500">Horarios activos asociados a tu perfil docente.</p>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-sky-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Día</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Materia</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Semestre</th>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Horario / aula</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($horarios as $horario)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-slate-700">{{ \App\Models\Horario::DIAS[$horario->dia_semana] ?? $horario->dia_semana }}</td>
                                <td class="px-5 py-4 text-sm font-semibold text-slate-800">{{ $horario->materia_nombre }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $horario->semestre }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                    {{ substr($horario->hora_inicio, 0, 5) }}–{{ substr($horario->hora_fin, 0, 5) }}
                                    <span class="block text-xs text-slate-400">{{ $horario->aula ?: 'Sin aula' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">Aún no tienes horarios activos asignados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-sky-100 bg-gradient-to-r from-sky-50 to-blue-50/60 px-5 py-5 sm:px-6">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm ring-1 ring-sky-100"><x-icon name="monitor" class="h-5 w-5" /></span>
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-slate-800">Monitoreo de mis equipos</h3>
                            <p class="mt-1 text-sm text-slate-500">Equipos vinculados a asignaciones de tus materias.</p>
                        </div>
                    </div>
                </div>
                <a href="{{ route('monitoreo.index') }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-bold text-sky-700 shadow-sm ring-1 ring-sky-100 transition hover:bg-sky-600 hover:text-white">Ver monitoreo <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a>
            </div>
            <div class="space-y-4 p-5">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-emerald-50 p-4">
                        <p class="text-xs font-medium text-emerald-800">Conectados</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $estadisticas['equipos_conectados'] }}</p>
                    </div>
                    <div class="rounded-xl bg-red-50 p-4">
                        <p class="text-xs font-medium text-red-800">Desconectados</p>
                        <p class="mt-2 text-2xl font-bold text-red-700">{{ $estadisticas['equipos_desconectados'] }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-100 p-4">
                        <p class="text-xs font-medium text-slate-600">Sin datos</p>
                        <p class="mt-2 text-2xl font-bold text-slate-700">{{ $estadisticas['equipos_sin_datos'] }}</p>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-xs font-medium text-slate-500">Último heartbeat recibido</p>
                    <p class="mt-2 text-sm font-semibold text-slate-700">{{ $ultimaActualizacionMonitoreo ? \Illuminate\Support\Carbon::parse($ultimaActualizacionMonitoreo)->format('d/m/Y H:i:s') : 'Sin datos registrados' }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-400">La conexión se calcula con la hora de recepción en el servidor; se considera reciente dentro de los últimos {{ \App\Models\Heartbeat::ONLINE_TIMEOUT_MINUTES }} minutos.</p>
                </div>
            </div>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-sky-100 bg-gradient-to-r from-sky-50 to-white px-5 py-5 sm:px-6">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm ring-1 ring-sky-100"><x-icon name="assignment" class="h-5 w-5" /></span>
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-slate-800">Asignaciones recientes</h3>
                            <p class="mt-1 text-sm text-slate-500">Préstamos registrados en tus materias.</p>
                        </div>
                    </div>
                </div>
                <a href="{{ route('asignaciones.index') }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-bold text-sky-700 shadow-sm ring-1 ring-sky-100 transition hover:bg-sky-600 hover:text-white">Ver todas <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($asignacionesRecientes as $asignacion)
                    @php
                        $equipo = $asignacion->equipo;
                        $heartbeat = $equipo?->ultimoHeartbeat;
                        $ultimaLectura = $heartbeat?->fecha_hora;
                        $appConectada = $ultimaLectura !== null
                            && $ultimaLectura->between(now()->subMinutes(\App\Models\Heartbeat::ONLINE_TIMEOUT_MINUTES), now());
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-800">{{ $equipo?->activo?->codigo ?? 'Equipo no disponible' }} <span class="font-normal text-slate-500">· {{ $equipo?->activo?->nombre }}</span></p>
                            <p class="mt-1 text-sm text-slate-600">{{ $asignacion->estudiante?->nombres ?? 'Estudiante no disponible' }} {{ $asignacion->estudiante?->apellido_paterno }} {{ $asignacion->estudiante?->apellido_materno }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $asignacion->created_at?->format('d/m/Y H:i') ?? 'Fecha no disponible' }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs font-bold">
                                <span class="rounded-full px-2.5 py-1 {{ $equipo?->token_hash ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-700' }}">{{ $equipo?->token_hash ? 'App vinculada' : 'App no vinculada' }}</span>
                                <span class="rounded-full px-2.5 py-1 {{ $appConectada ? 'bg-emerald-100 text-emerald-800' : ($heartbeat ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">{{ $appConectada ? 'App conectada' : ($heartbeat ? 'App sin conexión' : 'Sin reportes') }}</span>
                            </div>
                            <p class="mt-2 text-xs text-slate-400">Último reporte: {{ $ultimaLectura?->format('d/m/Y H:i:s') ?? 'Sin datos registrados' }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ strtolower($asignacion->estado) === 'activa' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst(strtolower($asignacion->estado)) }}</span>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-500 sm:px-6">No hay asignaciones recientes en tus materias.</p>
                @endforelse
            </div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-rose-100 bg-gradient-to-r from-rose-50 to-orange-50/60 px-5 py-5 sm:px-6">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-rose-600 shadow-sm ring-1 ring-rose-100"><x-icon name="diagnostic" class="h-5 w-5" /></span>
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-slate-800">Diagnósticos críticos</h3>
                            <p class="mt-1 text-sm text-slate-500">Alertas de equipos vinculados a tus materias.</p>
                        </div>
                    </div>
                </div>
                <a href="{{ route('diagnosticos.index') }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-bold text-sky-700 shadow-sm ring-1 ring-sky-100 transition hover:bg-sky-600 hover:text-white">Ver diagnósticos <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($diagnosticosCriticos as $diagnostico)
                    <div class="flex items-start gap-3 px-5 py-4 sm:px-6">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600"><x-icon name="incident" class="h-4 w-4" /></span>
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-800">{{ $diagnostico->equipo?->codigo ?? 'Equipo no disponible' }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $diagnostico->descripcion }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $diagnostico->fecha_hora?->format('d/m/Y H:i') ?? 'Fecha no disponible' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-500 sm:px-6">No hay diagnósticos críticos en los equipos vinculados a tus materias.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-rose-100 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-rose-100 bg-gradient-to-r from-rose-50 to-orange-50/60 px-5 py-5 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-rose-600 shadow-sm ring-1 ring-rose-100"><x-icon name="incident" class="h-5 w-5" /></span>
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Incidencias pendientes de mis asignaciones</h3>
                    <p class="mt-1 text-sm text-slate-500">Incluye reportes enviados desde las apps de los equipos.</p>
                </div>
            </div>
            <a href="{{ route('incidencias.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-bold text-sky-700 shadow-sm ring-1 ring-sky-100 transition hover:bg-sky-600 hover:text-white">Ver incidencias <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($incidenciasRecientes as $incidencia)
                <div class="flex flex-wrap items-start justify-between gap-3 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800">{{ $incidencia->activo?->codigo ?? 'Activo no disponible' }} <span class="font-normal text-slate-500">· {{ $incidencia->tipo }}</span></p>
                        <p class="mt-1 text-sm text-slate-600">{{ $incidencia->descripcion }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $incidencia->fecha_reporte?->format('d/m/Y H:i') ?? 'Fecha no disponible' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($incidencia->origen === 'app')
                            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-bold text-sky-800">Reportada desde app</span>
                        @endif
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">{{ str_replace('_', ' ', ucfirst($incidencia->estado)) }}</span>
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500 sm:px-6">No hay incidencias pendientes en tus asignaciones.</p>
            @endforelse
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2">
        <a href="{{ route('panel-docente.estudiantes') }}" class="group rounded-2xl border border-sky-100 bg-gradient-to-br from-white to-sky-50 p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-sky-100/70">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-sky-600">Comunidad académica</p>
                    <h3 class="mt-2 text-lg font-bold text-slate-800 transition group-hover:text-sky-700">Gestión Académica</h3>
                </div>
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm ring-1 ring-sky-100"><x-icon name="users" class="h-5 w-5" /></span>
            </div>
            <p class="mt-1 text-sm text-slate-500">Consulta las inscripciones cursando en tus materias de la gestión vigente.</p>
            <span class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-sky-700">Abrir panel <x-icon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1" /></span>
        </a>
        <a href="{{ route('panel-docente.academico') }}" class="group rounded-2xl border border-blue-100 bg-gradient-to-br from-white to-blue-50 p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg hover:shadow-blue-100/70">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-blue-600">Comunidad institucional</p>
                    <h3 class="mt-2 text-lg font-bold text-slate-800 transition group-hover:text-blue-700">Panel Académico</h3>
                </div>
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-blue-600 shadow-sm ring-1 ring-blue-100"><x-icon name="academic-cap" class="h-5 w-5" /></span>
            </div>
            <p class="mt-1 text-sm text-slate-500">Consulta perfiles docentes y administrativos asociados sin acceder a sus cuentas ni credenciales.</p>
            <span class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-blue-700">Abrir panel <x-icon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1" /></span>
        </a>
    </section>

    <section class="rounded-2xl border border-sky-100 bg-sky-50/80 px-5 py-4">
        <p class="text-sm leading-6 text-sky-900">Para cambios en materias, horarios, inscripciones o en los registros personales de usuarios, solicita la actualización a administración.</p>
    </section>
</x-docente-layout>
