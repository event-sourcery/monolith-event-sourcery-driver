<?php namespace EventSourcery\Monolith;

use EventSourcery\EventSourcery\EventDispatch\EventDispatcher;
use EventSourcery\EventSourcery\EventSourcing\DomainEvent;
use EventSourcery\EventSourcery\EventSourcing\DomainEvents;
use EventSourcery\EventSourcery\EventSourcing\EventStore;
use EventSourcery\EventSourcery\EventSourcing\StreamEvent;
use EventSourcery\EventSourcery\EventSourcing\StreamEvents;
use EventSourcery\EventSourcery\EventSourcing\StreamId;
use EventSourcery\EventSourcery\EventSourcing\StreamVersion;
use EventSourcery\EventSourcery\Serialization\DomainEventSerializer;
use Monolith\RelationalDatabase\Db;
use Monolith\Collections\Collection;

/**
 * The MonolithEventStore is a Monolith-specific implementation of
 * an EventStore. It uses the default relational driver configured
 * in the Monolith application.
 */
class MonolithEventStore implements EventStore
{

    /** @var DomainEventSerializer */
    private $serializer;

    /** @var EventDispatcher */
    private $eventDispatcher;

    /** @var Db */
    private $db;

    private $table = 'event_store';

    public function __construct(DomainEventSerializer $serializer, EventDispatcher $eventDispatcher, Db $db)
    {
        $this->serializer = $serializer;
        $this->eventDispatcher = $eventDispatcher;
        $this->db = $db;
    }

    /**
     * persist events in an event stream
     *
     * @param StreamEvents $events
     */
    public function storeStream(StreamEvents $events): void
    {
        // store events
        $events->each(function (StreamEvent $stream) {
            $this->store($stream->id(), $stream->event(), $stream->version());
        });

        // event dispatch
        $this->eventDispatcher->dispatch($events->toDomainEvents());
    }

    /**
     * persist a single event
     *
     * @param DomainEvent $event
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    public function storeEvent(DomainEvent $event): void
    {
        $this->store(
            StreamId::fromString(0),
            $event,
            StreamVersion::zero(),
            ''
        );

        $this->eventDispatcher->dispatch(DomainEvents::make([$event]));
    }

    /**
     * retrieve an event stream based on its id
     *
     * @param StreamId $id
     * @return StreamEvents
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    public function getStream(StreamId $id): StreamEvents
    {
        return StreamEvents::make(
            $this->getStreamRawEventData($id)->map(function ($e) {

                $e->event_data = json_decode($e->event_data, true);

                return new StreamEvent(
                    StreamId::fromString($e->stream_id),
                    StreamVersion::fromInt($e->stream_version),
                    $this->serializer->deserialize($e->event_data)
                );

            })->toArray()
        );
    }

    /**
     * a pagination function for processing events by pages
     * 0 is the first event in the store
     *
     * @param int $take
     * @param int $skip
     * @return DomainEvents
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    public function getEvents($take = 0, $skip = 0): DomainEvents
    {
        $eventData = $this->getRawEvents($take, $skip);

        $events = $eventData->map(function ($e) {
            $e->event_data = json_decode($e->event_data, true);
            return $this->serializer->deserialize($e);
        })->toArray();

        return DomainEvents::make($events);
    }

    /**
     * retrieve raw stream data from the database
     *
     * @param StreamId $id
     * @return Collection
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    private function getStreamRawEventData(StreamId $id): Collection
    {
        return new Collection((array) $this->db->read(
            "select * from {$this->table} where stream_id = :stream_id order by stream_version asc",
            [
                'stream_id' => $id->toString(),
            ]
        ));
    }

    /**
     * get raw event data for pagination
     *
     * @param int $take
     * @param int $skip
     * @return mixed
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    private function getRawEvents($take = 0, $skip = 0): Collection
    {
        return new Collection($this->db->read(
            "SELECT * FROM {$this->table} WHERE order by id asc limit :take offset :skip",
            [
                'take'  => $take,
                'skip'  => $skip,
            ]
        ));
    }

    /**
     * execute the relational persistence
     *
     * @param StreamId $id
     * @param DomainEvent $event
     * @param StreamVersion $version
     * @param string $metadata
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    private function store(StreamId $id, DomainEvent $event, StreamVersion $version, $metadata = ''): void
    {
        $this->db->write(
            "insert into {$this->table} (stream_id, stream_version, event_name, event_data, raised_at, meta_data) values(:stream_id, :stream_version, :event_name, :event_data, :raised_at, :meta_data)",
            [
                'stream_id'      => $id->toString(),
                'stream_version' => $version->toInt(),
                'event_name'     => $this->serializer->eventNameForClass(get_class($event)),
                'event_data'     => $this->serializer->serialize($event),
                'raised_at'      => date('Y-m-d H:i:s'),
                'meta_data'      => $metadata ?: '{}',
            ]
        );
    }
}
