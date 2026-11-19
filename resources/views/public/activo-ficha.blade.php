<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071426">
    <title>Ficha del activo · {{ config('app.name') }}</title>
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
        dl div { padding: 14px; border: 1px solid #203b5b; border-radius: 14px; background: #0b1c31; }
        dt { font-size: .75rem; }
        dd { margin: 5px 0 0; font-weight: 600; overflow-wrap: anywhere; }
        .badge { display: inline-block; margin-top: 12px; padding: 6px 10px; border-radius: 999px; background: #173c59; color: #9ce7ff; font-size: .82rem; }
    </style>
</head>
<body>
    <main>
        <p class="eyebrow">Sistema de Equipos ITIBB</p>
        <h1>Ficha del activo</h1>
        <p class="muted">Información patrimonial consultada mediante su código QR.</p>

        <section class="card" aria-labelledby="asset-title">
            <h2 id="asset-title">{{ $activo->nombre }}</h2>
            <span class="badge">{{ $activo->categoria?->nombre ?? 'Activo institucional' }} · {{ ucfirst($activo->categoria?->grupo ?? 'general') }}</span>
            <dl>
                <div><dt>Código de inventario</dt><dd>{{ $activo->codigo }}</dd></div>
                <div><dt>Nombre</dt><dd>{{ $activo->nombre }}</dd></div>
                <div><dt>Marca y modelo</dt><dd>{{ trim(($activo->marca ?? '').' '.($activo->modelo ?? '')) ?: 'No registrados' }}</dd></div>
                <div><dt>Estado</dt><dd>{{ ucfirst(str_replace('_', ' ', $activo->estado_uso ?? 'no registrado')) }}</dd></div>
                <div><dt>Ubicación</dt><dd>{{ $activo->ubicacion ?: 'No registrada' }}</dd></div>
                <div><dt>Fecha de adquisición</dt><dd>{{ $activo->fecha_adquisicion?->format('d/m/Y') ?? 'No registrada' }}</dd></div>
            </dl>
        </section>
    </main>
</body>
</html>
