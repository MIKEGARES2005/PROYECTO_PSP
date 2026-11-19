<?php

namespace App\Support;

final class DireccionEstudiante
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeDatos(array $datos, ?string $direccionAnterior = null): ?string
    {
        $zona = self::limpiar($datos['direccion_zona'] ?? null);
        $tipoVia = self::limpiar($datos['direccion_tipo_via'] ?? null);
        $via = self::limpiar($datos['direccion_via'] ?? null);
        $numeroPuerta = self::limpiar($datos['direccion_numero_puerta'] ?? null);
        $partes = [];

        if ($zona !== null) {
            $partes[] = 'Zn. '.$zona;
        }

        if ($via !== null) {
            $partes[] = $tipoVia !== null ? $tipoVia.' '.$via : $via;
        }

        if ($numeroPuerta !== null) {
            $partes[] = '#'.ltrim($numeroPuerta, '# ');
        }

        $direccion = implode(', ', $partes);

        return $direccion !== '' ? $direccion : self::limpiar($direccionAnterior);
    }

    /**
     * @return array{zona: string, tipo_via: string, via: string, numero_puerta: string}
     */
    public static function desglosar(?string $direccion): array
    {
        $componentes = [
            'zona' => '',
            'tipo_via' => '',
            'via' => '',
            'numero_puerta' => '',
        ];

        if (self::limpiar($direccion) === null) {
            return $componentes;
        }

        foreach (preg_split('/\s*,\s*/', trim($direccion)) ?: [] as $parte) {
            if (preg_match('/^Zn\.\s*(.*)$/i', $parte, $coincidencias) === 1) {
                $componentes['zona'] = trim($coincidencias[1]);

                continue;
            }

            if (preg_match('/^#\s*(.*)$/', $parte, $coincidencias) === 1) {
                $componentes['numero_puerta'] = trim($coincidencias[1]);

                continue;
            }

            if (preg_match('/^(Av\.|C\.|Avenida|Calle)\s*(.*)$/i', $parte, $coincidencias) === 1) {
                $componentes['tipo_via'] = match (strtolower($coincidencias[1])) {
                    'av.', 'avenida' => 'Av.',
                    default => 'C.',
                };
                $via = trim($coincidencias[2]);

                if (preg_match('/^(.*?)\s+#\s*(.*)$/', $via, $numero) === 1) {
                    $via = trim($numero[1]);
                    $componentes['numero_puerta'] = trim($numero[2]);
                }

                $componentes['via'] = $via;

                continue;
            }

            $componentes['via'] = trim(implode(' ', array_filter([
                $componentes['via'],
                $parte,
            ])));
        }

        return $componentes;
    }

    private static function limpiar(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor !== '' ? $valor : null;
    }
}
