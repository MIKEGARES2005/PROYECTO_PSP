<x-admin-shell>
    <main class="min-h-screen bg-[#F4F7FC] text-slate-800">
        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <header class="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] px-6 py-7 text-white shadow-2xl shadow-indigo-950/20 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-8">
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl" aria-hidden="true"></div>
                <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-30" aria-hidden="true"></div>
                <div class="relative">
                    <p class="text-sm font-medium text-cyan-200">Administración / Inventario / Categorías</p>
                    <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Editar categoría</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100/80">Selecciona una categoría para consultar y actualizar sus datos.</p>
                </div>
                <a href="{{ route('inventario.index') }}" class="relative inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50 hover:text-[#4959A6]">
                    Volver al inventario
                </a>
            </header>

            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ $errors->first() }}</div>
            @endif

            <form method="GET" action="{{ route('inventario.categorias.manage') }}" class="flex flex-col gap-4 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:flex-row sm:items-end sm:p-6">
                <div class="flex-1">
                    <label for="categoria" class="mb-2 block text-sm font-semibold text-slate-700">Categoría registrada</label>
                    <select id="categoria" name="categoria" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-[#4959A6] focus:ring-[#4959A6]">
                        <option value="">Selecciona una categoría</option>
                        @foreach ($categorias as $opcion)
                            <option value="{{ $opcion->id }}" @selected($categoria?->id === $opcion->id)>{{ $opcion->nombre }} · {{ $opcion->prefijo_codigo }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="inline-flex items-center justify-center rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4959A6]">Mostrar datos</button>
            </form>

            @if ($categoria)
                <div class="space-y-4">
                    <form method="POST" action="{{ route('inventario.categorias.update', $categoria) }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        <section class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-7">
                            <div class="mb-6">
                                <h2 class="text-lg font-bold text-slate-800">Datos de la categoría</h2>
                                <p class="mt-1 text-sm text-slate-500">Actualiza la clasificación y la ficha que crea el sistema.</p>
                            </div>
                            @include('inventario.categorias._form')
                        </section>
                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <a href="{{ route('inventario.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Cancelar</a>
                            <button class="inline-flex items-center justify-center rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4959A6]">Guardar cambios</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('inventario.categorias.destroy', $categoria) }}" onsubmit="return confirm('¿Eliminar esta categoría? Solo se permite si no tiene artículos registrados.')">
                        @csrf
                        @method('DELETE')
                        <button class="w-full rounded-xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 sm:w-auto">Eliminar categoría</button>
                    </form>
                </div>
            @else
                <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-8 text-center text-sm text-slate-500">Selecciona una categoría y presiona «Mostrar datos» para editarla o eliminarla.</p>
            @endif
        </div>
    </main>
</x-admin-shell>
