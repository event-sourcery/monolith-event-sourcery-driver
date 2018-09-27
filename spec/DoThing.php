<?php namespace spec\EventSourcery\Monolith;

use EventSourcery\EventSourcery\Commands\Command;
use EventSourcery\EventSourcery\EventSourcing\EventStore;

final class DoThing implements Command
{
    /** @var int */
    public $number;

    public function __construct(int $number)
    {
        $this->number = $number;
    }

    public function execute(EventStore $events) {

    }
}