<x-admin-layout>
    <x-admin-shell>
        <div class="min-h-screen bg-[#F4F7FC] text-slate-800" x-data="{ pdfFilesOpen: false }">
            <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0B1226] via-[#1E2A5A] to-[#272F7A] px-6 py-7 text-white shadow-2xl shadow-indigo-950/20 sm:px-8 sm:py-8">
                    <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-rose-400/10 blur-3xl" aria-hidden="true"></div>
                    <div class="dashboard-grid pointer-events-none absolute inset-0 opacity-30" aria-hidden="true"></div>
                    <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-medium text-cyan-200">Administración / Usuarios / Archivo</p>
                            <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Perfiles eliminados</h1>
                            <p class="mt-2 text-sm leading-6 text-indigo-100/80">Restaurables durante {{ (int) config('retention.users.deletion_grace_period_days', 2) }} días; puedes eliminarlos antes. Al finalizar, los datos se anonimizan y el PDF original se conserva indefinidamente con su información personal.</p>
                        </div>
                        <div class="relative flex flex-col gap-3 sm:flex-row">
                            <button type="button" @click="pdfFilesOpen = true" class="inline-flex items-center justify-center whitespace-nowrap rounded-xl bg-white px-5 py-3 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">
                                Ver archivos PDF
                            </button>
                            <a href="{{ route('usuarios.index') }}" class="inline-flex items-center justify-center whitespace-nowrap rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-bold text-[#272F7A] shadow-sm transition hover:bg-indigo-50 hover:text-[#4959A6]">
                                Volver al listado
                            </a>
                        </div>
                    </div>
                </header>

                <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                    @if ($usuarios->isEmpty())
                        <div class="px-6 py-16 text-center">
                            <p class="text-lg font-black text-slate-700">No hay perfiles eliminados</p>
                            <p class="mt-2 text-sm text-slate-500">Los perfiles archivados aparecerán aquí.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-slate-500">Perfil</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-slate-500">Usuario</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-slate-500">Tipo</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wider text-slate-500">Archivado</th>
                                        <th class="px-5 py-4 text-right text-xs font-black uppercase tracking-wider text-slate-500">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($usuarios as $usuario)
                                        @php
                                            $perfil = $usuario->tipo_usuario === 'estudiante' ? $usuario->estudiante : $usuario->docente;
                                            $nombre = trim(collect([$perfil?->nombres, $perfil?->apellido_paterno, $perfil?->apellido_materno])->filter()->implode(' '));
                                            $restaurable = $usuario->deleted_at?->gt(now()->subDays((int) config('retention.users.deletion_grace_period_days', 2))) ?? false;
                                        @endphp
                                        <tr class="hover:bg-slate-50/80">
                                            <td class="px-5 py-4"><p class="font-bold text-slate-800">{{ $nombre ?: 'Perfil sin nombre' }}</p><p class="mt-1 text-xs text-slate-400">ID #{{ $usuario->id }}</p></td>
                                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $usuario->username }}</td>
                                            <td class="px-5 py-4"><span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-bold capitalize text-rose-700">{{ $usuario->tipo_usuario }}</span></td>
                                            <td class="px-5 py-4 text-sm text-slate-500">{{ $usuario->deleted_at?->format('d/m/Y H:i') ?? 'No disponible' }}</td>
                                            <td class="px-5 py-4 text-right">
                                                <div class="flex flex-col items-end gap-2">
                                                    @if ($restaurable)
                                                        <form method="POST" action="{{ route('usuarios.restore', $usuario->id) }}" onsubmit="return confirm('¿Deseas restaurar este perfil y su cuenta?');">
                                                            @csrf
                                                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-emerald-700">Restaurar perfil</button>
                                                        </form>
                                                    @else
                                                        <span class="text-xs font-semibold text-slate-400">Plazo de restauración vencido</span>
                                                    @endif
                                                    <form method="POST" action="{{ route('usuarios.finalize', $usuario->id) }}" onsubmit="return confirm('No se podrá restaurar. Se anonimizarán los datos del sistema; el PDF original, que contiene datos personales, se conservará indefinidamente junto con el historial. ¿Deseas continuar?');">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-rose-700">Eliminar definitivamente</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($usuarios->hasPages())
                            <div class="border-t border-slate-100 px-5 py-4">{{ $usuarios->links() }}</div>
                        @endif
                    @endif
                </section>
            </main>

            <div
                x-cloak
                x-show="pdfFilesOpen"
                x-transition.opacity
                @keydown.escape.window="pdfFilesOpen = false"
                @click.self="pdfFilesOpen = false"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="deleted-user-pdfs-title"
            >
                <section x-show="pdfFilesOpen" x-transition class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
                    <header class="flex shrink-0 items-center justify-between gap-4 bg-gradient-to-r from-slate-950 via-blue-950 to-indigo-950 px-5 py-4 text-white sm:px-7">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-cyan-300">Archivo de usuarios</p>
                            <h2 id="deleted-user-pdfs-title" class="mt-1 text-lg font-black sm:text-xl">PDF de perfiles eliminados</h2>
                        </div>
                        <button type="button" @click="pdfFilesOpen = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-300 transition hover:bg-white/10 hover:text-white" aria-label="Cerrar ventana de archivos PDF">
                            <x-icon name="x" class="h-5 w-5" />
                        </button>
                    </header>

                    <div class="dashboard-scrollbar min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-7">
                        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4">
                            <p class="text-xs font-black uppercase tracking-wider text-[#4959A6]">Carpeta donde se guardan</p>
                            <code class="mt-2 block break-all rounded-xl bg-white px-3 py-2 text-xs text-slate-700 ring-1 ring-indigo-100">{{ $rutaDirectorioPdf }}</code>
                            <p class="mt-2 text-xs text-slate-500">Ruta relativa: <code>storage/app/public/{{ $directorioPdf }}</code></p>
                            <p class="mt-2 text-xs leading-5 text-slate-500">Los PDF contienen la información personal original y se conservan indefinidamente, incluso después de anonimizar el perfil.</p>
                        </div>

                        <div>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-bold text-slate-800">Archivos disponibles</h3>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $archivosPdf->count() }}</span>
                            </div>
                            <div class="space-y-2">
                                @forelse ($archivosPdf as $archivo)
                                    <a href="{{ $archivo['url'] }}" target="_blank" rel="noopener" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm transition hover:border-indigo-200 hover:bg-indigo-50">
                                        <span class="min-w-0 break-all font-semibold text-slate-700">{{ $archivo['nombre'] }}</span>
                                        <span class="shrink-0 text-xs font-bold text-[#4959A6]">Abrir PDF</span>
                                    </a>
                                @empty
                                    <p class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">Todavía no hay archivos PDF guardados en esta carpeta.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </x-admin-shell>
</x-admin-layout>
