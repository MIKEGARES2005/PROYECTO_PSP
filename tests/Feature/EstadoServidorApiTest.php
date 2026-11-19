<?php

test('the api reports the server as available', function () {
    $response = $this->getJson('/api/estado-servidor');

    $response->assertOk()
        ->assertJson([
            'estado' => 'ok',
            'mensaje' => 'Servidor Laravel operativo',
            'servidor' => config('app.name'),
        ])
        ->assertJsonStructure([
            'estado',
            'mensaje',
            'servidor',
            'version_laravel',
            'hora',
        ]);
});

test('the status endpoint is reachable without authentication', function () {
    $this->get('/api/estado-servidor')
        ->assertOk()
        ->assertHeader('content-type', 'application/json');
});
