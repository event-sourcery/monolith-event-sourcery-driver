<?php Monolith\Configuration;

class ConfigurationBootstrap implements \Monolith\ComponentLoading\ComponentBootstrap {

    public function bind(\Monolith\DependencyInjection\Container $container): void {
        $dotenv = new Dotenv\Dotenv(__DIR__);
        $dotenv->load();
    }

    public function init(\Monolith\DependencyInjection\Container $container): void {

    }
}