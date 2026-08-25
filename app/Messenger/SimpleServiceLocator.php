<?php

namespace App\Messenger;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

class SimpleServiceLocator implements ContainerInterface
{
    /**
     * @param  array<string, mixed>  $services
     */
    public function __construct(
        private array $services,
    ) {}

    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new class("Service [{$id}] was not found.") extends RuntimeException implements NotFoundExceptionInterface {};
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
