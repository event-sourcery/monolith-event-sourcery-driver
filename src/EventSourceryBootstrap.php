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
use EventSourcery\EventSourcery\Serialization\ValueSerializer;
use EventSourcery\EventSourcery\StreamProcessing\ProjectionManager;
use EventSourcery\EventSourcery\StreamProcessing\Projections;
use Monolith\ComponentBootstrapping\ComponentBootstrap;
use Monolith\DependencyInjection\Container;

class EventSourceryBootstrap implements ComponentBootstrap
{
    public function bind(Container $container): void
    {
        // CQRS
        $container->bind(CommandBus::class, function (Container $c) {
            return new ReflectionResolutionCommandBus($c);
        });

        $container->singleton(ProjectionManager::class, function () {
            return new ProjectionManager(Projections::make([]));
        });

        // Domain Event Serialization
        $container->singleton(DomainEventClassMap::class);

        $container->bind(DomainEventSerializer::class, function (Container $c) {
            return new ReflectionBasedDomainEventSerializer(
                $c->get(DomainEventClassMap::class),
                $c->get(ValueSerializer::class),
                $c->get(PersonalDataStore::class)
            );
        });

        $container->singleton(ValueSerializer::class, function (Container $c) {
            return new ValueSerializer($c->get(PersonalDataStore::class));
        });

        // Implementation Binding
        $container->bind(PersonalDataEncryption::class, LibSodiumEncryption::class);
        $container->singleton(EventDispatcher::class, ImmediateEventDispatcher::class);

        // Database Connection Configuration
        $container->bind(EventStoreDb::class, function (Container $c) {
            return new EventStoreDb(
                getenv('EVENT_STORE_DSN'),
                getenv('EVENT_STORE_USERNAME'),
                getenv('EVENT_STORE_PASSWORD')
            );
        });

        $container->bind(PersonalDataStoreDb::class, function (Container $c) {
            return new PersonalDataStoreDb(
                getenv('PERSONAL_DATA_STORE_DSN'),
                getenv('PERSONAL_DATA_STORE_USERNAME'),
                getenv('PERSONAL_DATA_STORE_PASSWORD')
            );
        });

        $container->bind(PersonalCryptographyStoreDb::class, function (Container $c) {
            return new PersonalCryptographyStoreDb(
                getenv('PERSONAL_CRYPTOGRAPHY_STORE_DSN'),
                getenv('PERSONAL_CRYPTOGRAPHY_STORE_USERNAME'),
                getenv('PERSONAL_CRYPTOGRAPHY_STORE_PASSWORD')
            );
        });

        // Data Store Configuration
        $container->singleton(EventStore::class, function () use ($container) {
            return new MonolithEventStore(
                $container->get(DomainEventSerializer::class),
                $container->get(EventDispatcher::class),
                $container->get(EventStoreDb::class)
            );
        });

        $container->bind(PersonalDataStore::class, function (Container $c) {
            return new MonolithPersonalDataStore(
                $c->get(PersonalCryptographyStore::class),
                $c->get(PersonalDataEncryption::class),
                $c->get(PersonalDataStoreDb::class)
            );
        });

        $container->bind(PersonalCryptographyStore::class, function (Container $c) {
            return new MonolithPersonalCryptographyStore(
                $c->get(PersonalDataEncryption::class),
                $c->get(PersonalCryptographyStoreDb::class)
            );
        });
    }

    public function init(Container $container): void
    {
        $dispatcher = $container->get(EventDispatcher::class);

        $dispatcher->addListener($container->get(ProjectionManager::class));
    }
}