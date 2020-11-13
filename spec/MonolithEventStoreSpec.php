<?php namespace spec\EventSourcery\Monolith;

use EventSourcery\Monolith\CanNotRetrieveDomainEvent;
use EventSourcery\EventSourcery\EventDispatch\EventDispatcher;
use EventSourcery\EventSourcery\EventSourcing\DomainEventClassMap;
use EventSourcery\EventSourcery\EventSourcing\DomainEvents;
use EventSourcery\EventSourcery\EventSourcing\StreamEvent;
use EventSourcery\EventSourcery\EventSourcing\StreamEvents;
use EventSourcery\EventSourcery\EventSourcing\StreamVersion;
use EventSourcery\EventSourcery\Serialization\DomainEventSerializer;
use EventSourcery\Monolith\EventSourceryBootstrap;
use EventSourcery\Monolith\EventStoreDb;
use EventSourcery\Monolith\MonolithEventStore;
use EventSourcery\Monolith\PersonalCryptographyStoreDb;
use EventSourcery\Monolith\PersonalDataStoreDb;
use Monolith\ComponentBootstrapping\ComponentLoader;
use Monolith\Configuration\ConfigurationBootstrap;
use Monolith\DependencyInjection\Container;
use PhpSpec\ObjectBehavior;

class MonolithEventStoreSpec extends ObjectBehavior
{
    use MonolithEventStoreTestValues;
    
    function bootstrapEventSourcery(): Container
    {
        $container = new Container;
        $loader = new ComponentLoader($container);
        $loader->register(
            new ConfigurationBootstrap('spec/.env'),
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

        $this->shouldHaveType(MonolithEventStore::class);

        // event name / class bindings
        $classMap = $container->get(DomainEventClassMap::class);
        $classMap->add('spec/DomainEventStub', DomainEventStub::class);

        $this->migrate($container);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(MonolithEventStore::class);
    }

    function it_can_store_events()
    {
        /** @var StreamEvents $stream */

        $id = IdStub::generate();

        // store an event stub
        $this->storeEvent(new DomainEventStub($id));

        // query
        $events = $this->getEvents(1, 0);

        // get back domain events collection
        $events->shouldHaveType(DomainEvents::class);

        // the event should be of the correct type
        $event = $events->first();
        $event->shouldHaveType(DomainEventStub::class);

        // the id should be the same
        $event->id->equals($id);
    }
    
    function it_can_retrieve_events_by_id()
    {
        /** @var StreamEvents $stream */

        $id = IdStub::generate();

        // store an event stub
        $this->storeEvent(new DomainEventStub($id));

        // query
        $event = $this->getEvent(1);

        // get back domain events collection
        $event->shouldHaveType(DomainEventStub::class);
        
        // the id should be the same
        $event->id->equals($id);
    }

    function it_throws_when_it_cant_find_an_event_by_id()
    {
        /** @var StreamEvents $stream */

        $id = IdStub::generate();

        // store an event stub
        $this->storeEvent(new DomainEventStub($id));

        // query
        $this->shouldThrow(CanNotRetrieveDomainEvent::class)->during('getEvent', [1190283719273]);
    }
    
    function it_can_store_event_streams()
    {
        $id = IdStub::generate();

        $this->storeStream(StreamEvents::make([
            new StreamEvent($id, StreamVersion::zero(), new DomainEventStub($id)),
        ]));

        /** @var StreamEvents $stream */
        $stream = $this->getStream($id);

        $stream->shouldHaveType(StreamEvents::class);
        $stream->first()->shouldHaveType(StreamEvent::class);

        $event = $stream->first()->event();
        $event->shouldHaveType(DomainEventStub::class);

        $event->id->equals($id)->shouldBe(true);
    }
}
