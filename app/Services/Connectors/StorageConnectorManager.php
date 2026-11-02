<?php

namespace App\Services\Connectors;

use App\Contracts\StorageConnectorInterface;
use App\Models\StorageSource;
use InvalidArgumentException;

class StorageConnectorManager
{
    /**
     * @var array<string, StorageConnectorInterface>
     */
    protected array $connectors = [];

    public function __construct(LocalConnector $localConnector)
    {
        $this->register('local', $localConnector);
        $this->register('nas_smb', $localConnector); // Local filesystem access via mounted share
    }

    public function register(string $driver, StorageConnectorInterface $connector): void
    {
        $this->connectors[$driver] = $connector;
    }

    public function forSource(StorageSource $source): StorageConnectorInterface
    {
        return $this->driver($source->driver);
    }

    public function driver(string $driver = 'local'): StorageConnectorInterface
    {
        if (! isset($this->connectors[$driver])) {
            throw new InvalidArgumentException("Unsupported storage driver [{$driver}].");
        }

        return $this->connectors[$driver];
    }
}
