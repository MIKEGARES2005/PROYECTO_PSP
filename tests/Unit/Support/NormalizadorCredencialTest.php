<?php

use App\Support\NormalizadorCredencial;

test('preserves the Spanish eñe while normalizing credential names', function () {
    expect(NormalizadorCredencial::normalizar('CASTAÑETA'))->toBe('castañeta')
        ->and(NormalizadorCredencial::normalizar("CASTAN\u{0303}ETA"))->toBe('castañeta')
        ->and(NormalizadorCredencial::normalizar('María-Pérez'))->toBe('mariaperez');
});
