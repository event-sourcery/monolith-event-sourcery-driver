<?php namespace EventSourcery\Monolith;

use EventSourcery\EventSourcery\PersonalData\CouldNotFindCryptographyForPerson;
use EventSourcery\EventSourcery\PersonalData\CryptographicDetails;
use EventSourcery\EventSourcery\PersonalData\EncryptionKeyGenerator;
use EventSourcery\EventSourcery\PersonalData\PersonalCryptographyStore;
use EventSourcery\EventSourcery\PersonalData\PersonalDataEncryption;
use EventSourcery\EventSourcery\PersonalData\PersonalEncryptionKeyStore;
use EventSourcery\EventSourcery\PersonalData\PersonalKey;

/**
 * The MonolithPersonalCryptographyStore is the Monolith-specific implementation
 * of a PersonalCryptographyStore. It uses the default relational driver for
 * the Monolith application.
 */
class MonolithPersonalCryptographyStore implements PersonalCryptographyStore
{

    /** @var PersonalCryptographyStoreDb */
    private $db;

    /** @var PersonalDataEncryption */
    private $encryption;

    private $table = 'personal_cryptography_store';

    public function __construct(PersonalDataEncryption $encryption, PersonalCryptographyStoreDb $db)
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
     * @throws CanNotAddCryptoPersonAlreadyHasCrypto
     */
    function addPerson(PersonalKey $person, CryptographicDetails $crypto): void
    {
        if ($this->hasPerson($person)) {
            throw new CanNotAddCryptoPersonAlreadyHasCrypto($person->toString());
        }

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

    function hasPerson(PersonalKey $person): bool
    {
        $crypto = $this->db->readFirst(
            "select * from {$this->table} where personal_key = :personal_key",
            [
                'personal_key' => $person->toString(),
            ]
        );

        return $crypto && isset($crypto->cryptographic_details);
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

    /**
     * get cryptography details for a person (identified by personal key)
     *
     * @param PersonalKey $person
     * @return CryptographicDetails
     * @throws \EventSourcery\EventSourcery\PersonalData\CannotDeserializeCryptographicDetails
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     * @throws CryptoCouldNotBeFoundForPerson
     * @throws CanNotRetrieveCryptoForARemovedPerson
     */
    public function getCryptographyFor(PersonalKey $person): CryptographicDetails
    {
        if ( ! $this->hasPerson($person)) {
            throw new CryptoCouldNotBeFoundForPerson($person->toString());
        }

        $crypto = $this->db->readFirst(
            "select * from {$this->table} where personal_key = :personal_key",
            [
                'personal_key' => $person->toString(),
            ]
        );

        if (empty($crypto->cryptographic_details)) {
            throw new CanNotRetrieveCryptoForARemovedPerson;
        }
        
        $details = (array) json_decode($crypto->cryptographic_details);
        return CryptographicDetails::deserialize($details);
    }

    /**
     * get the current encryption algorithm.
     */
    function getEncryption(): PersonalDataEncryption
    {
        return $this->encryption;
    }
}