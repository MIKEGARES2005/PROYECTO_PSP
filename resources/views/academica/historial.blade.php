<x-admin-layout>
    <x-admin-shell>
        <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-[#4959A6]">Administración / Académica</p>
                    <h1 class="mt-2 text-3xl font-bold text-slate-800">Historial de {{ $gestion }}</h1>
                    <p class="mt-2 text-sm text-slate-500">Resultados archivados al cierre. Estos registros ya no son inscripciones activas.</p>
                </div>
                <a href="{{ route('gestion-academica.index') }}" class="inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-white px-4 py-2.5 text-sm font-semibold text-[#272F7A] shadow-sm transition hover:bg-indigo-50">Volver a Gestión académica</a>
            </header>

            <section class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-indigo-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Estudiante</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Materia</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Tipo</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Resultado / nota</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Decisión de progresión</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Archivado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($inscripciones as $inscripcion)
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-semibold text-slate-800">{{ $inscripcion->estudiante_nombre }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $inscripcion->matricula ?: 'Sin matrícula' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-semibold text-slate-800">{{ $inscripcion->materia_nombre }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $inscripcion->semestre }} semestre</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ ucfirst($inscripcion->tipo) }}</td>
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-semibold text-slate-800">{{ $inscripcion->resultado_final }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $inscripcion->nota_final !== null ? number_format((float) $inscripcion->nota_final, 2) : 'Sin nota registrada' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-700">{{ ucfirst(str_replace('_', ' ', $inscripcion->decision_progresion)) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">{{ \Illuminate\Support\Carbon::parse($inscripcion->archivado_en)->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-4">{{ $inscripciones->links() }}</div>
            </section>
        </main>
    </x-admin-shell>
</x-admin-layout>
