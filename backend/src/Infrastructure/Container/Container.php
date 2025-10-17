<?php

declare(strict_types=1);

namespace Infrastructure\Container;

use Psr\Container\ContainerInterface;
use InvalidArgumentException;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $singletons = [];

    public function bind(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function get(string $id)
    {
        if (isset($this->singletons[$id])) {
            if (is_callable($this->singletons[$id])) {
                $this->singletons[$id] = ($this->singletons[$id])($this);
            }
            return $this->singletons[$id];
        }

        if (isset($this->bindings[$id])) {
            return ($this->bindings[$id])($this);
        }

        throw new InvalidArgumentException("No entry found for {$id}.");
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->singletons[$id]);
    }

    public function make(string $abstract, array $parameters = [])
    {
        if (!isset($this->bindings[$abstract])) {
            throw new InvalidArgumentException("No binding found for {$abstract}.");
        }

        return ($this->bindings[$abstract])($this, $parameters);
    }
}