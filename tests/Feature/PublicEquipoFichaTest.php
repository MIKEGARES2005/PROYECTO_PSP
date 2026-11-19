<?php

use App\Models\Activo;
use App\Models\Categoria;
use App\Models\Complemento;
use App\Models\Equipo;

test('the equipment QR opens a public inventory ficha with only current complements', function () {
    $equipmentCategory = Categoria::create([
        'nombre' => 'Equipos QR',
        'grupo' => 'equipo',
        'estado' => 'activa',
    ]);
    $complementCategory = Categoria::create([
        'nombre' => 'Complementos QR',
        'grupo' => 'electronico',
        'estado' => 'activa',
    ]);
    $equipmentAsset = Activo::create([
        'codigo' => 'QR-EQ-001',
        'categoria_id' => $equipmentCategory->id,
        'nombre' => 'Tablet de laboratorio',
        'marca' => 'TLC',
        'modelo' => 'TAB10L',
        'numero_serie' => 'SERIE-PRIVADA',
        'estado_uso' => 'asignado',
        'ubicacion' => 'Laboratorio 2',
    ]);
    $equipo = Equipo::create([
        'activo_id' => $equipmentAsset->id,
        'identificador' => 'TABLET-QR-001',
        'tipo_equipo' => 'tablet',
        'modo_uso' => 'individual',
    ]);

    $activeComplementAsset = Activo::create([
        'codigo' => 'QR-COMP-001',
        'categoria_id' => $complementCategory->id,
        'nombre' => 'Teclado inalámbrico',
        'estado_uso' => 'disponible',
    ]);
    $activeComplement = Complemento::create([
        'activo_id' => $activeComplementAsset->id,
        'tipo_complemento' => 'Teclado',
        'estado_fisico' => 'bueno',
    ]);
    $equipo->complementos()->attach($activeComplement->id, [
        'fecha_inicio' => now(),
    ]);

    $endedComplementAsset = Activo::create([
        'codigo' => 'QR-COMP-002',
        'categoria_id' => $complementCategory->id,
        'nombre' => 'Mouse desvinculado',
        'estado_uso' => 'disponible',
    ]);
    $endedComplement = Complemento::create([
        'activo_id' => $endedComplementAsset->id,
        'tipo_complemento' => 'Mouse',
        'estado_fisico' => 'regular',
    ]);
    $equipo->complementos()->attach($endedComplement->id, [
        'fecha_inicio' => now()->subDay(),
        'fecha_fin' => now(),
    ]);

    $this->get(route('equipos.ficha-publica', $equipo))
        ->assertOk()
        ->assertSeeText('Ficha del equipo')
        ->assertSeeText('TABLET-QR-001')
        ->assertSeeText('TLC TAB10L')
        ->assertSeeText('Periféricos y complementos vinculados')
        ->assertSeeText('Teclado inalámbrico')
        ->assertDontSeeText('Mouse desvinculado')
        ->assertDontSeeText('SERIE-PRIVADA')
        ->assertDontSeeText('Iniciar sesión');
});
