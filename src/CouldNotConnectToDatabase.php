<?php namespace EventSourcery\Monolith;

use Monolith\RelationalDatabase\CouldNotConnectWithPdo;

final class CouldNotConnectToDatabase extends MonolithEventSourceryDriverException
{
    public static function fromPdoException(CouldNotConnectWithPdo $e)
    {
        $message =<<<EOF
Monolith Event Sourcery driver could not connect to the database and received the PDO error, '{$e->getMessage()}'.

Have you configured your .env environment variables for the event store, personal data store, and personal cryptography stores?
EOF;
        return new static($message);
    }
}