<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen {{ $tipoPeriodo }}</title>
    <style>
        @page {
            margin: 32px 38px;
        }

        body {
            color: #1e293b;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
        }

        h1,
        h2,
        p {
            margin: 0;
        }

        .header {
            border-bottom: 3px solid #272f7a;
            margin-bottom: 20px;
            padding-bottom: 14px;
        }

        .eyebrow {
            color: #4959a6;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        h1 {
            color: #172554;
            font-size: 21px;
            margin-top: 5px;
        }

        .subtitle {
            color: #475569;
            margin-top: 7px;
        }

        .meta {
            color: #64748b;
            line-height: 1.7;
            margin-top: 11px;
        }

        section {
            margin-top: 18px;
        }

        h2 {
            background: #eef2ff;
            border-left: 3px solid #4959a6;
            color: #272f7a;
            font-size: 12px;
            padding: 8px 10px;
        }

        table {
            border-collapse: collapse;
            margin-top: 7px;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #dbe3ee;
            padding: 7px 9px;
            text-align: left;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 9px;
            text-transform: uppercase;
        }

        td.value {
            color: #172554;
            font-size: 12px;
            font-weight: bold;
            text-align: right;
            width: 22%;
        }

        .note {
            color: #64748b;
            font-size: 9px;
            line-height: 1.6;
            margin-top: 18px;
        }
    </style>
</head>
<body>
    <header class="header">
        <p class="eyebrow">Sistema de Equipos</p>
        <h1>Resumen de reportes del sistema</h1>
        <p class="subtitle">Reporte {{ $tipoPeriodo }}</p>
        <div class="meta">
            <p>Periodo: {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</p>
            <p>Generado por: {{ $generadoPor }}</p>
            <p>Fecha de generación: {{ $generadoEn->format('d/m/Y H:i') }}</p>
        </div>
    </header>

    <section>
        <h2>Inventario actual</h2>
        <table>
            <tbody>
                <tr><td>Total de equipos</td><td class="value">{{ $estadisticas['totalEquipos'] }}</td></tr>
                <tr><td>Disponibles</td><td class="value">{{ $estadisticas['equiposDisponibles'] }}</td></tr>
                <tr><td>En uso</td><td class="value">{{ $estadisticas['equiposEnUso'] }}</td></tr>
                <tr><td>Otros estados</td><td class="value">{{ $estadisticas['equiposOtros'] }}</td></tr>
                <tr><td>Accesorios registrados</td><td class="value">{{ $estadisticas['totalAccesorios'] }}</td></tr>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Actividad del periodo</h2>
        <table>
            <thead>
                <tr><th>Indicador</th><th class="value">Total</th></tr>
            </thead>
            <tbody>
                <tr><td>Asignaciones</td><td class="value">{{ $estadisticas['totalAsignaciones'] }}</td></tr>
                <tr><td>Asignaciones activas</td><td class="value">{{ $estadisticas['asignacionesActivas'] }}</td></tr>
                <tr><td>Asignaciones finalizadas</td><td class="value">{{ $estadisticas['asignacionesFinalizadas'] }}</td></tr>
                <tr><td>Incidencias</td><td class="value">{{ $estadisticas['totalIncidencias'] }}</td></tr>
                <tr><td>Incidencias pendientes</td><td class="value">{{ $estadisticas['incidenciasPendientes'] }}</td></tr>
                <tr><td>Incidencias resueltas</td><td class="value">{{ $estadisticas['incidenciasResueltas'] }}</td></tr>
                <tr><td>Incidencias reportadas desde la app</td><td class="value">{{ $estadisticas['incidenciasApp'] }}</td></tr>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Estado de aplicaciones y diagnósticos</h2>
        <table>
            <tbody>
                <tr><td>Apps conectadas</td><td class="value">{{ $estadisticas['dispositivosConectados'] }}</td></tr>
                <tr><td>Apps desconectadas</td><td class="value">{{ $estadisticas['dispositivosDesconectados'] }}</td></tr>
                <tr><td>Equipos sin reportes</td><td class="value">{{ $estadisticas['dispositivosSinReportes'] }}</td></tr>
                <tr><td>Diagnósticos registrados</td><td class="value">{{ $estadisticas['totalDiagnosticos'] }}</td></tr>
                <tr><td>Diagnósticos críticos</td><td class="value">{{ $estadisticas['diagnosticosCriticos'] }}</td></tr>
                <tr><td>Alertas abiertas</td><td class="value">{{ $estadisticas['alertasAbiertas'] }}</td></tr>
            </tbody>
        </table>
    </section>

    <p class="note">Las asignaciones e incidencias corresponden al periodo indicado. El inventario, la conectividad y los diagnósticos reflejan el estado al momento de generar este documento.</p>
</body>
</html>
