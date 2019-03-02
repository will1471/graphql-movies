<?php

namespace GraphQLMovies;

use DusanKasan\Knapsack\Collection;
use GraphQL\Type\Definition\ResolveInfo;

class GetMovieQuery
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
        $id = $args['id'];
        $columns = Helpers::buildColumns(array_keys($resolveInfo->getFieldSelection()), 'id', self::$map);
        $select = $this->pdo->prepare($sql = "SELECT $columns FROM film WHERE film_id = :id;");
        $select->execute(['id' => $id]);
        $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) != 1) {
            return null;
        }
        return Collection::from($rows)
            ->map(Helpers::renameKeys(array_flip(self::$map)))
            ->toArray()[0];
    }
}
