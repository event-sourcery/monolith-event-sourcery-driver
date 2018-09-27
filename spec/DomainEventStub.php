<?php namespace spec\EventSourcery\Monolith;

use EventSourcery\EventSourcery\EventSourcing\DomainEvent;

final class DomainEventStub implements DomainEvent
{
    /** @var IdStub */
    public $id;

    public function __construct(IdStub $id)
    {
        $this->id = $id;
    }
}