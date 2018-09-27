<?php namespace EventSourcery\Monolith;

use DB;
use EventSourcery\EventSourcery\PersonalData\CanNotFindPersonalDataByKey;
use EventSourcery\EventSourcery\PersonalData\CouldNotRetrievePersonalData;
use EventSourcery\EventSourcery\PersonalData\EncryptedPersonalData;
use EventSourcery\EventSourcery\PersonalData\PersonalCryptographyStore;
use EventSourcery\EventSourcery\PersonalData\PersonalData;
use EventSourcery\EventSourcery\PersonalData\PersonalDataEncryption;
use EventSourcery\EventSourcery\PersonalData\PersonalDataKey;
use EventSourcery\EventSourcery\PersonalData\PersonalDataStore;
use EventSourcery\EventSourcery\PersonalData\PersonalEncryptionKeyStore;
use EventSourcery\EventSourcery\PersonalData\PersonalKey;
use EventSourcery\EventSourcery\PersonalData\ProtectedData;
use EventSourcery\EventSourcery\PersonalData\ProtectedDataKey;
use Monolith\RelationalDatabase\Query;

/**
 * The MonolithPersonalDataStore is the Monolith-specific implementation of
 * a PersonalDataStore. It uses the default relational driver configured
 * in the Monolith application.
 */
class MonolithPersonalDataStore implements PersonalDataStore
{

    /** @var PersonalCryptographyStore */
    private $cryptographyStore;

    /** @var PersonalDataEncryption */
    private $encryption;

    /** @var Query */
    private $query;

    private $table = 'personal_data_store';

    public function __construct(PersonalCryptographyStore $cryptographyStore, PersonalDataEncryption $encryption, Query $query)
    {
        $this->cryptographyStore = $cryptographyStore;
        $this->encryption = $encryption;
        $this->query = $query;
    }

    /**
     * retrieve data from the personal data store based on a personal key and data key.
     *
     * @param PersonalKey $personalKey
     * @param PersonalDataKey $dataKey
     * @return PersonalData
     * @throws CanNotFindPersonalDataByKey
     * @throws \EventSourcery\EventSourcery\PersonalData\CryptographicDetailsDoNotContainKey
     * @throws \EventSourcery\EventSourcery\PersonalData\CryptographicDetailsNotCompatibleWithEncryption
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    public function retrieveData(PersonalKey $personalKey, PersonalDataKey $dataKey): PersonalData
    {
        $data = $this->query->read(
            "select * from {$this->table} where data_key = :data_key",
            [
                'personal_key' => $dataKey->serialize(),
            ]
        );

        if ( ! $data) {
            throw new CanNotFindPersonalDataByKey($dataKey->toString());
        }

        $decrypted = $this->encryption->decrypt(
            EncryptedPersonalData::deserialize($data->encrypted_personal_data),
            $this->cryptographyStore->getCryptographyFor($personalKey)
        )->toString();

        return PersonalData::fromString($decrypted);
    }

    /**
     * store data in the personal data store identified by a personal key and a data key
     *
     * @param PersonalKey $personalKey
     * @param PersonalDataKey $dataKey
     * @param PersonalData $data
     * @throws \EventSourcery\EventSourcery\PersonalData\CryptographicDetailsDoNotContainKey
     * @throws \EventSourcery\EventSourcery\PersonalData\CryptographicDetailsNotCompatibleWithEncryption
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    public function storeData(PersonalKey $personalKey, PersonalDataKey $dataKey, PersonalData $data): void
    {
        $crypto = $this->cryptographyStore->getCryptographyFor($personalKey);

        $this->query->write(
            "insert into {$this->table} (personal_key, data_key, encrypted_personal_data, encryption, stored_at) values (:personal_key, :data_key, :encrypted_personal_data, :encryption, :stored_at)",
            [
                'personal_key'            => $personalKey->toString(),
                'data_key'                => $dataKey->toString(),
                'encrypted_personal_data' => $this->encryption->encrypt($data, $crypto)->serialize(),
                'encryption'              => $crypto->encryption(),
                'stored_at'               => date('Y-m-d H:i:s')
            ]
        );
    }

    /**
     * remove all data for a person from the data store
     *
     * @param PersonalKey $personalKey
     * @throws \Monolith\RelationalDatabase\CanNotExecuteQuery
     */
    function removeDataFor(PersonalKey $personalKey): void
    {
        $this->query->write(
            "delete from {$this->table} where personal_key = :personal_key",
            [
                'personal_key' => $personalKey->serialize(),
            ]
        );
    }
}