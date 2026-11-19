<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitar equipo · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800" data-authenticated-portal="student">
    <header class="border-b border-emerald-100 bg-white"><div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-6"><div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 font-black text-white">IT</span><div><p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Portal del estudiante</p><p class="text-sm font-bold">{{ config('app.name') }}</p></div></div><a href="{{ route('estudiante.dashboard') }}" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50">Volver al panel</a></div></header>
    <main class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6">
        <section class="rounded-3xl bg-gradient-to-r from-emerald-700 to-teal-500 p-6 text-white shadow-lg"><p class="text-xs font-bold uppercase tracking-widest text-emerald-100">Equipos</p><h1 class="mt-2 text-3xl font-black">Solicitar equipo</h1><p class="mt-2 text-sm text-emerald-50">La solicitud será revisada por el docente o la administración.</p></section>
        @if ($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('estudiante.equipment.store') }}" class="space-y-6 rounded-3xl border border-emerald-100 bg-white p-6 shadow-sm sm:p-8">@csrf
            <div><label class="text-sm font-bold text-slate-700">Materia y horario</label><select name="horario_id" required class="mt-2 w-full rounded-xl border-slate-300"><option value="">Selecciona una clase</option>@foreach ($horarios as $horario)<option value="{{ $horario->id }}" @selected(old('horario_id') == $horario->id)>{{ $horario->materia?->nombre }} · {{ \App\Models\Horario::DIAS[$horario->dia_semana] ?? $horario->dia_semana }} {{ substr((string) $horario->hora_inicio, 0, 5) }}–{{ substr((string) $horario->hora_fin, 0, 5) }} · Aula {{ $horario->aula ?: 'Sin aula' }}</option>@endforeach</select>@if ($horarios->isEmpty())<p class="mt-2 text-xs text-amber-700">No tienes horarios con inscripción vigente para solicitar un equipo.</p>@endif</div>
            <div class="grid gap-4 sm:grid-cols-2"><div><label class="text-sm font-bold text-slate-700">Tipo de equipo</label><select name="tipo_equipo" required class="mt-2 w-full rounded-xl border-slate-300"><option value="cpu">Computadora</option><option value="tablet">Tablet</option></select></div><div><label class="text-sm font-bold text-slate-700">Fecha de uso</label><input type="date" name="fecha_uso" min="{{ now()->toDateString() }}" value="{{ old('fecha_uso') }}" required class="mt-2 w-full rounded-xl border-slate-300"></div></div>
            <div><label class="text-sm font-bold text-slate-700">Observaciones <span class="font-normal text-slate-400">(opcional)</span></label><textarea name="observaciones" rows="4" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Indica algún detalle importante para tu solicitud">{{ old('observaciones') }}</textarea></div>
            <button class="w-full rounded-xl bg-emerald-600 px-5 py-3 font-black text-white shadow-sm hover:bg-emerald-700">Enviar solicitud</button>
        </form>
    </main>
</body>
</html>
