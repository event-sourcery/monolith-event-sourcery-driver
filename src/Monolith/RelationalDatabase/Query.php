<?php namespace Monolith\RelationalDatabase;

class Query {

    /** @var PDO */
    private $pdo;

    public function __construct($dsn) {
        $this->pdo = new PDO($dsn);
    }

    public function execute($query, array $placeholders = []) {
        $statement = $this->pdo->prepare($query);
        $statement->execute($placeholders);
        return $statement->fetch();
    }
}