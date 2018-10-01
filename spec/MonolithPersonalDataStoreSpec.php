<?php namespace spec\EventSourcery\Monolith;

use EventSourcery\EventSourcery\PersonalData\CanNotFindPersonalDataByKey;
use EventSourcery\EventSourcery\PersonalData\LibSodiumEncryption;
use EventSourcery\EventSourcery\PersonalData\PersonalCryptographyStore;
use EventSourcery\EventSourcery\PersonalData\PersonalData;
use EventSourcery\EventSourcery\PersonalData\PersonalDataEncryption;
use EventSourcery\EventSourcery\PersonalData\PersonalDataKey;
use EventSourcery\EventSourcery\PersonalData\PersonalKey;
use EventSourcery\Monolith\EventSourceryBootstrap;
use EventSourcery\Monolith\EventStoreDb;
use EventSourcery\Monolith\MonolithPersonalDataStore;
use EventSourcery\Monolith\PersonalCryptographyStoreDb;
use EventSourcery\Monolith\PersonalDataStoreDb;
use Monolith\ComponentBootstrapping\ComponentLoader;
use Monolith\Configuration\ConfigurationBootstrap;
use Monolith\DependencyInjection\Container;
use PhpSpec\ObjectBehavior;

class MonolithPersonalDataStoreSpec extends ObjectBehavior
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

    /** @var Container */
    private $container;

    function let()
    {
        $this->container = $this->bootstrapEventSourcery();

        $this->beConstructedWith(
            $this->container->get(PersonalCryptographyStore::class),
            $this->container->get(PersonalDataEncryption::class),
            $this->container->get(PersonalDataStoreDb::class)
        );

        /** @var PersonalCryptographyStoreDb $db */
        $db = $this->container->get(PersonalDataStoreDb::class);
        $db->write(file_get_contents('migrations/create_personal_data_store.sql'));

        $db = $this->container->get(PersonalCryptographyStoreDb::class);
        $db->write(file_get_contents('migrations/create_personal_cryptography_store.sql'));

        $db = $this->container->get(EventStoreDb::class);
        $db->write(file_get_contents('migrations/create_event_store.sql'));
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(MonolithPersonalDataStore::class);
    }

    function it_can_store_personal_encrypted_data()
    {
        $crypto = (new LibSodiumEncryption)->generateCryptographicDetails();

        $personalKey = PersonalKey::fromString('hats');
        $dataKey = PersonalDataKey::generate();
        $data = PersonalData::fromString("shawn mccool");

        $this->container->get(PersonalCryptographyStore::class)->addPerson($personalKey, $crypto);

        $this->storeData($personalKey, $dataKey, $data);
    }

    function it_can_retrieve_personal_encrypted_data()
    {
        $crypto = (new LibSodiumEncryption)->generateCryptographicDetails();

        $personalKey = PersonalKey::fromString('hats');
        $dataKey = PersonalDataKey::generate();
        $data = PersonalData::fromString("shawn mccool");

        $this->container->get(PersonalCryptographyStore::class)->addPerson($personalKey, $crypto);

        $this->storeData($personalKey, $dataKey, $data);

        $data = $this->retrieveData($personalKey, $dataKey);

        $data->toString()->shouldBe('shawn mccool');
    }

    function it_throws_when_it_cannot_identify_requested_data()
    {
        $personalKey = PersonalKey::fromString('hats');
        $dataKey = PersonalDataKey::generate();

        $this->shouldThrow(CanNotFindPersonalDataByKey::class)->during('retrieveData', [$personalKey, $dataKey]);
    }

    function it_can_remove_all_personal_data_for_a_person()
    {
        $crypto = (new LibSodiumEncryption)->generateCryptographicDetails();

        $personalKey = PersonalKey::fromString('hats');

        $this->container->get(PersonalCryptographyStore::class)->addPerson($personalKey, $crypto);

        $dataKey = PersonalDataKey::generate();
        $data = PersonalData::fromString("shawn mccool");

        $this->storeData($personalKey, $dataKey, $data);

        $dataKeyTwo = PersonalDataKey::generate();
        $data = PersonalData::fromString("jun 27th");

        $this->storeData($personalKey, $dataKeyTwo, $data);

        $this->removeDataFor($personalKey);

        $this->shouldThrow(CanNotFindPersonalDataByKey::class)->during('retrieveData', [$personalKey, $dataKey]);
        $this->shouldThrow(CanNotFindPersonalDataByKey::class)->during('retrieveData', [$personalKey, $dataKeyTwo]);
    }

    function it_will_automatically_add_personal_cryptography_if_needed_when_storing_data()
    {
        $personalKey = PersonalKey::fromString('hats');
        $dataKey = PersonalDataKey::generate();
        $data = PersonalData::fromString("shawn mccool");

        $this->storeData($personalKey, $dataKey, $data);

        $cryptoStore = $this->container->get(PersonalCryptographyStore::class);

        $cryptoStore->getCryptographyFor($personalKey);
    }
}
