<?php

namespace GraphQLMovies;

class QueryFactory
{

    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function get(string $name): callable
    {
        switch ($name) {
            case 'listMovies':
                return new ListMoviesQuery($this->pdo);
            case'getMovie':
                return new GetMovieQuery($this->pdo);
            default:
                throw new \Exception('Unknown Query');
        }
    }
}
