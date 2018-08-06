<?php namespace Monolith\ComponentLoading;

use Monolith\Collections\Collection;
use Monolith\DependencyInjection\Container;

class ComponentLoader {

    /** @var Container */
    private $container;
    /** @var Collection */
    private $registered;

    public function __construct(Container $container) {
        $this->container = $container;
        $this->registered = new Collection;
    }

    public function register(ComponentBootstrap ...$bootstrap) {
        $this->registered = $this->registered->merge(new Collection($bootstrap));
    }

    public function load(): Container {
        // First, run the component bindings
        /** @var ComponentBootstrap $bootstrap */
        foreach ($this->registered as $bootstrap) {
            $bootstrap->bind($this->container);
        }

        // Then, when all bindings are loaded, initialise the component
        /** @var ComponentBootstrap $bootstrap */
        foreach ($this->registered as $bootstrap) {
            $bootstrap->init($this->container);
        }

        return $this->container;
    }
}