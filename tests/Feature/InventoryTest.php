<?php

use App\Models\Accesorio;
use App\Models\Activo;
use App\Models\Categoria;
use App\Models\Complemento;
use App\Models\Diagnostico;
use App\Models\Docente;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
});

test('the patrimonial inventory panel groups categories and displays asset records', function () {
    $admin = inventoryAdminUser();
    $category = Categoria::create([
        'nombre' => 'Muebles de laboratorio',
        'grupo' => 'mobiliario',
        'estado' => 'activa',
    ]);
    Activo::create([
        'codigo' => 'MOB-001',
        'categoria_id' => $category->id,
        'nombre' => 'Mesa de trabajo',
        'estado_uso' => 'disponible',
    ]);

    $this->actingAs($admin)
        ->get(route('inventario.index'))
        ->assertOk()
        ->assertSeeText('Inventario patrimonial')
        ->assertSeeText('Muebles de laboratorio')
        ->assertSeeText('Mesa de trabajo')
        ->assertSeeText('Muebles');
});

test('inventory category filter shows only assets from the selected category', function () {
    $admin = inventoryAdminUser();
    $chairs = Categoria::create([
        'nombre' => 'Sillas',
        'grupo' => 'mobiliario',
        'estado' => 'activa',
        'prefijo_codigo' => 'SIL',
    ]);
    $tables = Categoria::create([
        'nombre' => 'Mesas',
        'grupo' => 'mobiliario',
        'estado' => 'activa',
        'prefijo_codigo' => 'MES',
    ]);
    Activo::create([
        'codigo' => 'SIL-0001',
        'categoria_id' => $chairs->id,
        'nombre' => 'Silla azul',
        'estado_uso' => 'disponible',
    ]);
    Activo::create([
        'codigo' => 'MES-0001',
        'categoria_id' => $tables->id,
        'nombre' => 'Mesa grande',
        'estado_uso' => 'disponible',
    ]);

    $this->actingAs($admin)
        ->get(route('inventario.index', ['categoria' => $chairs->id]))
        ->assertOk()
        ->assertSeeText('Silla azul')
        ->assertDontSeeText('Mesa grande')
        ->assertSee('value="'.$chairs->id.'" selected', false);
});

test('administrators can select a category and view its editable data', function () {
    $admin = inventoryAdminUser();
    $category = Categoria::create([
        'nombre' => 'Sillas del aula',
        'grupo' => 'mobiliario',
        'estado' => 'activa',
        'prefijo_codigo' => 'SILLA',
    ]);

    $this->actingAs($admin)
        ->get(route('inventario.categorias.manage'))
        ->assertOk()
        ->assertSeeText('Selecciona una categoría')
        ->assertSeeText('Sillas del aula');

    $this->get(route('inventario.categorias.manage', ['categoria' => $category->id]))
        ->assertOk()
        ->assertSee('value="Sillas del aula"', false)
        ->assertSee('value="SILLA"', false)
        ->assertSeeText('Guardar cambios')
        ->assertSeeText('Eliminar categoría');
});

test('administrators register electronics as independent assets and complements as peripherals', function () {
    $admin = inventoryAdminUser();
    $electronicCategory = Categoria::create([
        'nombre' => 'Electrónicos independientes',
        'grupo' => 'electronico',
        'estado' => 'activa',
        'prefijo_codigo' => 'TV',
    ]);
    $this->assertDatabaseHas('categorias', [
        'nombre' => 'Periféricos y complementos',
        'grupo' => 'complemento',
    ]);
    $complementCategory = Categoria::query()->where('nombre', 'Periféricos y complementos')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('inventario.activos.create'))
        ->assertOk()
        ->assertSeeText('Electrónicos independientes')
        ->assertSeeText('Periféricos y complementos')
        ->assertSee('TV-0001', false);

    $this->actingAs($admin)
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $electronicCategory->id,
            'codigo' => 'CODIGO-MANUAL',
            'nombre' => 'Televisor del aula',
            'estado_uso' => 'disponible',
            'marca' => 'Samsung',
            'modelo' => 'TV-55',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $television = Activo::query()->where('codigo', 'TV-0001')->firstOrFail();
    $this->assertDatabaseMissing('equipos', ['activo_id' => $television->id]);
    $this->assertDatabaseMissing('complementos', ['activo_id' => $television->id]);

    $this->actingAs($admin)
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $complementCategory->id,
            'codigo' => 'CODIGO-MANUAL',
            'nombre' => 'Teclado USB',
            'estado_uso' => 'disponible',
            'tipo_complemento' => 'Teclado',
            'tipos_equipo_compatibles' => ['cpu', 'tablet', 'mini_pc'],
            'estado_fisico' => 'bueno',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $keyboard = Activo::query()->where('codigo', 'COM-0001')->firstOrFail();
    $this->assertDatabaseHas('complementos', [
        'activo_id' => $keyboard->id,
        'tipo_complemento' => 'Teclado',
    ]);
    $this->assertDatabaseMissing('equipos', ['activo_id' => $keyboard->id]);
});

test('the asset form disables required fields for hidden ficha types', function () {
    $admin = inventoryAdminUser();

    $this->actingAs($admin)
        ->get(route('inventario.activos.create'))
        ->assertOk()
        ->assertSee(":disabled=\"tipo !== 'equipo'\"", false)
        ->assertSee(":disabled=\"tipo !== 'complemento'\"", false);
});

test('mobiliario assets do not retain electronic make model or serial data', function () {
    $admin = inventoryAdminUser();
    $furnitureCategory = Categoria::create([
        'nombre' => 'Sillas',
        'grupo' => 'mobiliario',
        'estado' => 'activa',
        'prefijo_codigo' => 'SILLA',
    ]);

    $this->actingAs($admin)
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $furnitureCategory->id,
            'nombre' => 'Silla de aula',
            'estado_uso' => 'disponible',
            'marca' => 'Marca no aplicable',
            'modelo' => 'Modelo no aplicable',
            'numero_serie' => 'SER-SIL-001',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('activos', [
        'codigo' => 'SILLA-0001',
        'marca' => null,
        'modelo' => null,
        'numero_serie' => null,
    ]);
});

test('categories can be created updated and deleted from the inventory', function () {
    $admin = inventoryAdminUser();
    $this->actingAs($admin)
        ->from(route('inventario.categorias.create'))
        ->post(route('inventario.categorias.store'), [
            'nombre' => 'Mobiliario mal clasificado',
            'grupo' => 'mobiliario',
            'tipo_registro' => 'equipo',
            'prefijo_codigo' => 'BAD',
        ])
        ->assertRedirect(route('inventario.categorias.create'))
        ->assertSessionHasErrors('tipo_registro');

    $this->assertDatabaseMissing('categorias', ['nombre' => 'Mobiliario mal clasificado']);

    $this->actingAs($admin)
        ->post(route('inventario.categorias.store'), [
            'nombre' => 'Routers',
            'grupo' => 'red',
            'tipo_registro' => 'equipo',
            'prefijo_codigo' => 'rtr',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $category = Categoria::query()->where('nombre', 'Routers')->firstOrFail();
    expect($category->prefijo_codigo)->toBe('RTR')
        ->and($category->tipo_registro)->toBe('equipo');

    $this->actingAs($admin)
        ->put(route('inventario.categorias.update', $category), [
            'nombre' => 'Routers de red',
            'grupo' => 'red',
            'tipo_registro' => 'equipo',
            'estado' => 'activa',
            'prefijo_codigo' => 'RTR',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $category->refresh();
    expect($category->nombre)->toBe('Routers de red');

    $this->actingAs($admin)
        ->delete(route('inventario.categorias.destroy', $category))
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('categorias', ['id' => $category->id]);
});

test('categories with assets cannot change their ficha type or be deleted', function () {
    $admin = inventoryAdminUser();
    $category = Categoria::create([
        'nombre' => 'Equipos de red',
        'grupo' => 'red',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
        'prefijo_codigo' => 'RED',
    ]);
    Activo::create([
        'codigo' => 'RED-0001',
        'categoria_id' => $category->id,
        'nombre' => 'Router del laboratorio',
        'estado_uso' => 'disponible',
    ]);

    $this->actingAs($admin)
        ->from(route('inventario.categorias.edit', $category))
        ->put(route('inventario.categorias.update', $category), [
            'nombre' => $category->nombre,
            'grupo' => 'red',
            'tipo_registro' => 'activo',
            'estado' => 'activa',
            'prefijo_codigo' => 'RED',
        ])
        ->assertRedirect(route('inventario.categorias.edit', $category))
        ->assertSessionHasErrors('tipo_registro');

    $this->actingAs($admin)
        ->from(route('inventario.index'))
        ->delete(route('inventario.categorias.destroy', $category))
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHasErrors('categoria');

    $this->assertDatabaseHas('categorias', ['id' => $category->id]);
});

test('bulk registration creates one individually coded asset for each unit', function () {
    $admin = inventoryAdminUser();
    $category = Categoria::create([
        'nombre' => 'Sillas de aula',
        'grupo' => 'mobiliario',
        'tipo_registro' => 'activo',
        'estado' => 'activa',
        'prefijo_codigo' => 'SILLA',
    ]);

    $this->actingAs($admin)
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $category->id,
            'cantidad' => 3,
            'nombre' => 'Silla universitaria',
            'estado_uso' => 'disponible',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success', '3 activos registrados correctamente.');

    expect(Activo::query()->where('categoria_id', $category->id)->orderBy('codigo')->pluck('codigo')->all())
        ->toBe(['SILLA-0001', 'SILLA-0002', 'SILLA-0003']);
    $this->assertDatabaseCount('activos', 3);

    $this->actingAs($admin)
        ->from(route('inventario.activos.create'))
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $category->id,
            'cantidad' => 101,
            'nombre' => 'Lote demasiado grande',
            'estado_uso' => 'disponible',
        ])
        ->assertRedirect(route('inventario.activos.create'))
        ->assertSessionHasErrors('cantidad');

    $this->assertDatabaseCount('activos', 3);
});

test('assets can be viewed edited and safely deleted', function () {
    $admin = inventoryAdminUser();
    $category = Categoria::create([
        'nombre' => 'Pizarras',
        'grupo' => 'mobiliario',
        'tipo_registro' => 'activo',
        'estado' => 'activa',
        'prefijo_codigo' => 'PIZ',
    ]);

    $this->actingAs($admin)
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $category->id,
            'nombre' => 'Pizarra blanca',
            'estado_uso' => 'disponible',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $asset = Activo::query()->where('codigo', 'PIZ-0001')->firstOrFail();
    $this->actingAs($admin)
        ->get(route('inventario.activos.show', $asset))
        ->assertSeeText('Pizarra blanca');
    $this->actingAs($admin)
        ->get(route('inventario.activos.edit', $asset))
        ->assertSeeText('Editar activo patrimonial');

    $this->actingAs($admin)
        ->put(route('inventario.activos.update', $asset), [
            'categoria_id' => $category->id,
            'nombre' => 'Pizarra blanca magnética',
            'estado_uso' => 'disponible',
        ])
        ->assertRedirect(route('inventario.activos.show', $asset))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('activos', [
        'id' => $asset->id,
        'nombre' => 'Pizarra blanca magnética',
    ]);

    $this->actingAs($admin)
        ->delete(route('inventario.activos.destroy', $asset))
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('activos', ['id' => $asset->id]);
});

test('assets with diagnostic history cannot be deleted from inventory', function () {
    $admin = inventoryAdminUser();
    $category = Categoria::create([
        'nombre' => 'PC de laboratorio',
        'grupo' => 'equipo',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
        'prefijo_codigo' => 'PC',
    ]);
    $asset = Activo::create([
        'codigo' => 'PC-0001',
        'categoria_id' => $category->id,
        'nombre' => 'PC con diagnóstico',
        'estado_uso' => 'disponible',
    ]);
    $equipment = Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => 'PC-LAB-01',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    Diagnostico::create([
        'equipo_id' => $equipment->id,
        'tipo' => 'prueba',
        'nivel' => 'info',
        'codigo_regla' => 'PRUEBA-INVENTARIO',
        'fecha_hora' => now(),
    ]);

    $this->actingAs($admin)
        ->from(route('inventario.index'))
        ->delete(route('inventario.activos.destroy', $asset))
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHasErrors('activo');

    $this->assertDatabaseHas('activos', ['id' => $asset->id, 'deleted_at' => null]);
});

test('network devices and mini PCs use the equipment ficha while a LAN jack stays an independent asset', function () {
    $admin = inventoryAdminUser();
    $equipmentCategory = Categoria::create([
        'nombre' => 'Mini PCs',
        'grupo' => 'equipo',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
        'prefijo_codigo' => 'MINI',
    ]);
    $networkEquipmentCategory = Categoria::create([
        'nombre' => 'Equipos de red',
        'grupo' => 'red',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
        'prefijo_codigo' => 'NET',
    ]);
    $networkAssetCategory = Categoria::create([
        'nombre' => 'Conectores LAN',
        'grupo' => 'red',
        'tipo_registro' => 'activo',
        'estado' => 'activa',
        'prefijo_codigo' => 'LAN',
    ]);

    foreach (['mini_pc', 'router', 'wifi'] as $tipoEquipo) {
        $this->actingAs($admin)
            ->post(route('inventario.activos.store'), [
                'categoria_id' => $tipoEquipo === 'mini_pc' ? $equipmentCategory->id : $networkEquipmentCategory->id,
                'nombre' => $tipoEquipo,
                'tipo_equipo' => $tipoEquipo,
                'modo_uso' => 'individual',
                'estado_uso' => 'disponible',
            ])
            ->assertRedirect(route('inventario.index'))
            ->assertSessionHas('success');
    }

    $this->actingAs($admin)
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $networkAssetCategory->id,
            'nombre' => 'Conector jack doble de red LAN',
            'estado_uso' => 'disponible',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    expect(Equipo::query()->orderBy('id')->pluck('tipo_equipo')->all())
        ->toBe(['mini_pc', 'router', 'wifi']);
    $miniPc = Activo::query()->where('codigo', 'MINI-0001')->firstOrFail();
    $router = Activo::query()->where('codigo', 'NET-0001')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('inventario.activos.show', $miniPc))
        ->assertSeeText('Mini PC');
    $this->get(route('equipos.ficha-publica', $router->equipo))
        ->assertSeeText('Router');

    $this->assertDatabaseHas('activos', [
        'codigo' => 'LAN-0001',
        'nombre' => 'Conector jack doble de red LAN',
    ]);
    $this->assertDatabaseMissing('equipos', [
        'activo_id' => Activo::query()->where('codigo', 'LAN-0001')->value('id'),
    ]);
});

test('administrators can download an asset QR as a PDF', function () {
    $admin = inventoryAdminUser();
    $category = Categoria::create([
        'nombre' => 'Computadoras QR',
        'grupo' => 'equipo',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
        'prefijo_codigo' => 'PC',
    ]);
    $asset = Activo::create([
        'codigo' => 'PC-0001',
        'categoria_id' => $category->id,
        'nombre' => 'Computadora del aula',
        'estado_uso' => 'disponible',
    ]);
    $equipment = Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => 'PC-AULA-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    config(['app.url' => 'http://192.168.1.4:8000']);
    $fichaUrl = rtrim((string) config('app.url'), '/')
        .route('equipos.ficha-publica', $equipment, false);
    $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&format=png&data='.urlencode($fichaUrl);
    $png = inventoryQrPng();

    Http::preventStrayRequests();
    Http::fake([
        $qrApiUrl => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('inventario.activos.qr', $asset));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=qr-inventario-PC-0001.pdf');
    expect($response->getContent())->toStartWith('%PDF-');
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $qrApiUrl);
});

test('QR print layout places the asset name above the code and the inventory caption below it', function () {
    $html = view('inventario.activos.qr-pdf', [
        'activo' => new Activo(['nombre' => 'Computadora del laboratorio']),
        'qrDataUri' => 'data:image/png;base64,'.base64_encode(inventoryQrPng()),
    ])->render();

    $assetNamePosition = strpos($html, '<h1>Computadora del laboratorio</h1>');
    $qrImagePosition = strpos($html, '<img');
    $captionPosition = strpos($html, 'INVENTARIO AULA DE DESARROLLO');

    expect($assetNamePosition)->not->toBeFalse()
        ->and($assetNamePosition)->toBeLessThan($qrImagePosition)
        ->and($qrImagePosition)->toBeLessThan($captionPosition)
        ->and($html)->toContain('height: 480px;')
        ->and($html)->toContain('width: 480px;')
        ->and($html)->toContain('class="qr-frame"')
        ->and($html)->toContain('data:image/png;base64,')
        ->and($html)->toContain('INVENTARIO AULA DE DESARROLLO')
        ->and($html)->toContain('ficha completa de este activo');
});

test('all asset details offer QR PDF download and the inventory offers a bulk download', function () {
    $admin = inventoryAdminUser();
    $equipmentCategory = Categoria::create([
        'nombre' => 'Equipos con QR',
        'grupo' => 'equipo',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
    ]);
    $equipmentAsset = Activo::create([
        'codigo' => 'QR-PC-001',
        'categoria_id' => $equipmentCategory->id,
        'nombre' => 'PC con QR',
        'estado_uso' => 'disponible',
    ]);
    Equipo::create([
        'activo_id' => $equipmentAsset->id,
        'identificador' => 'PC-QR-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    $chairCategory = Categoria::create([
        'nombre' => 'Sillas con QR',
        'grupo' => 'mobiliario',
        'tipo_registro' => 'activo',
        'estado' => 'activa',
    ]);
    $chairAsset = Activo::create([
        'codigo' => 'QR-SILLA-001',
        'categoria_id' => $chairCategory->id,
        'nombre' => 'Silla con QR',
        'estado_uso' => 'disponible',
    ]);

    $this->actingAs($admin)
        ->get(route('inventario.activos.show', $equipmentAsset))
        ->assertSeeText('Generar QR PDF');
    $this->get(route('inventario.activos.show', $chairAsset))
        ->assertSeeText('Generar QR PDF');
});

test('only administrators can download individual asset QR PDFs', function () {
    $teacher = inventoryTeacherUser();
    $category = Categoria::create([
        'nombre' => 'Equipos QR restringidos',
        'grupo' => 'equipo',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => 'PC-RESTRINGIDA-001',
        'categoria_id' => $category->id,
        'nombre' => 'Equipo restringido',
        'estado_uso' => 'disponible',
    ]);
    Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => 'PC-RESTRINGIDA-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);

    $this->actingAs($teacher)
        ->get(route('inventario.activos.qr', $asset))
        ->assertForbidden();
});

test('asset QR download reports an error when the QR provider is unavailable', function () {
    $admin = inventoryAdminUser();
    $category = Categoria::create([
        'nombre' => 'Equipos QR con error',
        'grupo' => 'equipo',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => 'PC-ERROR-001',
        'categoria_id' => $category->id,
        'nombre' => 'Equipo con error QR',
        'estado_uso' => 'disponible',
    ]);
    Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => 'PC-ERROR-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    $fichaUrl = rtrim((string) config('app.url'), '/')
        .route('equipos.ficha-publica', $asset->equipo, false);
    $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&format=png&data='.urlencode($fichaUrl);

    Http::preventStrayRequests();
    Http::fake([
        $qrApiUrl => Http::response('QR provider unavailable', 503),
    ]);

    $this->actingAs($admin)
        ->get(route('inventario.activos.qr', $asset))
        ->assertStatus(502);
});

test('administrators can download a QR for a furniture asset without an equipment ficha', function () {
    $admin = inventoryAdminUser();
    $category = Categoria::create([
        'nombre' => 'Muebles sin QR de equipo',
        'grupo' => 'mobiliario',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => 'MOB-QR-001',
        'categoria_id' => $category->id,
        'nombre' => 'Silla sin QR de equipo',
        'ubicacion' => 'Aula 2',
        'estado_uso' => 'disponible',
    ]);

    $this->get(route('activos.ficha-publica', $asset))
        ->assertOk()
        ->assertSeeText('Silla sin QR de equipo')
        ->assertSeeText('MOB-QR-001')
        ->assertSeeText('Aula 2');

    $fichaUrl = rtrim((string) config('app.url'), '/')
        .route('activos.ficha-publica', $asset, false);
    $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&format=png&data='.urlencode($fichaUrl);
    Http::preventStrayRequests();
    Http::fake([
        $qrApiUrl => Http::response(inventoryQrPng(), 200, ['Content-Type' => 'image/png']),
    ]);

    $this->actingAs($admin)
        ->get(route('inventario.activos.qr', $asset))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('asset QR download rejects invalid PNG image content', function () {
    $admin = inventoryAdminUser();
    $category = Categoria::create([
        'nombre' => 'Equipos QR PNG inválido',
        'grupo' => 'equipo',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
    ]);
    $asset = Activo::create([
        'codigo' => 'PC-PNG-001',
        'categoria_id' => $category->id,
        'nombre' => 'Equipo con PNG inválido',
        'estado_uso' => 'disponible',
    ]);
    Equipo::create([
        'activo_id' => $asset->id,
        'identificador' => 'PC-PNG-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    $fichaUrl = rtrim((string) config('app.url'), '/')
        .route('equipos.ficha-publica', $asset->equipo, false);
    $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&format=png&data='.urlencode($fichaUrl);

    Http::preventStrayRequests();
    Http::fake([
        $qrApiUrl => Http::response('not a PNG image', 200, ['Content-Type' => 'image/png']),
    ]);

    $this->actingAs($admin)
        ->get(route('inventario.activos.qr', $asset))
        ->assertStatus(502);
});

test('patrimonial codes increment independently by category and are not reused after deletion', function () {
    $admin = inventoryAdminUser();
    $this->actingAs($admin)
        ->post(route('inventario.categorias.store'), [
            'nombre' => 'Mesas',
            'grupo' => 'mobiliario',
            'tipo_registro' => 'activo',
            'prefijo_codigo' => 'mesa',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $tableCategory = Categoria::query()->where('nombre', 'Mesas')->firstOrFail();
    expect($tableCategory->prefijo_codigo)->toBe('MESA');

    $this->actingAs($admin)
        ->from(route('inventario.categorias.create'))
        ->post(route('inventario.categorias.store'), [
            'nombre' => 'Mesas secundarias',
            'grupo' => 'mobiliario',
            'tipo_registro' => 'activo',
            'prefijo_codigo' => 'MESA',
        ])
        ->assertRedirect(route('inventario.categorias.create'))
        ->assertSessionHasErrors('prefijo_codigo');

    $this->actingAs($admin)
        ->get(route('inventario.activos.create', ['categoria' => $tableCategory->id]))
        ->assertSee('codigoActual: \'MESA-0001\'', false)
        ->assertDontSee('name="codigo"', false);

    $this->actingAs($admin)
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $tableCategory->id,
            'nombre' => 'Mesa de aula',
            'estado_uso' => 'disponible',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $firstTable = Activo::query()->where('categoria_id', $tableCategory->id)->firstOrFail();
    expect($firstTable->codigo)->toBe('MESA-0001');
    $firstTable->delete();

    $this->actingAs($admin)
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $tableCategory->id,
            'nombre' => 'Segunda mesa',
            'estado_uso' => 'disponible',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('activos', [
        'categoria_id' => $tableCategory->id,
        'codigo' => 'MESA-0002',
    ]);

    $this->actingAs($admin)
        ->post(route('inventario.categorias.store'), [
            'nombre' => 'Sillas',
            'grupo' => 'mobiliario',
            'tipo_registro' => 'activo',
            'prefijo_codigo' => 'SILLA',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $chairCategory = Categoria::query()->where('nombre', 'Sillas')->firstOrFail();
    $this->actingAs($admin)
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $chairCategory->id,
            'nombre' => 'Silla de aula',
            'estado_uso' => 'disponible',
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('activos', [
        'categoria_id' => $chairCategory->id,
        'codigo' => 'SILLA-0001',
    ]);
});

test('administrators can link and unlink a complement from an equipment asset', function () {
    $admin = inventoryAdminUser();
    $equipmentCategory = Categoria::create(['nombre' => 'Equipos de prueba', 'grupo' => 'equipo', 'tipo_registro' => 'equipo', 'estado' => 'activa']);
    $complementCategory = Categoria::create(['nombre' => 'Complementos de prueba', 'grupo' => 'complemento', 'tipo_registro' => 'complemento', 'estado' => 'activa']);
    $inactiveComplementCategory = Categoria::create(['nombre' => 'Complementos archivados', 'grupo' => 'complemento', 'tipo_registro' => 'complemento', 'estado' => 'inactiva']);
    $equipmentAsset = Activo::create([
        'codigo' => 'EQ-001',
        'categoria_id' => $equipmentCategory->id,
        'nombre' => 'Equipo de prueba',
        'estado_uso' => 'disponible',
    ]);
    $complementAsset = Activo::create([
        'codigo' => 'CP-001',
        'categoria_id' => $complementCategory->id,
        'nombre' => 'Monitor de prueba',
        'estado_uso' => 'disponible',
    ]);
    $equipment = Equipo::create([
        'activo_id' => $equipmentAsset->id,
        'identificador' => 'LAB-EQ-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    $complement = Complemento::create([
        'activo_id' => $complementAsset->id,
        'tipo_complemento' => 'Monitor',
        'tipos_equipo_compatibles' => ['cpu', 'mini_pc'],
        'estado_fisico' => 'bueno',
    ]);
    $inactiveComplementAsset = Activo::create([
        'codigo' => 'CP-002',
        'categoria_id' => $inactiveComplementCategory->id,
        'nombre' => 'Monitor archivado',
        'estado_uso' => 'disponible',
    ]);
    $inactiveComplement = Complemento::create([
        'activo_id' => $inactiveComplementAsset->id,
        'tipo_complemento' => 'Monitor',
        'tipos_equipo_compatibles' => ['cpu'],
        'estado_fisico' => 'bueno',
    ]);

    $this->actingAs($admin)
        ->get(route('inventario.activos.create'))
        ->assertOk()
        ->assertSeeText('Complementos de prueba')
        ->assertSeeText('Monitor de prueba')
        ->assertDontSeeText('Monitor archivado')
        ->assertDontSeeText('Complementos archivados');

    $this->get(route('inventario.activos.show', $equipmentAsset))
        ->assertOk()
        ->assertSee('optgroup label="Complementos de prueba"', false)
        ->assertSeeText('Monitor de prueba')
        ->assertDontSeeText('Monitor archivado')
        ->assertDontSeeText('Complementos archivados');

    $this->from(route('inventario.activos.show', $equipmentAsset))
        ->post(route('inventario.activos.complementos.attach', $equipmentAsset), [
            'complemento_id' => $inactiveComplement->id,
        ])
        ->assertRedirect(route('inventario.activos.show', $equipmentAsset))
        ->assertSessionHasErrors([
            'complemento_id' => 'Solo se pueden vincular complementos de categorías activas.',
        ]);

    $this->actingAs($admin)
        ->post(route('inventario.activos.complementos.attach', $equipmentAsset), [
            'complemento_id' => $complement->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('equipo_complemento', [
        'equipo_id' => $equipment->id,
        'complemento_id' => $complement->id,
        'fecha_fin' => null,
    ]);

    $this->get(route('inventario.activos.show', $equipmentAsset))
        ->assertOk()
        ->assertSeeText('Monitor de prueba')
        ->assertDontSee('<option value="'.$complement->id.'"', false);

    $this->actingAs($admin)
        ->delete(route('inventario.activos.complementos.detach', [$equipmentAsset, $complement]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->get(route('inventario.activos.show', $equipmentAsset))
        ->assertOk()
        ->assertSee('<option value="'.$complement->id.'"', false);

    $this->assertDatabaseMissing('equipo_complemento', [
        'equipo_id' => $equipment->id,
        'complemento_id' => $complement->id,
        'fecha_fin' => null,
    ]);
});

test('tablet categories default to tablet and show their compatible peripherals for linking', function () {
    $admin = inventoryAdminUser();
    $tabletCategory = Categoria::create([
        'nombre' => 'Tablets informáticas',
        'grupo' => 'equipo',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
        'prefijo_codigo' => 'TAB',
    ]);
    $peripheralCategory = Categoria::create([
        'nombre' => 'Teclado y mouse para tablets',
        'grupo' => 'complemento',
        'tipo_registro' => 'complemento',
        'estado' => 'activa',
        'prefijo_codigo' => 'TEMO',
    ]);
    $peripheralAsset = Activo::create([
        'codigo' => 'TEMO-0001',
        'categoria_id' => $peripheralCategory->id,
        'nombre' => 'Teclado y mouse tablet',
        'estado_uso' => 'disponible',
    ]);
    $peripheral = Complemento::create([
        'activo_id' => $peripheralAsset->id,
        'tipo_complemento' => 'Teclado y mouse para Tablet',
        'tipos_equipo_compatibles' => ['tablet'],
        'estado_fisico' => 'bueno',
    ]);

    $this->actingAs($admin)
        ->get(route('inventario.activos.create', ['categoria' => $tabletCategory->id]))
        ->assertOk()
        ->assertSee('value="tablet" :disabled="grupo === \'red\'" selected', false)
        ->assertSeeText('Teclado y mouse para tablets')
        ->assertSeeText('Teclado y mouse tablet');

    $this->post(route('inventario.activos.store'), [
        'categoria_id' => $tabletCategory->id,
        'nombre' => 'Tablet de prueba',
        'estado_uso' => 'disponible',
        'tipo_equipo' => 'tablet',
        'modo_uso' => 'individual',
    ])->assertRedirect(route('inventario.index'));

    $tablet = Activo::query()->where('nombre', 'Tablet de prueba')->firstOrFail();

    $this->get(route('inventario.activos.show', $tablet))
        ->assertOk()
        ->assertSee('optgroup label="Teclado y mouse para tablets"', false)
        ->assertSee('<option value="'.$peripheral->id.'"', false);
});

test('administrators can create an equipment with multiple complements selected', function () {
    $admin = inventoryAdminUser();
    $equipmentCategory = Categoria::create([
        'nombre' => 'Equipos multiples',
        'grupo' => 'equipo',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
        'prefijo_codigo' => 'PC',
    ]);
    $complementCategory = Categoria::create(['nombre' => 'Complementos multiples', 'grupo' => 'complemento', 'tipo_registro' => 'complemento', 'estado' => 'activa']);
    $complements = collect(['Monitor', 'Teclado'])->map(function (string $nombre) use ($complementCategory): Complemento {
        $asset = Activo::create([
            'codigo' => 'CP-'.fake()->unique()->numerify('###'),
            'categoria_id' => $complementCategory->id,
            'nombre' => $nombre,
            'estado_uso' => 'disponible',
        ]);

        return Complemento::create([
            'activo_id' => $asset->id,
            'tipo_complemento' => $nombre,
            'tipos_equipo_compatibles' => $nombre === 'Monitor' ? ['cpu', 'mini_pc'] : ['cpu', 'tablet', 'mini_pc'],
            'estado_fisico' => 'bueno',
        ]);
    });

    $this->actingAs($admin)
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $equipmentCategory->id,
            'nombre' => 'Computadora de laboratorio',
            'estado_uso' => 'disponible',
            'identificador' => 'LAB-MULTI-001',
            'tipo_equipo' => 'cpu',
            'modo_uso' => 'individual',
            'complementos' => $complements->pluck('id')->all(),
        ])
        ->assertRedirect(route('inventario.index'))
        ->assertSessionHas('success');

    $equipmentAsset = Activo::where('nombre', 'Computadora de laboratorio')->firstOrFail();
    expect($equipmentAsset->codigo)->toBe('PC-0001');
    expect($equipmentAsset->equipo->complementos)->toHaveCount(2);
});

test('complements can only be linked to compatible equipment types', function () {
    $admin = inventoryAdminUser();
    $equipmentCategory = Categoria::create([
        'nombre' => 'Tablets de prueba',
        'grupo' => 'equipo',
        'tipo_registro' => 'equipo',
        'estado' => 'activa',
        'prefijo_codigo' => 'TAB',
    ]);
    $complementCategory = Categoria::create([
        'nombre' => 'Complementos de prueba',
        'grupo' => 'complemento',
        'tipo_registro' => 'complemento',
        'estado' => 'activa',
        'prefijo_codigo' => 'CMP',
    ]);
    $equipmentAsset = Activo::create([
        'codigo' => 'TAB-0900',
        'categoria_id' => $equipmentCategory->id,
        'nombre' => 'Tablet de aula',
        'estado_uso' => 'disponible',
    ]);
    $monitorAsset = Activo::create([
        'codigo' => 'COM-0001',
        'categoria_id' => $complementCategory->id,
        'nombre' => 'Monitor',
        'estado_uso' => 'disponible',
    ]);
    $supportAsset = Activo::create([
        'codigo' => 'COM-0002',
        'categoria_id' => $complementCategory->id,
        'nombre' => 'Apoyador para tablet',
        'estado_uso' => 'disponible',
    ]);
    $equipment = Equipo::create([
        'activo_id' => $equipmentAsset->id,
        'identificador' => 'TAB-AULA-01',
        'tipo_equipo' => 'tablet',
        'modo_uso' => 'individual',
    ]);
    $monitor = Complemento::create([
        'activo_id' => $monitorAsset->id,
        'tipo_complemento' => 'Monitor',
        'tipos_equipo_compatibles' => ['cpu', 'mini_pc'],
        'estado_fisico' => 'bueno',
    ]);
    $support = Complemento::create([
        'activo_id' => $supportAsset->id,
        'tipo_complemento' => 'Apoyador',
        'tipos_equipo_compatibles' => ['tablet'],
        'estado_fisico' => 'bueno',
    ]);

    $this->actingAs($admin)
        ->from(route('inventario.activos.create'))
        ->post(route('inventario.activos.store'), [
            'categoria_id' => $equipmentCategory->id,
            'nombre' => 'Otra tablet',
            'estado_uso' => 'disponible',
            'tipo_equipo' => 'tablet',
            'modo_uso' => 'individual',
            'complementos' => [$monitor->id],
        ])
        ->assertRedirect(route('inventario.activos.create'))
        ->assertSessionHasErrors('complementos');

    $this->assertDatabaseMissing('activos', ['nombre' => 'Otra tablet']);

    $this->actingAs($admin)
        ->from(route('inventario.activos.show', $equipmentAsset))
        ->post(route('inventario.activos.complementos.attach', $equipmentAsset), [
            'complemento_id' => $monitor->id,
        ])
        ->assertRedirect(route('inventario.activos.show', $equipmentAsset))
        ->assertSessionHasErrors('complemento_id');

    $this->assertDatabaseMissing('equipo_complemento', [
        'equipo_id' => $equipment->id,
        'complemento_id' => $monitor->id,
    ]);

    $this->actingAs($admin)
        ->post(route('inventario.activos.complementos.attach', $equipmentAsset), [
            'complemento_id' => $support->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('equipo_complemento', [
        'equipo_id' => $equipment->id,
        'complemento_id' => $support->id,
        'fecha_fin' => null,
    ]);
});

test('administrators can view a complement and its active linked equipment', function () {
    $admin = inventoryAdminUser();
    $equipmentCategory = Categoria::create(['nombre' => 'Equipos relacionados', 'grupo' => 'equipo', 'tipo_registro' => 'equipo', 'estado' => 'activa']);
    $complementCategory = Categoria::create(['nombre' => 'Complementos relacionados', 'grupo' => 'complemento', 'tipo_registro' => 'complemento', 'estado' => 'activa']);
    $equipmentAsset = Activo::create([
        'codigo' => 'EQ-REL-001',
        'categoria_id' => $equipmentCategory->id,
        'nombre' => 'Computadora vinculada',
        'estado_uso' => 'disponible',
    ]);
    $complementAsset = Activo::create([
        'codigo' => 'CP-REL-001',
        'categoria_id' => $complementCategory->id,
        'nombre' => 'Monitor vinculado',
        'estado_uso' => 'disponible',
    ]);
    $equipment = Equipo::create([
        'activo_id' => $equipmentAsset->id,
        'identificador' => 'LAB-REL-001',
        'tipo_equipo' => 'cpu',
        'modo_uso' => 'individual',
    ]);
    $complement = Complemento::create([
        'activo_id' => $complementAsset->id,
        'tipo_complemento' => 'Monitor',
        'tipos_equipo_compatibles' => ['cpu', 'mini_pc'],
        'estado_fisico' => 'bueno',
    ]);
    $equipment->complementos()->attach($complement->id, ['fecha_inicio' => now()]);

    $this->actingAs($admin)
        ->get(route('inventario.activos.show', $complementAsset))
        ->assertSeeText('Monitor vinculado')
        ->assertSeeText('Equipo relacionado')
        ->assertSeeText('Computadora vinculada')
        ->assertSeeText('EQ-REL-001');
});

test('administrators can search equipment and view the complete equipment detail', function () {
    $admin = inventoryAdminUser();
    $equipment = inventoryEquipment([
        'codigo' => 'INV-100',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad',
        'imagen_referencial' => 'equipos/thinkpad.jpg',
    ]);
    $otherEquipment = inventoryEquipment([
        'codigo' => 'INV-200',
        'marca' => 'Dell',
        'modelo' => 'Latitude',
    ]);
    $accessory = inventoryAccessory($equipment);

    $this->actingAs($admin)
        ->get(route('equipos.index', ['buscar' => 'INV-100']))
        ->assertSeeText('INV-100')
        ->assertDontSeeText('INV-200');

    $this->actingAs($admin)
        ->get(route('equipos.show', $equipment->id))
        ->assertSeeText('ThinkPad')
        ->assertSeeText('Número de serie')
        ->assertSeeText('Accesorios relacionados')
        ->assertSeeText($accessory->nombre)
        ->assertSee('equipos/thinkpad.jpg', false);
});

test('administrators can create update search and delete equipment', function () {
    Storage::fake('public');
    $admin = inventoryAdminUser();
    $photo = UploadedFile::fake()->image('equipo.jpg');

    $this->actingAs($admin)
        ->post(route('equipos.store'), [
            'codigo' => 'INV-300',
            'tipo_equipo' => 'Laptop',
            'marca' => 'HP',
            'modelo' => 'ProBook',
            'numero_serie' => 'SER-300',
            'modo_uso' => 'individual',
            'estado' => 'Disponible',
            'mac' => '00:11:22:33:44:55',
            'imagen_referencial' => $photo,
        ])
        ->assertRedirect(route('equipos.index'))
        ->assertSessionHas('success');

    $equipment = Equipo::where('codigo', 'INV-300')->firstOrFail();
    Storage::disk('public')->assertExists($equipment->imagen_referencial);
    $oldImage = $equipment->imagen_referencial;

    $this->actingAs($admin)
        ->put(route('equipos.update', $equipment->id), [
            'codigo' => 'INV-300',
            'tipo_equipo' => 'Laptop',
            'marca' => 'HP',
            'modelo' => 'ProBook G2',
            'numero_serie' => 'SER-300',
            'modo_uso' => 'individual',
            'estado' => 'En uso',
            'mac' => '00:11:22:33:44:55',
            'imagen_referencial' => UploadedFile::fake()->image('equipo-nuevo.jpg'),
        ])
        ->assertRedirect(route('equipos.show', $equipment->id))
        ->assertSessionHas('success');

    $equipment->refresh();
    $this->assertDatabaseHas('equipos', [
        'id' => $equipment->id,
        'modelo' => 'ProBook G2',
        'estado' => 'En uso',
    ]);
    Storage::disk('public')->assertMissing($oldImage);
    Storage::disk('public')->assertExists($equipment->imagen_referencial);

    $this->actingAs($admin)
        ->get(route('equipos.index', ['buscar' => 'ProBook G2']))
        ->assertSeeText('INV-300');

    $this->actingAs($admin)
        ->delete(route('equipos.destroy', $equipment->id))
        ->assertRedirect(route('equipos.index'));

    $this->assertDatabaseMissing('equipos', ['id' => $equipment->id]);
    Storage::disk('public')->assertMissing($equipment->imagen_referencial);
});

test('equipment with related accessories cannot be deleted', function () {
    $admin = inventoryAdminUser();
    $equipment = inventoryEquipment(['codigo' => 'INV-350']);
    inventoryAccessory($equipment);

    $this->actingAs($admin)
        ->from(route('equipos.index'))
        ->delete(route('equipos.destroy', $equipment->id))
        ->assertRedirect(route('equipos.index'))
        ->assertSessionHasErrors('equipo');

    $this->assertDatabaseHas('equipos', [
        'id' => $equipment->id,
        'codigo' => 'INV-350',
    ]);
});

test('accessory creation rejects an unsupported state', function () {
    $admin = inventoryAdminUser();
    $equipment = inventoryEquipment();

    $this->actingAs($admin)
        ->from(route('accesorios.create'))
        ->post(route('accesorios.store'), [
            'equipo_id' => $equipment->id,
            'nombre' => 'Mouse USB',
            'cantidad' => 1,
            'estado' => 'Estado inválido',
        ])
        ->assertRedirect(route('accesorios.create'))
        ->assertSessionHasErrors('estado');

    $this->assertDatabaseMissing('accesorios', [
        'equipo_id' => $equipment->id,
        'nombre' => 'Mouse USB',
    ]);
});

test('administrators can search create update view and delete accessories', function () {
    Storage::fake('public');
    $admin = inventoryAdminUser();
    $equipment = inventoryEquipment(['codigo' => 'INV-400']);

    $this->actingAs($admin)
        ->post(route('accesorios.store'), [
            'equipo_id' => $equipment->id,
            'nombre' => 'Teclado USB',
            'marca' => 'Logitech',
            'modelo' => 'K120',
            'numero_serie' => 'ACC-400',
            'cantidad' => 2,
            'estado' => 'Disponible',
            'observaciones' => 'Con distribución española.',
            'imagen_referencial' => UploadedFile::fake()->image('teclado.jpg'),
        ])
        ->assertRedirect(route('accesorios.index'))
        ->assertSessionHas('success');

    $accessory = Accesorio::where('numero_serie', 'ACC-400')->firstOrFail();
    Storage::disk('public')->assertExists($accessory->imagen_referencial);
    $oldImage = $accessory->imagen_referencial;

    $this->actingAs($admin)
        ->get(route('accesorios.index', ['buscar' => 'INV-400']))
        ->assertSeeText('Teclado USB');

    $this->actingAs($admin)
        ->get(route('accesorios.show', $accessory->id))
        ->assertSeeText('Teclado USB')
        ->assertSeeText('INV-400')
        ->assertSeeText('Con distribución española.')
        ->assertSee('accesorios/', false);

    $this->actingAs($admin)
        ->put(route('accesorios.update', $accessory->id), [
            'equipo_id' => $equipment->id,
            'nombre' => 'Teclado inalámbrico',
            'marca' => 'Logitech',
            'modelo' => 'K380',
            'numero_serie' => 'ACC-400',
            'cantidad' => 1,
            'estado' => 'En uso',
            'observaciones' => 'Asignado a laboratorio.',
            'imagen_referencial' => UploadedFile::fake()->image('teclado-nuevo.jpg'),
        ])
        ->assertRedirect(route('accesorios.show', $accessory->id))
        ->assertSessionHas('success');

    $accessory->refresh();
    $this->assertDatabaseHas('accesorios', [
        'id' => $accessory->id,
        'nombre' => 'Teclado inalámbrico',
        'modelo' => 'K380',
        'cantidad' => 1,
        'estado' => 'En uso',
    ]);
    Storage::disk('public')->assertMissing($oldImage);
    Storage::disk('public')->assertExists($accessory->imagen_referencial);

    $this->actingAs($admin)
        ->delete(route('accesorios.destroy', $accessory->id))
        ->assertRedirect(route('accesorios.index'));

    $this->assertDatabaseMissing('accesorios', ['id' => $accessory->id]);
    Storage::disk('public')->assertMissing($accessory->imagen_referencial);
});

test('teachers can view their inventory panel but cannot access administrative inventory', function () {
    $teacher = inventoryTeacherUser();

    $this->actingAs($teacher)
        ->get(route('inventario.index'))
        ->assertOk()
        ->assertDontSeeText('Registrar activo')
        ->assertDontSeeText('Nueva categoria');

    $this->actingAs($teacher)
        ->get(route('equipos.index'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('accesorios.index'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('accesorios.create'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('inventario.activos.create'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('inventario.categorias.manage'))
        ->assertForbidden();
});

function inventoryAdminUser(): User
{
    $teacher = Docente::create([
        'nombres' => 'Admin',
        'apellido_paterno' => 'Inventario',
        'apellido_materno' => 'Pruebas',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Administrador',
        'estado' => 'activo',
    ]);

    return User::create([
        'name' => 'Administrador inventario',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'administrador',
        'docente_id' => $teacher->id,
    ]);
}

function inventoryTeacherUser(): User
{
    $teacher = Docente::create([
        'nombres' => 'Docente',
        'apellido_paterno' => 'Inventario',
        'apellido_materno' => 'Pruebas',
        'ci' => fake()->unique()->numerify('########'),
        'cargo' => 'Docente',
        'estado' => 'activo',
    ]);

    return User::create([
        'name' => 'Docente inventario',
        'username' => fake()->unique()->userName(),
        'password' => 'password',
        'tipo_usuario' => 'docente',
        'docente_id' => $teacher->id,
    ]);
}

function inventoryEquipment(array $attributes = []): Equipo
{
    return Equipo::create($attributes + [
        'codigo' => fake()->unique()->bothify('INV-###'),
        'tipo_equipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude',
        'numero_serie' => fake()->unique()->bothify('SER-###'),
        'modo_uso' => 'individual',
        'estado' => 'Disponible',
    ]);
}

function inventoryAccessory(Equipo $equipment): Accesorio
{
    return Accesorio::create([
        'equipo_id' => $equipment->id,
        'nombre' => 'Cargador',
        'cantidad' => 1,
        'estado' => 'Disponible',
    ]);
}

function inventoryQrPng(): string
{
    $image = imagecreatetruecolor(600, 600);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefill($image, 0, 0, $white);

    for ($row = 0; $row < 24; $row++) {
        for ($column = 0; $column < 24; $column++) {
            if (($row * 13 + $column * 7) % 5 < 2) {
                imagefilledrectangle(
                    $image,
                    $column * 25,
                    $row * 25,
                    ($column + 1) * 25 - 1,
                    ($row + 1) * 25 - 1,
                    $black
                );
            }
        }
    }

    ob_start();
    imagepng($image);
    imagedestroy($image);

    return ob_get_clean();
}
