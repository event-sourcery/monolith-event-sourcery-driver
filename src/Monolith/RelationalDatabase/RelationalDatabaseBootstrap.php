<?php use Monolith\RelationalDatabase\Query;

Monolith\RelationalDatabase;

class RelationalDatabaseBootstrap implements \Monolith\ComponentLoading\ComponentBootstrap {

    public function bind(\Monolith\DependencyInjection\Container $container): void {
        $container->bind(Query::class, function() {
            return new Query(get_env('RELATIONAL_DATABASE_DSN'));
        });
    }

    public function init(\Monolith\DependencyInjection\Container $container): void {

    }
}