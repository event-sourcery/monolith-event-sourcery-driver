<?php namespace Monolith\DependencyInjection;

final class Singleton {
    /** @var null|callable */
    private $instance;
    /** @var callable */
    private $f;

    public function __construct(callable $f) {
        $this->f = $f;
    }

    public function __invoke(Container $container) {
        if ( ! $this->instance) {
            $this->instance = call_user_func($this->f, $container);
        }
        return $this->instance;
    }
}