<?php namespace spec\EventSourcery\Monolith;

use EventSourcery\Monolith\EventStoreDb;
use Monolith\DependencyInjection\Container;
use EventSourcery\Monolith\PersonalDataStoreDb;
use EventSourcery\Monolith\PersonalCryptographyStoreDb;

trait MonolithEventStoreTestValues
{
    public function migrate(Container $container)
    {
        $db = $container->get(PersonalDataStoreDb::class);
        $db->write(file_get_contents('migrations/2019-02-04_03-00-00_create_personal_data_store.sql'));

        $db = $container->get(PersonalCryptographyStoreDb::class);
        $db->write(file_get_contents('migrations/2019-02-04_02-00-00_create_personal_cryptography_store.sql'));

        $db = $container->get(EventStoreDb::class);
        $db->write(file_get_contents('migrations/2019-02-04_01-00-00_create_event_store.sql'));
    }
}