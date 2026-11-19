<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 36px;
        }

        body {
            color: #1e293b;
            font-family: DejaVu Sans, sans-serif;
            text-align: center;
        }

        .label {
            border: 1px solid #dbeafe;
            border-radius: 22px;
            margin-top: 18px;
            padding: 38px 24px 32px;
        }

        .eyebrow {
            color: #4959a6;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 0 0 12px;
            text-transform: uppercase;
        }

        h1 {
            color: #172554;
            font-size: 27px;
            margin: 0 0 10px;
        }

        .asset-code {
            background: #eef2ff;
            border-radius: 12px;
            color: #3730a3;
            display: inline-block;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 0;
            padding: 8px 14px;
        }

        .qr-frame {
            background: #ffffff;
            border: 1px solid #dbeafe;
            border-radius: 16px;
            display: inline-block;
            margin-top: 28px;
            padding: 8px;
        }

        .qr-code {
            display: block;
            height: 480px;
            width: 480px;
        }

        .caption {
            border-top: 1px solid #e2e8f0;
            color: #272f7a;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1.8px;
            margin: 28px 0 0;
            padding-top: 20px;
        }

        .instruction {
            color: #64748b;
            font-size: 11px;
            margin: 8px 0 0;
        }

        .details {
            color: #475569;
            font-size: 10px;
            line-height: 1.5;
            margin: 12px auto 0;
        }
    </style>
</head>
<body>
    <main class="label">
        <header>
            <p class="eyebrow">{{ $activo->categoria?->nombre ?? 'Identificación de activo' }} · {{ ucfirst($activo->categoria?->grupo ?? 'activo') }}</p>
            <h1>{{ $activo->nombre }}</h1>
            <p class="asset-code">{{ $activo->codigo }}</p>
            <p class="details">
                Estado: {{ ucfirst(str_replace('_', ' ', $activo->estado_uso ?? 'no registrado')) }}
                · Ubicación: {{ $activo->ubicacion ?: 'No registrada' }}
                @if ($activo->marca || $activo->modelo)
                    · {{ trim(($activo->marca ?? '').' '.($activo->modelo ?? '')) }}
                @endif
                @if ($activo->numero_serie)
                    · Serie: {{ $activo->numero_serie }}
                @endif
            </p>
        </header>
        <div class="qr-frame">
            <img class="qr-code" src="{{ $qrDataUri }}" alt="Código QR de {{ $activo->nombre }}">
        </div>
        <footer>
            <p class="caption">INVENTARIO AULA DE DESARROLLO</p>
            <p class="instruction">Escanea el código para consultar la ficha completa de este activo.</p>
        </footer>
    </main>
</body>
</html>
