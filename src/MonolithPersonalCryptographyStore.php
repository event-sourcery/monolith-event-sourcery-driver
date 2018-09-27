<?php namespace EventSourcery\Monolith;

use EventSourcery\EventSourcery\PersonalData\CanNotFindCryptographyForPerson;
use EventSourcery\EventSourcery\PersonalData\CouldNotFindCryptographyForPerson;
use EventSourcery\EventSourcery\PersonalData\CryptographicDetails;
use EventSourcery\EventSourcery\PersonalData\EncryptionKeyGenerator;
use EventSourcery\EventSourcery\PersonalData\PersonalCryptographyStore;
use EventSourcery\EventSourcery\PersonalData\PersonalDataEncryption;
use EventSourcery\EventSourcery\PersonalData\PersonalEncryptionKeyStore;
use EventSourcery\EventSourcery\PersonalData\PersonalKey;
use Monolith\RelationalDatabase\Db;

/**
 * The MonolithPersonalCryptographyStore is the Monolith-specific implementation
 * of a PersonalCryptographyStore. It uses the default relational driver for
 * the Monolith application.
 */
class MonolithPersonalCryptographyStore implements PersonalCryptographyStore
{

    /** @var Db */
    private $db;

    /** @var PersonalDataEncryption */
    private $encryption;

    private $table = 'personal_cryptography_store';

    public function __construct(PersonalDataEncryption $encryption, Db $db)
    {
        $this->db = $db;
        $this->encryption = $encryption;
    }

    /**
     * add a person (identified by personal key) and their cryptographic details
     *
     * @param PersonalKey $person
     * @param CryptographicDetails $crypto
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    function addPerson(PersonalKey $person, CryptographicDetails $crypto): void
    {
        $this->db->write(
            "insert into {$this->table} (personal_key, cryptographic_details, encryption, added_at) values(:personal_key, :cryptographic_details, :encryption, :added_at)",
            [
                'personal_key'          => $person->toString(),
                'cryptographic_details' => json_encode($crypto->serialize()),
                'encryption'            => $crypto->encryption(),
                'added_at'              => date('Y-m-d H:i:s'),
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
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    function getCryptographyFor(PersonalKey $person): CryptographicDetails
    {
        $crypto = $this->db->readOne(
            "select * from {$this->table} where personal_key = :personal_key",
            [
                'personal_key' => $person->toString(),
            ]
        );

        if ( ! $crypto) {
            $this->addPerson($person, $this->encryption->generateCryptographicDetails());
            return $this->getCryptographyFor($person);
        }

        $details = (array) json_decode($crypto->cryptographic_details);

        return CryptographicDetails::deserialize($details);
    }

    /**
     * remove cryptographic details for a person (identified by personal key)
     *
     * @param PersonalKey $person
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    function removePerson(PersonalKey $person): void
    {
        $this->db->write(
            "update {$this->table} set cryptographic_details = '', cleared_at = :cleared_at where personal_key = :personal_key",
            [
                'personal_key' => $person->toString(),
                'cleared_at'   => date('Y-m-d H:i:s'),
            ]
        );
    }
}