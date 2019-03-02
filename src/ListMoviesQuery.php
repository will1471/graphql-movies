<?php

namespace GraphQLMovies;

use GraphQL\Type\Definition\ResolveInfo;

class ListMoviesQuery
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function __invoke($ctx, $args, $context, ResolveInfo $resolveInfo)
    {
        $limit = (int)$args['limit'];
        $columns = build_columns($map = [
            'id' => 'film_id',
            'name' => 'title',
            'description' => 'description',
            'year' => 'release_year'
        ], array_keys($resolveInfo->getFieldSelection()), 'id');
        $select = $this->pdo->prepare($sql = "SELECT $columns FROM film LIMIT $limit;");
        $select->execute([]);
        $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
        return mysql_rows_to_data($rows, array_flip($map));
    }
}