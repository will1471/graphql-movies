<?php

namespace GraphQLMovies;

final class QueryFactory
{

    /**
     * @var \PDO
     */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param string $name
     *
     * @return callable
     *
     * @throws Exception\UnknownQuery
     */
    public function get(string $name): callable
    {
        switch ($name) {
            case 'listMovies':
                return new ListMoviesQuery($this->pdo);
            case 'getMovie':
                return new GetMovieQuery($this->pdo);
            default:
                throw new Exception\UnknownQuery();
        }
    }
}
