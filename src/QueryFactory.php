<?php

namespace GraphQLMovies;

class QueryFactory
{

    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function get(string $name)
    {
        switch ($name) {
            case 'listMovies':
                return new ListMoviesQuery($this->pdo);
            default:
                throw new \Exception('Unknown Query');
        }
    }
}
