<?php namespace Monolith\DependencyInjection;

use ArrayAccess;
use Monolith\Collections\Map;

final class Container implements ArrayAccess {
    private $bindings;

    public function __construct() {
        $this->bindings = new Map;
    }

    public function bind(string $name, ?callable $resolver = null): void {
        $resolver = $resolver ?: $this->fn($name);
        $this->bindings->add($name, $resolver);
    }

    public function singleton(string $name, ?callable $resolver = null): void {
        $resolver = $resolver ?: $this->fn($name);
        $this->bind($name, new Singleton($resolver, $this));
    }

    public function make(string $name) {
        $f = $this->bindings->get($name);
        if ( ! $f) {
            throw new ContainerBindingNotFound($name);
        }
        return $f($this);
    }

    private function fn(string $className): callable {
        return function() use ($className) {
            return new $className;
        };
    }

    public function offsetExists($offset): bool {
        return array_key_exists($offset, $this->bindings);
    }

    public function offsetGet($offset) {
        return $this->make($offset);
    }

    public function offsetSet($offset, $value): void {
        throw new CannotBindThroughArrayAccess;
    }

    public function offsetUnset($offset): void {
        throw new CannotUnsetBindingThroughArrayAccess;
    }
}