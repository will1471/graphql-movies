<?php

namespace GraphQLMovies;

use DusanKasan\Knapsack\Collection;
use GraphQL\Type\Definition\ResolveInfo;

class ListMoviesQuery
{
    private $pdo;

    private static $map = [
        'id' => 'film_id',
        'name' => 'title',
        'description' => 'description',
        'year' => 'release_year'
    ];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function __invoke($ctx, $args, $context, ResolveInfo $resolveInfo)
    {
        $limit = (int)$args['limit'];

        $columns = Helpers::buildColumns(
            array_keys($resolveInfo->getFieldSelection()),
            'id',
            self::$map
        );

        $select = $this->pdo->prepare($sql = "SELECT $columns FROM film LIMIT $limit;");
        $select->execute([]);
        $rows = $select->fetchAll(\PDO::FETCH_ASSOC);

        return Collection::from($rows)
            ->map(Helpers::renameKeys(array_flip(self::$map)))
            ->toArray();
    }
}