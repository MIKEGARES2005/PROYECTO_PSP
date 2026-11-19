<x-admin-shell>
    <main class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-indigo-50/60">
        <div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wider text-[#4959A6]">Incremento II / Patrimonio</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Inventario</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-500">{{ auth()->user()->isAdministrador() ? 'Administra equipos, periféricos y complementos, además de mobiliario, desde un solo panel.' : 'Consulta los equipos y complementos registrados, estén asignados o disponibles.' }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if (auth()->user()->isAdministrador())
                        <a href="{{ route('inventario.categorias.create') }}" class="inline-flex items-center rounded-xl border border-indigo-200 bg-white px-4 py-3 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">Nueva categoría</a>
                        <a href="{{ route('inventario.categorias.manage') }}" class="inline-flex items-center rounded-xl border border-indigo-200 bg-white px-4 py-3 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">Editar categoría</a>
                    @endif
                    @if (auth()->user()->isAdministrador())
                        <a href="{{ route('inventario.activos.create') }}" class="inline-flex items-center rounded-xl bg-[#272F7A] px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#4959A6]">Registrar artículo</a>
                    @endif
                </div>
            </header>

            @if (session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <section class="grid gap-2 sm:gap-4 {{ auth()->user()->isAdministrador() ? 'grid-cols-1 md:grid-cols-4' : 'grid-cols-3' }}">
                @foreach (array_filter([
                    ['label' => auth()->user()->isAdministrador() ? 'Equipos' : 'Equipos vinculados', 'key' => 'equipo', 'value' => $resumen['equipos'], 'badge' => 'bg-indigo-50 text-indigo-700', 'caption' => auth()->user()->isAdministrador() ? 'Computadoras y tablets' : 'Equipos de tus asignaciones'],
                    ['label' => auth()->user()->isAdministrador() ? 'Periféricos y complementos' : 'Complementos', 'key' => 'complemento', 'value' => $resumen['complementos'], 'badge' => 'bg-cyan-50 text-cyan-700', 'caption' => 'Teclados, mouse, monitores y apoyadores'],
                    auth()->user()->isAdministrador() ? ['label' => 'Muebles', 'key' => 'mueble', 'value' => $resumen['muebles'], 'badge' => 'bg-amber-50 text-amber-700', 'caption' => 'Mobiliario institucional'] : null,
                    ['label' => auth()->user()->isAdministrador() ? 'Total de activos' : 'Total de registros', 'key' => '', 'value' => $resumen['total'], 'badge' => 'bg-emerald-50 text-emerald-700', 'caption' => auth()->user()->isAdministrador() ? 'Patrimonio registrado' : 'Equipos y complementos registrados'],
                ]) as $tarjeta)
                    <a href="{{ $tarjeta['key'] ? route('inventario.index', ['tipo' => $tarjeta['key']]) : route('inventario.index') }}" class="group min-w-0 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md sm:p-5">
                        <div class="flex min-w-0 items-start justify-between gap-1 sm:gap-3">
                            <p class="min-w-0 text-[10px] font-black uppercase tracking-wide text-slate-500 sm:text-xs sm:tracking-wider">{{ $tarjeta['label'] }}</p>
                            <span class="shrink-0 rounded-lg px-2 py-1 text-base font-black sm:rounded-xl sm:px-3 sm:py-2 sm:text-lg {{ $tarjeta['badge'] }}">{{ $tarjeta['value'] }}</span>
                        </div>
                        <p class="mt-2 text-xs font-semibold text-slate-800 group-hover:text-[#272F7A] sm:mt-4 sm:text-sm">{{ $tarjeta['caption'] }}</p>
                        <p class="mt-1 text-[10px] text-slate-400 sm:text-xs">Ver registros</p>
                    </a>
                @endforeach
            </section>

            <section class="rounded-3xl border border-indigo-100 bg-white p-5 shadow-sm sm:p-6">
                <div class="space-y-4">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Filtrar artículos</h2>
                        <p class="mt-1 text-sm text-slate-500">Busca por datos del artículo o selecciona una categoría para ver sus registros.</p>
                    </div>
                    <form method="GET" action="{{ route('inventario.index') }}" class="flex w-full flex-nowrap items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <label for="buscar" class="mb-1 block truncate text-xs font-bold text-slate-600">Buscar</label>
                            <input id="buscar" name="buscar" value="{{ request('buscar') }}" placeholder="Nombre, código o marca" class="min-h-10 w-full min-w-0 rounded-xl border-slate-300 px-2 text-xs sm:text-sm">
                        </div>
                        <div class="min-w-0 flex-1">
                            <label for="categoria" class="mb-1 block truncate text-xs font-bold text-slate-600">Categoría</label>
                            <select id="categoria" name="categoria" class="min-h-10 w-full min-w-0 rounded-xl border-slate-300 px-2 text-xs sm:text-sm">
                                <option value="">Todas las categorías</option>
                                @foreach ($categorias as $categoria)
                                    <option value="{{ $categoria->id }}" @selected($categoriaId === $categoria->id)>{{ $categoria->nombre }} ({{ $categoria->activos_count }})</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="min-h-10 shrink-0 whitespace-nowrap rounded-xl bg-slate-900 px-2 text-xs font-bold text-white sm:px-4 sm:text-sm">Filtrar</button>
                    </form>
                </div>
            </section>

            <section class="space-y-4">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">{{ $categoriaId ? 'Activos de la categoria' : ($tipo ? ucfirst($tipo).'s' : 'Ultimos activos') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Selecciona un registro para consultar toda su ficha patrimonial.</p>
                    </div>
                    @if ($categoriaId || $tipo || request()->filled('buscar'))
                        <a href="{{ route('inventario.index') }}" class="text-sm font-bold text-[#4959A6] hover:underline">Ver todo</a>
                    @endif
                </div>
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($activos as $activo)
                        <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg">
                            <a href="{{ route('inventario.activos.show', $activo) }}" class="block">
                                @if ($activo->imagen_referencial)
                                    <img src="{{ asset('storage/'.$activo->imagen_referencial) }}" alt="Imagen de {{ $activo->nombre }}" class="h-44 w-full object-cover">
                                @else
                                    <div class="flex h-44 items-center justify-center bg-gradient-to-br from-indigo-100 via-white to-cyan-50 text-5xl font-black text-indigo-300">{{ $activo->equipo ? 'EQ' : ($activo->complemento ? 'CP' : ($activo->categoria?->grupo === 'electronico' ? 'EL' : ($activo->categoria?->grupo === 'mobiliario' ? 'MB' : 'AC'))) }}</div>
                                @endif
                                <div class="space-y-3 p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-black uppercase tracking-wider text-[#4959A6]">{{ $activo->codigo }}</p>
                                            <h3 class="mt-1 text-lg font-black text-slate-900 group-hover:text-[#272F7A]">{{ $activo->nombre }}</h3>
                                        </div>
                                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-black text-emerald-700">{{ ucfirst($activo->estado_uso) }}</span>
                                    </div>
                                    <p class="text-sm text-slate-500">{{ $activo->categoria?->nombre }}@if ($activo->marca || $activo->modelo) · {{ $activo->marca }} {{ $activo->modelo }}@endif</p>
                                    <p class="text-xs font-semibold text-slate-400">{{ $activo->numero_serie ?: 'Sin numero de serie' }} · {{ $activo->ubicacion ?: 'Sin ubicacion' }}</p>
                                </div>
                            </a>
                            @if (auth()->user()->isAdministrador())
                                <div class="flex gap-2 border-t border-slate-100 px-5 py-3">
                                    <a href="{{ route('inventario.activos.qr', $activo) }}" class="rounded-lg bg-cyan-50 px-3 py-2 text-xs font-bold text-cyan-800">QR PDF</a>
                                    <a href="{{ route('inventario.activos.edit', $activo) }}" class="rounded-lg bg-indigo-50 px-3 py-2 text-xs font-bold text-[#272F7A]">Editar</a>
                                    <form method="POST" action="{{ route('inventario.activos.destroy', $activo) }}" onsubmit="return confirm('¿Eliminar este activo?')">@csrf @method('DELETE')<button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-red-700">Eliminar</button></form>
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center md:col-span-2 xl:col-span-3">
                            <p class="text-lg font-black text-slate-700">No hay activos para este filtro</p>
                            <p class="mt-2 text-sm text-slate-500">{{ auth()->user()->isAdministrador() ? 'Registra el primer activo de esta categoria para verlo aqui.' : 'No hay equipos asignados que coincidan con este filtro.' }}</p>
                        </div>
                    @endforelse
                </div>
                {{ $activos->links() }}
            </section>
        </div>
    </main>
</x-admin-shell>
