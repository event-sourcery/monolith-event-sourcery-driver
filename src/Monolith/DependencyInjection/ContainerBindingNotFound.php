<?php namespace Monolith\DependencyInjection;

class ContainerBindingNotFound extends \Exception {

    public function __construct(string $name) {
        parent::__construct("Container binding [{$name}] not found.");
    }
}