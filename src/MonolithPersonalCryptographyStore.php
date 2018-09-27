<?php namespace EventSourcery\Monolith;

use DB;
use EventSourcery\EventSourcery\PersonalData\CanNotFindCryptographyForPerson;
use EventSourcery\EventSourcery\PersonalData\CouldNotFindCryptographyForPerson;
use EventSourcery\EventSourcery\PersonalData\CryptographicDetails;
use EventSourcery\EventSourcery\PersonalData\EncryptionKeyGenerator;
use EventSourcery\EventSourcery\PersonalData\PersonalCryptographyStore;
use EventSourcery\EventSourcery\PersonalData\PersonalDataEncryption;
use EventSourcery\EventSourcery\PersonalData\PersonalEncryptionKeyStore;
use EventSourcery\EventSourcery\PersonalData\PersonalKey;
use Monolith\RelationalDatabase\Query;

/**
 * The MonolithPersonalCryptographyStore is the Monolith-specific implementation
 * of a PersonalCryptographyStore. It uses the default relational driver for
 * the Monolith application.
 */
class MonolithPersonalCryptographyStore implements PersonalCryptographyStore
{

    /** @var Query */
    private $query;

    /** @var PersonalDataEncryption */
    private $encryption;

    private $table = 'personal_cryptography_store';

    public function __construct(PersonalDataEncryption $encryption, Query $query)
    {
        $this->query = $query;
        $this->encryption = $encryption;
    }

    /**
     * add a person (identified by personal key) and their cryptographic details
     *
     * @param PersonalKey $person
     * @param CryptographicDetails $crypto
     */
    function addPerson(PersonalKey $person, CryptographicDetails $crypto): void
    {
        $this->query->write(
            'insert into :table (personal_key, cryptographic_details, encryption) values(:personal_key, :cryptographic_details, :encryption)',
            [
                'table'                 => $this->table,
                'personal_key'          => $person->toString(),
                'cryptographic_details' => json_encode($crypto->serialize()),
                'encryption'            => $crypto->encryption(),
            ]
        );
    }

    /**
     * get cryptography details for a person (identified by personal key)
     *
     * @param PersonalKey $person
     * @throws CanNotFindCryptographyForPerson
     * @return CryptographicDetails
     * @throws \EventSourcery\EventSourcery\PersonalData\CannotDeserializeCryptographicDetails
     */
    function getCryptographyFor(PersonalKey $person): CryptographicDetails
    {
        $crypto = $this->query->read(
            'select * from :table where personal_key = :personal_key',
            [
                'table'        => $this->table,
                'personal_key' => $person->toString(),
            ]
        );

        if ( ! $crypto) {
            $this->addPerson($person, $this->encryption->generateCryptographicDetails());
            return $this->getCryptographyFor($person);
//            throw new CanNotFindCryptographyForPerson($person->toString());
        }

        $details = (array) json_decode($crypto->cryptographic_details);

        return CryptographicDetails::deserialize($details);
    }

    /**
     * remove cryptographic details for a person (identified by personal key)
     *
     * @param PersonalKey $person
     */
    function removePerson(PersonalKey $person): void
    {
        $this->query->write(
            'delete from :table where personal_key = :personal_key',
            [
                'table'        => $this->table,
                'personal_key' => $person->toString(),
            ]
        );
    }
}