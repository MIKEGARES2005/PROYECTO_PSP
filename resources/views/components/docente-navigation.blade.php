<div>
    <p class="mb-2 px-3 text-[10px] font-black uppercase tracking-[0.22em] text-sky-100/80">Principal</p>
    <a href="{{ route('dashboard') }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20' : 'text-sky-50/85 hover:bg-white/10 hover:text-white' }}">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sky-100 ring-1 ring-white/10 transition group-hover:bg-white/20 group-hover:text-white">
            <x-icon name="home" class="h-4 w-4" />
        </span>
        <span>Inicio</span>
        @if (request()->routeIs('dashboard'))
            <span class="ml-auto h-1.5 w-1.5 rounded-full bg-sky-200"></span>
        @endif
    </a>
</div>

<div>
    <p class="mb-2 px-3 text-[10px] font-black uppercase tracking-[0.22em] text-sky-100/80">Académico</p>
    <div class="space-y-1">
        <a href="{{ route('panel-docente.estudiantes') }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('panel-docente.estudiantes') ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20' : 'text-sky-50/85 hover:bg-white/10 hover:text-white' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sky-100 ring-1 ring-white/10 transition group-hover:bg-white/20 group-hover:text-white">
                <x-icon name="users" class="h-4 w-4" />
            </span>
            <span>Gestión Académica</span>
            @if (request()->routeIs('panel-docente.estudiantes'))
                <span class="ml-auto h-1.5 w-1.5 rounded-full bg-sky-200"></span>
            @endif
        </a>

        <a href="{{ route('panel-docente.academico') }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('panel-docente.academico') ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20' : 'text-sky-50/85 hover:bg-white/10 hover:text-white' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sky-100 ring-1 ring-white/10 transition group-hover:bg-white/20 group-hover:text-white">
                <x-icon name="academic-cap" class="h-4 w-4" />
            </span>
            <span>Panel Académico</span>
            @if (request()->routeIs('panel-docente.academico'))
                <span class="ml-auto h-1.5 w-1.5 rounded-full bg-sky-200"></span>
            @endif
        </a>

        <a href="{{ route('panel-docente.seguimiento') }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('panel-docente.seguimiento*') ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20' : 'text-sky-50/85 hover:bg-white/10 hover:text-white' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sky-100 ring-1 ring-white/10 transition group-hover:bg-white/20 group-hover:text-white">
                <x-icon name="clipboard" class="h-4 w-4" />
            </span>
            <span>Seguimiento estudiantil</span>
            @if (request()->routeIs('panel-docente.seguimiento*'))
                <span class="ml-auto h-1.5 w-1.5 rounded-full bg-sky-200"></span>
            @endif
        </a>

    </div>
</div>

<div>
    <p class="mb-2 px-3 text-[10px] font-black uppercase tracking-[0.22em] text-sky-100/80">Operación</p>
    <div class="space-y-1">
        <a href="{{ route('inventario.index') }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('inventario.*') ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20' : 'text-sky-50/85 hover:bg-white/10 hover:text-white' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sky-100 ring-1 ring-white/10 transition group-hover:bg-white/20 group-hover:text-white">
                <x-icon name="inventory" class="h-4 w-4" />
            </span>
            <span>Inventario</span>
            @if (request()->routeIs('inventario.*'))
                <span class="ml-auto h-1.5 w-1.5 rounded-full bg-sky-200"></span>
            @endif
        </a>

        <a href="{{ route('asignaciones.index') }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('asignaciones.*') ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20' : 'text-sky-50/85 hover:bg-white/10 hover:text-white' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sky-100 ring-1 ring-white/10 transition group-hover:bg-white/20 group-hover:text-white">
                <x-icon name="assignment" class="h-4 w-4" />
            </span>
            <span>Asignaciones</span>
            @if (request()->routeIs('asignaciones.*'))
                <span class="ml-auto h-1.5 w-1.5 rounded-full bg-sky-200"></span>
            @endif
        </a>

        <a href="{{ route('incidencias.index') }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('incidencias.*') ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20' : 'text-sky-50/85 hover:bg-white/10 hover:text-white' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sky-100 ring-1 ring-white/10 transition group-hover:bg-white/20 group-hover:text-white">
                <x-icon name="incident" class="h-4 w-4" />
            </span>
            <span>Incidencias</span>
            @if (request()->routeIs('incidencias.*'))
                <span class="ml-auto h-1.5 w-1.5 rounded-full bg-sky-200"></span>
            @endif
        </a>

        <a href="{{ route('diagnosticos.index') }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('diagnosticos.*') ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20' : 'text-sky-50/85 hover:bg-white/10 hover:text-white' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sky-100 ring-1 ring-white/10 transition group-hover:bg-white/20 group-hover:text-white">
                <x-icon name="diagnostic" class="h-4 w-4" />
            </span>
            <span>Diagnósticos</span>
            @if (request()->routeIs('diagnosticos.*'))
                <span class="ml-auto h-1.5 w-1.5 rounded-full bg-sky-200"></span>
            @endif
        </a>

        <a href="{{ route('monitoreo.index') }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('monitoreo.*') ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20' : 'text-sky-50/85 hover:bg-white/10 hover:text-white' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sky-100 ring-1 ring-white/10 transition group-hover:bg-white/20 group-hover:text-white">
                <x-icon name="monitor" class="h-4 w-4" />
            </span>
            <span>Monitoreo</span>
            @if (request()->routeIs('monitoreo.*'))
                <span class="ml-auto h-1.5 w-1.5 rounded-full bg-sky-200"></span>
            @endif
        </a>

        <a href="{{ route('reportes.index') }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ request()->routeIs('reportes.*') ? 'bg-white/20 text-white shadow-inner shadow-white/10 ring-1 ring-white/20' : 'text-sky-50/85 hover:bg-white/10 hover:text-white' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-sky-100 ring-1 ring-white/10 transition group-hover:bg-white/20 group-hover:text-white">
                <x-icon name="report" class="h-4 w-4" />
            </span>
            <span>Reportes</span>
            @if (request()->routeIs('reportes.*'))
                <span class="ml-auto h-1.5 w-1.5 rounded-full bg-sky-200"></span>
            @endif
        </a>
    </div>
</div>
