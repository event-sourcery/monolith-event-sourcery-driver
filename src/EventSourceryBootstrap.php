<?php namespace EventSourcery\Monolith;

use Monolith\Configuration\Config;
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
        // Database Connection Configuration relies on env configuration
        $container->bind(EventStoreDb::class, function (callable $r) {
            try {
                $config = $r(Config::class);
                return new EventStoreDb(
                    $config->get('EVENT_STORE_DSN'),
                    $config->get('EVENT_STORE_USERNAME'),
                    $config->get('EVENT_STORE_PASSWORD')
                );
            } catch (CouldNotConnectWithPdo $e) {
                throw CouldNotConnectToDatabase::fromPdoException($e);
            }
        });

        $container->bind(PersonalDataStoreDb::class, function (callable $r) {
            try {
                $config = $r(Config::class);
                return new PersonalDataStoreDb(
                    $config->get('PERSONAL_DATA_STORE_DSN'),
                    $config->get('PERSONAL_DATA_STORE_USERNAME'),
                    $config->get('PERSONAL_DATA_STORE_PASSWORD')
                );
            } catch (CouldNotConnectWithPdo $e) {
                throw CouldNotConnectToDatabase::fromPdoException($e);
            }
        });

        $container->bind(PersonalCryptographyStoreDb::class, function (callable $r) {
            try {
                $config = $r(Config::class);
                return new PersonalCryptographyStoreDb(
                    $config->get('PERSONAL_CRYPTOGRAPHY_STORE_DSN'),
                    $config->get('PERSONAL_CRYPTOGRAPHY_STORE_USERNAME'),
                    $config->get('PERSONAL_CRYPTOGRAPHY_STORE_PASSWORD')
                );
            } catch (CouldNotConnectWithPdo $e) {
                throw CouldNotConnectToDatabase::fromPdoException($e);
            }
        });

        # event dispatcher configuration
        $dispatcher = $container->get(EventDispatcher::class);

        $dispatcher->addListener($container->get(ProjectionManager::class));
    }
}