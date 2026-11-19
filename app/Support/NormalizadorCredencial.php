<?php

namespace App\Support;

use Illuminate\Support\Str;

final class NormalizadorCredencial
{
    public static function normalizar(string $valor): string
    {
        $valor = Str::lower(trim($valor));
        $valorDescompuesto = preg_replace('/n\x{0303}/u', 'ñ', $valor);

        if ($valorDescompuesto === null) {
            throw new \RuntimeException('No se pudo normalizar el texto para generar las credenciales.');
        }

        $partesValor = preg_split('/(ñ)/u', $valorDescompuesto, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($partesValor === false) {
            throw new \RuntimeException('No se pudo normalizar el texto para generar las credenciales.');
        }

        $valorNormalizado = '';

        foreach ($partesValor as $parte) {
            $valorNormalizado .= $parte === 'ñ' ? 'ñ' : Str::ascii($parte);
        }

        $valorSanitizado = preg_replace('/[^a-z0-9ñ]/u', '', $valorNormalizado);

        if ($valorSanitizado === null) {
            throw new \RuntimeException('No se pudo normalizar el texto para generar las credenciales.');
        }

        return $valorSanitizado;
    }
}
