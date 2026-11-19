<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071426">
    <title>Ficha del equipo · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: dark; font-family: system-ui, sans-serif; background: #071426; color: #f3f7ff; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 32px 16px; background: radial-gradient(ellipse at top, #153457, #071426 65%); }
        main { width: min(100%, 680px); margin: 0 auto; }
        .card { margin-top: 20px; padding: 24px; border: 1px solid #203b5b; border-radius: 20px; background: #10243d; }
        .eyebrow { color: #42c8f5; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        h1, h2, p { margin-top: 0; }
        h1 { margin-bottom: 6px; font-size: clamp(1.6rem, 6vw, 2.2rem); overflow-wrap: anywhere; }
        h2 { margin-bottom: 16px; font-size: 1.15rem; }
        .muted, dt { color: #9cb0ca; }
        dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 20px 0 0; }
        dl div, .complement { padding: 14px; border: 1px solid #203b5b; border-radius: 14px; background: #0b1c31; }
        dt { font-size: .75rem; }
        dd { margin: 5px 0 0; font-weight: 600; overflow-wrap: anywhere; }
        .complement + .complement { margin-top: 10px; }
        .empty { margin: 0; padding: 18px; border: 1px dashed #38516e; border-radius: 14px; color: #9cb0ca; text-align: center; }
        .badge { display: inline-block; margin-top: 12px; padding: 6px 10px; border-radius: 999px; background: #173c59; color: #9ce7ff; font-size: .82rem; }
    </style>
</head>
<body>
    <main>
        <p class="eyebrow">Sistema de Equipos ITIBB</p>
        <h1>Ficha del equipo</h1>
        <p class="muted">Información de inventario y de los periféricos y complementos vinculados.</p>

        <section class="card" aria-labelledby="equipment-title">
            <h2 id="equipment-title">{{ $equipo->identificador }}</h2>
            <span class="badge">{{ ['cpu' => 'Computadora (PC)', 'tablet' => 'Tablet', 'mini_pc' => 'Mini PC', 'router' => 'Router', 'wifi' => 'Equipo Wi-Fi'][$equipo->tipo_equipo] ?? $equipo->tipo_equipo }}</span>
            <dl>
                <div><dt>Código de inventario</dt><dd>{{ $equipo->activo?->codigo ?? 'No registrado' }}</dd></div>
                <div><dt>Nombre</dt><dd>{{ $equipo->activo?->nombre ?? 'No registrado' }}</dd></div>
                <div><dt>Marca y modelo</dt><dd>{{ trim(($equipo->activo?->marca ?? '').' '.($equipo->activo?->modelo ?? '')) ?: 'No registrados' }}</dd></div>
                <div><dt>Estado</dt><dd>{{ ucfirst(str_replace('_', ' ', $equipo->activo?->estado_uso ?? 'no registrado')) }}</dd></div>
                <div><dt>Ubicación</dt><dd>{{ $equipo->activo?->ubicacion ?: 'No registrada' }}</dd></div>
                <div><dt>Modo de uso</dt><dd>{{ ucfirst($equipo->modo_uso) }}</dd></div>
            </dl>
        </section>

        <section class="card" aria-labelledby="complements-title">
            <h2 id="complements-title">Periféricos y complementos vinculados</h2>
            @forelse ($equipo->complementos as $complemento)
                <article class="complement">
                    <strong>{{ $complemento->activo?->nombre ?? $complemento->tipo_complemento }}</strong>
                    <p class="muted" style="margin: 6px 0 0">
                        {{ $complemento->tipo_complemento }}
                        · Código {{ $complemento->activo?->codigo ?? 'sin código' }}
                        · Estado {{ ucfirst($complemento->estado_fisico) }}
                    </p>
                </article>
            @empty
                <p class="empty">Este equipo no tiene periféricos ni complementos vinculados actualmente.</p>
            @endforelse
        </section>
    </main>
</body>
</html>
