<?php namespace EventSourcery\Monolith;

use EventSourcery\EventSourcery\Commands\CommandBus;
use EventSourcery\EventSourcery\Commands\ReflectionResolutionCommandBus;
use EventSourcery\EventSourcery\EventDispatch\EventDispatcher;
use EventSourcery\EventSourcery\EventDispatch\ImmediateEventDispatcher;
use EventSourcery\EventSourcery\EventSourcing\DomainEventClassMap;
use EventSourcery\EventSourcery\EventSourcing\EventStore;
use EventSourcery\EventSourcery\PersonalData\LibSodiumEncryption;
use EventSourcery\EventSourcery\PersonalData\PersonalCryptographyStore;
use EventSourcery\EventSourcery\PersonalData\PersonalDataEncryption;
use EventSourcery\EventSourcery\PersonalData\PersonalDataStore;
use EventSourcery\EventSourcery\Serialization\DomainEventSerializer;
use EventSourcery\EventSourcery\Serialization\ReflectionBasedDomainEventSerializer;
use EventSourcery\EventSourcery\StreamProcessing\ProjectionManager;
use EventSourcery\EventSourcery\StreamProcessing\Projections;
use Monolith\ComponentBootstrapping\ComponentBootstrap;
use Monolith\DependencyInjection\Container;

class EventSourceryBootstrap implements ComponentBootstrap {

    public function bind(Container $container): void {

        $container->bind(DomainEventSerializer::class, ReflectionBasedDomainEventSerializer::class);

        $container->singleton(DomainEventClassMap::class);

        $container->singleton(EventDispatcher::class, function() use ($container) {
            return new ImmediateEventDispatcher();
        });

        $container->singleton(EventStore::class, function() use ($container) {
            return new MonolithEventStore($container->make(DomainEventSerializer::class));
        });

        $container->singleton(ProjectionManager::class, function () {
            return new ProjectionManager(Projections::make([]));
        });

        $container->bind(CommandBus::class, ReflectionResolutionCommandBus::class);
        $container->bind(PersonalCryptographyStore::class, MonolithPersonalCryptographyStore::class);
        $container->bind(PersonalDataStore::class, MonolithPersonalDataStore::class);
        $container->bind(PersonalDataEncryption::class, LibSodiumEncryption::class);
    }

    public function init(Container $container): void {
        $dispatcher = $container->make(EventDispatcher::class);

        $dispatcher->addListener($container->make(ProjectionManager::class));
    }
}
