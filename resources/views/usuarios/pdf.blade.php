@php
    $registroPersonal = match ($usuario->tipo_usuario) {
        'docente' => $usuario->docente,
        'estudiante' => $usuario->estudiante,
        'administrador' => $usuario->docente ?? $usuario->estudiante,
        default => null,
    };

    $nombreCompleto = $registroPersonal
        ? trim(collect([
            $registroPersonal->nombres ?? null,
            $registroPersonal->apellido_paterno ?? null,
            $registroPersonal->apellido_materno ?? null,
        ])->filter()->implode(' '))
        : '';
    $nombreMostrado = $nombreCompleto ?: ($usuario->name ?: $usuario->username);
    $perfilActivo = $registroPersonal
        ? in_array(strtolower((string) $registroPersonal->estado), ['activo', 'activa'], true)
        : (bool) $usuario->activo;
    $estadoCuenta = $perfilActivo ? 'Activo' : 'Inactivo';
    $fotoDataUri = null;

    if ($registroPersonal?->foto_personal && \Illuminate\Support\Facades\Storage::disk('public')->exists($registroPersonal->foto_personal)) {
        $extension = strtolower(pathinfo($registroPersonal->foto_personal, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
        $fotoDataUri = 'data:'.$mime.';base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($registroPersonal->foto_personal));
    }

    $iniciales = collect(explode(' ', $nombreMostrado))
        ->filter()
        ->map(fn ($parte) => mb_substr($parte, 0, 1))
        ->take(2)
        ->join('');

    $datosPerfil = [];

    if ($registroPersonal) {
        $datosPerfil = $usuario->tipo_usuario === 'estudiante'
            ? [
                'Nombres' => $registroPersonal->nombres,
                'Apellido paterno' => $registroPersonal->apellido_paterno,
                'Apellido materno' => $registroPersonal->apellido_materno,
                'Carnet de identidad' => trim($registroPersonal->ci.' '.($registroPersonal->ci_extension ?? '')),
                'Teléfono' => $registroPersonal->telefono,
                'Correo personal' => $registroPersonal->correo_personal,
                'Matrícula' => $registroPersonal->matricula,
                'Semestre' => $registroPersonal->semestre
                    ? $registroPersonal->semestre.'° semestre'
                    : null,
                'Generación de ingreso' => $registroPersonal->generacion_ingreso,
                'Correo institucional' => $registroPersonal->correo_institucional,
                'Dirección' => $registroPersonal->direccion,
            ]
            : [
                'Nombres' => $registroPersonal->nombres,
                'Apellido paterno' => $registroPersonal->apellido_paterno,
                'Apellido materno' => $registroPersonal->apellido_materno,
                'Carnet de identidad' => trim($registroPersonal->ci.' '.($registroPersonal->ci_extension ?? '')),
                'Teléfono' => $registroPersonal->telefono,
                'Correo personal' => $registroPersonal->correo_personal,
                'Cargo' => $registroPersonal->cargo,
                'Especialidad' => $registroPersonal->especialidad,
                'Nivel académico' => $registroPersonal->nivel_academico,
                'Fecha de ingreso' => $registroPersonal->fecha_ingreso
                    ? \Illuminate\Support\Carbon::parse($registroPersonal->fecha_ingreso)->format('d/m/Y')
                    : null,
            ];
    }

    $filasPerfil = array_chunk($datosPerfil, 2, true);
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Antecedente de usuario | {{ $nombreMostrado }}</title>
    <style>
        @page {
            margin: 1cm 1.5cm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #1e293b;
            background: #ffffff;
            font-family: "Helvetica", "Arial", sans-serif;
            font-size: 13px;
            line-height: 1.4;
        }

        .header-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 12px;
            background: #0b1226;
            margin-bottom: 14px;
        }

        .header-table td {
            padding: 12px 14px;
            vertical-align: middle;
        }

        .header-title {
            font-size: 19px;
            margin: 0;
            color: #ffffff;
            line-height: 1.25;
        }

        .header-subtitle {
            font-size: 13px;
            margin: 4px 0 0;
            color: #cbd5e1;
        }

        .header-right {
            text-align: right;
        }

        .document-type {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
            color: #e0f2fe;
            line-height: 1.4;
        }

        .section-title {
            font-size: 15px;
            color: #272f7a;
            margin: 13px 0 6px;
            text-transform: uppercase;
            line-height: 1.3;
        }

        .section-subtitle {
            font-size: 13px;
            color: #666;
            margin-bottom: 9px;
            display: block;
            line-height: 1.4;
        }

        .profile-header {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 12px;
            background: #1e2a5a;
            margin-bottom: 14px;
        }

        .photo-cell {
            width: 170px;
            vertical-align: middle;
            padding: 14px;
        }

        .photo-img {
            width: 140px;
            height: 140px;
            border: 3px solid #ffffff;
            border-radius: 14px;
            object-fit: cover;
        }

        .photo-placeholder {
            width: 140px;
            height: 140px;
            border: 3px solid #ffffff;
            border-radius: 14px;
            background: #e5eaff;
            color: #272f7a;
            font-size: 36px;
            font-weight: bold;
            text-align: center;
            line-height: 140px;
        }

        .profile-info {
            vertical-align: middle;
            padding: 14px 14px 14px 0;
            color: #ffffff;
        }

        .profile-name {
            font-size: 26px;
            margin: 0 0 8px;
            color: #ffffff;
            line-height: 1.3;
        }

        .account-summary {
            width: 90%;
            border-collapse: separate;
            border-spacing: 3px 0;
            margin: 0;
        }

        .account-summary-cell {
            width: 33.333%;
            padding: 4px 5px;
            border: 1px solid #4959a6;
            border-radius: 5px;
            background: #272f7a;
            vertical-align: top;
        }

        .account-summary-label {
            display: inline;
            margin-right: 3px;
            color: #c7d2fe;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .account-summary-value {
            display: inline;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            line-height: 1.35;
            word-wrap: break-word;
        }

        .account-summary-active {
            border-color: #34d399;
            background: #065f46;
        }

        .account-summary-active .account-summary-label {
            color: #a7f3d0;
        }

        .account-summary-inactive {
            border-color: #fda4af;
            background: #881337;
        }

        .account-summary-inactive .account-summary-label {
            color: #fecdd3;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 7px 6px;
            margin-bottom: 9px;
        }

        .data-table td {
            width: 50%;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            vertical-align: top;
        }

        .data-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            display: block;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .data-value {
            display: block;
            font-size: 14px;
            font-weight: bold;
            color: #1e293b;
            line-height: 1.45;
            word-wrap: break-word;
        }

        .empty-profile {
            margin-top: 9px;
            padding: 10px 12px;
            color: #865b12;
            background: #fff8e8;
            font-size: 13px;
            border-left: 3px solid #d9a036;
            line-height: 1.45;
        }

        .notice {
            margin-top: 14px;
            padding: 10px 12px;
            color: #4b5872;
            background: #eef8fa;
            font-size: 12px;
            border-left: 3px solid #27a9bd;
            line-height: 1.45;
        }

        .footer {
            width: 100%;
            margin-top: 12px;
            padding-top: 7px;
            border-top: 1px solid #ddd;
            color: #777;
            font-size: 11px;
            line-height: 1.4;
        }

        .footer-right {
            text-align: right;
        }

        .section-separator {
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td width="60%">
                <h1 class="header-title">ITIBB CENTRO DE CONTROL</h1>
                <p class="header-subtitle">Plantilla de usuario</p>
            </td>
            <td width="40%" class="header-right">
                <p class="document-type">Ficha oficial de datos personales</p>
            </td>
        </tr>
    </table>

    <table class="profile-header">
        <tr>
            <td class="photo-cell">
                @if ($fotoDataUri)
                    <img src="{{ $fotoDataUri }}" class="photo-img" alt="Fotografía de {{ $nombreMostrado }}">
                @else
                    <div class="photo-placeholder">{{ $iniciales ?: 'U' }}</div>
                @endif
            </td>
            <td class="profile-info">
                <h3 class="profile-name">{{ $nombreMostrado }}</h3>
                <table class="account-summary">
                    <tr>
                        <td class="account-summary-cell">
                            <span class="account-summary-label">USUARIO:</span>
                            <span class="account-summary-value">{{ $usuario->username ?: 'No registrado' }}</span>
                        </td>
                        <td class="account-summary-cell">
                            <span class="account-summary-label">TIPO:</span>
                            <span class="account-summary-value">{{ ucfirst($usuario->tipo_usuario ?? 'No definido') }}</span>
                        </td>
                        <td class="account-summary-cell {{ $perfilActivo ? 'account-summary-active' : 'account-summary-inactive' }}">
                            <span class="account-summary-label">ESTADO:</span>
                            <span class="account-summary-value">{{ $estadoCuenta }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($filasPerfil)
        <h2 class="section-title section-separator">Información personal</h2>
        <span class="section-subtitle">Datos personales, académicos y de contacto registrados.</span>
        <table class="data-table">
            @foreach ($filasPerfil as $fila)
                <tr>
                    @foreach ($fila as $etiqueta => $valor)
                        <td @if (count($fila) === 1) colspan="2" @endif>
                            <span class="data-label">{{ $etiqueta }}</span>
                            <span class="data-value">{{ $valor ?: '-' }}</span>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @else
        <div class="empty-profile">Esta cuenta todavía no tiene un registro personal asociado.</div>
    @endif

    <div class="notice"><strong>Seguridad:</strong> las contraseñas y las respuestas a las preguntas de seguridad no se muestran por este medio.</div>

    <table class="footer">
        <tr>
            <td width="50%">Documento generado desde el Centro de control ITIBB</td>
            <td width="50%" class="footer-right">Emitido el {{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
</body>
</html>