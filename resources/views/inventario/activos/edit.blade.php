<x-admin-shell>
    <main class="min-h-screen bg-[#F4F7FC] text-slate-800">
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <header class="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] px-6 py-7 text-white shadow-2xl shadow-indigo-950/20 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-8">
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl" aria-hidden="true"></div>
                <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-30" aria-hidden="true"></div>
                <div class="relative">
                    <p class="text-sm font-medium text-cyan-200">Administración / Inventario / Activos</p>
                    <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Editar activo patrimonial</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100/80">Actualiza los datos y la ficha técnica del artículo seleccionado.</p>
                </div>
                <a href="{{ route('inventario.activos.show', $activo) }}" class="relative inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50 hover:text-[#4959A6]">
                    Volver a la ficha
                </a>
            </header>

            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('inventario.activos.update', $activo) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')
                @include('inventario.activos._form')

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('inventario.activos.show', $activo) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Cancelar</a>
                    <button class="inline-flex items-center justify-center rounded-xl bg-[#272F7A] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4959A6]">Guardar cambios</button>
                </div>
            </form>
        </div>
    </main>
</x-admin-shell>
