<?php namespace spec\EventSourcery\Monolith;

use EventSourcery\EventSourcery\PersonalData\PersonalDataEncryption;
use EventSourcery\EventSourcery\PersonalData\PersonalKey;
use EventSourcery\Monolith\CanNotAddCryptoPersonAlreadyHasCrypto;
use EventSourcery\Monolith\CanNotRetrieveCryptoForARemovedPerson;
use EventSourcery\Monolith\CanNotRetrieveCryptographyFromARemovedPerson;
use EventSourcery\Monolith\EventSourceryBootstrap;
use EventSourcery\Monolith\EventStoreDb;
use EventSourcery\Monolith\MonolithPersonalCryptographyStore;
use EventSourcery\Monolith\PersonalCryptographyStoreDb;
use EventSourcery\Monolith\PersonalDataStoreDb;
use Monolith\ComponentBootstrapping\ComponentLoader;
use Monolith\Configuration\ConfigurationBootstrap;
use Monolith\DependencyInjection\Container;
use PhpSpec\ObjectBehavior;
use Ramsey\Uuid\Uuid;

class MonolithPersonalCryptographyStoreSpec extends ObjectBehavior
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

    /** @var PersonalDataEncryption */
    private $encryption;

    function let()
    {
        $container = $this->bootstrapEventSourcery();
        $this->encryption = $container->get(PersonalDataEncryption::class);

        // build integrated event store
        $this->beConstructedWith(
            $this->encryption,
            $container->get(PersonalCryptographyStoreDb::class)
        );

        $this->shouldHaveType(MonolithPersonalCryptographyStore::class);

        // migrations
        $db = $container->get(PersonalDataStoreDb::class);
        $db->write(file_get_contents('migrations/create_personal_data_store.sql'));

        $db = $container->get(PersonalCryptographyStoreDb::class);
        $db->write(file_get_contents('migrations/create_personal_cryptography_store.sql'));

        $db = $container->get(EventStoreDb::class);
        $db->write(file_get_contents('migrations/create_event_store.sql'));
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(MonolithPersonalCryptographyStore::class);
    }

    function it_can_add_a_persons_cryptography()
    {
        $personalKey = PersonalKey::fromString(Uuid::uuid4());
        $newCrypto = $this->encryption->generateCryptographicDetails();

        $this->addPerson($personalKey, $newCrypto);
        $crypto = $this->getCryptographyFor($personalKey);

        $crypto->encryption()->shouldBe($newCrypto->encryption());
        $crypto->key('secretKey')->shouldBe($newCrypto->key('secretKey'));
    }

    function it_will_not_allow_a_person_to_be_added_twice()
    {
        $personalKey = PersonalKey::fromString(Uuid::uuid4());
        $newCrypto = $this->encryption->generateCryptographicDetails();

        $this->addPerson($personalKey, $newCrypto);
        $this->shouldThrow(CanNotAddCryptoPersonAlreadyHasCrypto::class)->during('addPerson', [$personalKey, $newCrypto]);
    }

    function it_can_tell_if_it_contains_the_crypto_for_a_person()
    {
        $personalKey = PersonalKey::fromString(Uuid::uuid4());

        $this->hasPerson($personalKey)->shouldBe(false);

        $newCrypto = $this->encryption->generateCryptographicDetails();
        $this->addPerson($personalKey, $newCrypto);

        $this->hasPerson($personalKey)->shouldBe(true);

        $this->removePerson($personalKey);

        // still true
        $this->hasPerson($personalKey)->shouldBe(true);
    }

    function it_can_remove_cryptography_for_a_person()
    {
        $personalKey = PersonalKey::fromString(Uuid::uuid4());
        $newCrypto = $this->encryption->generateCryptographicDetails();

        $this->addPerson($personalKey, $newCrypto);
        $this->removePerson($personalKey);

        $this->shouldThrow(CanNotRetrieveCryptoForARemovedPerson::class)->during('getCryptographyFor', [$personalKey]);
    }

    function it_can_provide_its_configured_encryption_algorithm()
    {
        $this->getEncryption()->shouldHaveType(PersonalDataEncryption::class);
    }
}
