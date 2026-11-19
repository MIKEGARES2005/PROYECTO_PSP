<?php

namespace App\Services;

use RuntimeException;

class SystemShutdownService
{
    private const HEARTBEAT_FILE = 'system-shutdown-agent.heartbeat';

    private const REQUEST_FILE = 'system-shutdown.request';

    private const PROCESSING_FILE = 'system-shutdown.request.processing';

    public function __construct(private readonly ?string $frameworkDirectory = null) {}

    public function isAgentAvailable(): bool
    {
        $lastHeartbeat = @filemtime($this->path(self::HEARTBEAT_FILE));

        if ($lastHeartbeat === false) {
            return false;
        }

        $ageInSeconds = time() - $lastHeartbeat;

        return $ageInSeconds >= 0 && $ageInSeconds <= 5;
    }

    public function requestShutdown(): bool
    {
        $directory = $this->frameworkDirectory ?? storage_path('framework');

        if (! is_dir($directory)) {
            throw new RuntimeException('El directorio local de control del sistema no existe.');
        }

        $requestPath = $this->path(self::REQUEST_FILE);

        if (is_file($requestPath) || is_file($this->path(self::PROCESSING_FILE))) {
            return false;
        }

        $request = @fopen($requestPath, 'x');

        if ($request === false) {
            if (is_file($requestPath) || is_file($this->path(self::PROCESSING_FILE))) {
                return false;
            }

            throw new RuntimeException('No se pudo crear la solicitud local para finalizar el sistema.');
        }

        if (! fclose($request)) {
            throw new RuntimeException('No se pudo guardar la solicitud local para finalizar el sistema.');
        }

        return true;
    }

    private function path(string $filename): string
    {
        return rtrim($this->frameworkDirectory ?? storage_path('framework'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .$filename;
    }
}
