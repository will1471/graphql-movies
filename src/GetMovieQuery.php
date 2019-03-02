<?php

namespace GraphQLMovies;

use GraphQL\Type\Definition\ResolveInfo;

class GetMovieQuery
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function __invoke($ctx, $args, $context, ResolveInfo $resolveInfo)
    {
        $id = $args['id'];
        $columns = build_columns($map = [
            'id' => 'film_id',
            'name' => 'title',
            'description' => 'description',
            'year' => 'release_year'
        ], array_keys($resolveInfo->getFieldSelection()), 'id');
        $select = $this->pdo->prepare($sql = "SELECT $columns FROM film WHERE film_id = :id;");
        $select->execute(['id' => $id]);
        $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) != 1) {
            throw new \Exception('Movie not found');
        }
        return mysql_rows_to_data($rows, array_flip($map))[0];
    }
}