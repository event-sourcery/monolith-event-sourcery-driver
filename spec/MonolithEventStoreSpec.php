<?php namespace spec\EventSourcery\Monolith;

use EventSourcery\EventSourcery\EventDispatch\EventDispatcher;
use EventSourcery\EventSourcery\EventSourcing\DomainEventClassMap;
use EventSourcery\EventSourcery\EventSourcing\StreamEvents;
use EventSourcery\EventSourcery\EventSourcing\StreamId;
use EventSourcery\EventSourcery\Serialization\DomainEventSerializer;
use EventSourcery\Monolith\EventSourceryBootstrap;
use EventSourcery\Monolith\EventStoreDb;
use EventSourcery\Monolith\MonolithEventStore;
use Monolith\ComponentBootstrapping\ComponentLoader;
use Monolith\Configuration\ConfigurationBootstrap;
use Monolith\DependencyInjection\Container;
use PhpSpec\ObjectBehavior;
use Ramsey\Uuid\Uuid;

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

        // build integrated event store
        $this->beConstructedWith(
            $container->get(DomainEventSerializer::class),
            $container->get(EventDispatcher::class),
            $container->get(EventStoreDb::class)
        );

        // event name / class bindings
        $classMap = $container->get(DomainEventClassMap::class);
        $classMap->add('spec/DomainEventStub', DomainEventStub::class);

        // clear event store
        /** @var EventStoreDb $db */
        $db = $container->get(EventStoreDb::class);
        $db->write(file_get_contents('migrations/2018-09-26_15-29-00_create_event_store.sql'));
    }

    function letGo()
    {
        // clean up
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(MonolithEventStore::class);
    }

    function it_can_store_events() {

        $id = IdStub::generate();
        $this->storeEvent(new DomainEventStub($id));

        /** @var StreamEvents $stream */
        $stream = $this->getStream(StreamId::fromString('0'));

        $stream->shouldHaveType(StreamEvents::class);
        $event = $stream->first()->event();

        $event->shouldHaveType(DomainEventStub::class);
        $event->id->equals($id);
    }
}
