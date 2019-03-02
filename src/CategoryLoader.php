<?php

namespace GraphQLMovies;

use DusanKasan\Knapsack\Collection;
use Overblog\PromiseAdapter\PromiseAdapterInterface;

class CategoryLoader
{
    private $pdo;
    private $promiseAdapter;

    public function __construct(\PDO $pdo, PromiseAdapterInterface $promiseAdapter)
    {
        $this->pdo = $pdo;
        $this->promiseAdapter = $promiseAdapter;
    }

    /**
     * @param array $keys movie ids
     * @return \GraphQL\Executor\Promise\Promise
     */
    public function __invoke(array $keys)
    {
        $keys = Collection::from($keys);

        $ids = Helpers::ids($keys);

        $sql = <<<SQL
SELECT fc.film_id, name
FROM category c
JOIN film_category fc ON c.category_id = fc.category_id
WHERE fc.film_id IN ($ids);
SQL;
        $select = $this->pdo->prepare($sql);
        $select->execute([]);
        $rows = $select->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = (new Collection($rows))
            ->groupByKey('film_id')
            ->map(function (Collection $grouped) {
                return $grouped
                    ->map(function ($category) {
                        return $category['name'];
                    })
                    ->toArray();
            });

        return $this->promiseAdapter->createAll(Helpers::dataLoaderResponseFromGrouped($keys, $grouped));
    }
}