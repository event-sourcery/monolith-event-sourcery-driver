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
use Monolith\RelationalDatabase\CouldNotConnectWithPdo;

class EventSourceryBootstrap implements ComponentBootstrap
{
    public function bind(Container $container): void
    {
        // CQRS
        $container->bind(CommandBus::class, function (callable $r) use ($container) {
            return new ReflectionResolutionCommandBus($container);
        });

        $container->singleton(ProjectionManager::class, function () {
            return new ProjectionManager(Projections::make([]));
        });

        // Domain Event Serialization
        $container->singleton(DomainEventClassMap::class);

        $container->bind(DomainEventSerializer::class, function (callable $r) {
            return new ReflectionBasedDomainEventSerializer(
                $r(DomainEventClassMap::class),
                $r(ValueSerializer::class),
                $r(PersonalDataStore::class)
            );
        });

        // Implementation Binding
        $container->bind(PersonalDataEncryption::class, LibSodiumEncryption::class);
        $container->singleton(EventDispatcher::class, ImmediateEventDispatcher::class);

        // Database Connection Configuration
        $container->bind(EventStoreDb::class, function (callable $r) {
            try {
                return new EventStoreDb(
                    getenv('EVENT_STORE_DSN'),
                    getenv('EVENT_STORE_USERNAME'),
                    getenv('EVENT_STORE_PASSWORD')
                );
            } catch (CouldNotConnectWithPdo $e) {
                throw CouldNotConnectToDatabase::fromPdoException($e);
            }
        });

        $container->bind(PersonalDataStoreDb::class, function (callable $r) {
            try {
                return new PersonalDataStoreDb(
                    getenv('PERSONAL_DATA_STORE_DSN'),
                    getenv('PERSONAL_DATA_STORE_USERNAME'),
                    getenv('PERSONAL_DATA_STORE_PASSWORD')
                );
            } catch (CouldNotConnectWithPdo $e) {
                throw CouldNotConnectToDatabase::fromPdoException($e);
            }
        });

        $container->bind(PersonalCryptographyStoreDb::class, function (callable $r) {
            try {
                return new PersonalCryptographyStoreDb(
                    getenv('PERSONAL_CRYPTOGRAPHY_STORE_DSN'),
                    getenv('PERSONAL_CRYPTOGRAPHY_STORE_USERNAME'),
                    getenv('PERSONAL_CRYPTOGRAPHY_STORE_PASSWORD')
                );
            } catch (CouldNotConnectWithPdo $e) {
                throw CouldNotConnectToDatabase::fromPdoException($e);
            }
        });

        // Data Store Configuration
        $container->singleton(EventStore::class, MonolithEventStore::class);
        $container->bind(MonolithEventStore::class, function (callable $r) {
            return new MonolithEventStore(
                $r(DomainEventSerializer::class),
                $r(EventDispatcher::class),
                $r(EventStoreDb::class)
            );
        });

        $container->bind(MonolithPersonalDataStore::class, function (callable $r) {
            return new MonolithPersonalDataStore(
                $r(PersonalCryptographyStore::class),
                $r(PersonalDataEncryption::class),
                $r(PersonalDataStoreDb::class)
            );
        });
        $container->singleton(PersonalDataStore::class, MonolithPersonalDataStore::class);

        $container->bind(MonolithPersonalCryptographyStore::class, function (callable $r) {
            return new MonolithPersonalCryptographyStore(
                $r(PersonalDataEncryption::class),
                $r(PersonalCryptographyStoreDb::class)
            );
        });
        $container->singleton(PersonalCryptographyStore::class, MonolithPersonalCryptographyStore::class);
    }

    public function init(Container $container): void
    {
        $dispatcher = $container->get(EventDispatcher::class);

        $dispatcher->addListener($container->get(ProjectionManager::class));
    }
}