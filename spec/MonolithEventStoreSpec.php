<?php namespace spec\EventSourcery\Monolith;

use EventSourcery\EventSourcery\EventDispatch\EventDispatcher;
use EventSourcery\EventSourcery\Serialization\DomainEventSerializer;
use EventSourcery\Monolith\EventSourceryBootstrap;
use EventSourcery\Monolith\EventStoreDb;
use EventSourcery\Monolith\MonolithEventStore;
use Monolith\ComponentBootstrapping\ComponentLoader;
use Monolith\Configuration\ConfigurationBootstrap;
use Monolith\DependencyInjection\Container;
use Monolith\RelationalDatabase\Db;
use PhpSpec\ObjectBehavior;

class MonolithEventStoreSpec extends ObjectBehavior
{
    function bootstrapEventSourcery(): Container
    {
        $container = new Container;
        $loader = new ComponentLoader($container);
        $loader->register(
            new ConfigurationBootstrap('spec/'),
            new EventSourceryBootstrap
        );
        $loader->load();
        return $container;
    }

    function let()
    {
        $container = $this->bootstrapEventSourcery();

        $this->beConstructedWith(
            $container->get(DomainEventSerializer::class),
            $container->get(EventDispatcher::class),
            $container->get(EventStoreDb::class)
        );
    }

    function letGo()
    {
        // clean up
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(MonolithEventStore::class);
    }
}
