<?php

namespace GraphQLMovies;

use DusanKasan\Knapsack\Collection;
use Overblog\PromiseAdapter\PromiseAdapterInterface;

class ActorLoader
{
    private $pdo;
    private $promiseAdapter;

    public function __construct(\PDO $pdo, PromiseAdapterInterface $promiseAdapter)
    {
        $this->pdo = $pdo;
        $this->promiseAdapter = $promiseAdapter;
    }

    /**
     * @param array $keys Movie ids
     *
     * @return \GraphQL\Executor\Promise\Promise|mixed
     */
    public function __invoke(array $keys)
    {
        $keys = Collection::from($keys);

        $ids = Helpers::ids($keys);

        /* @todo only load requested rows */
        $columns = Helpers::buildColumns(
            ['id', 'firstName', 'lastName'],
            'id',
            [
                'id' => '`a`.`actor_id`',
                'firstName' => '`a`.`first_name`',
                'lastName' => '`a`.`last_name`',
            ]
        );

        $sql = <<<SQL
SELECT fa.film_id, $columns
FROM actor a
JOIN film_actor fa ON a.actor_id = fa.actor_id
WHERE fa.film_id IN ($ids);
SQL;

        $select = $this->pdo->prepare($sql);
        $select->execute([]);
        $rows = $select->fetchAll(\PDO::FETCH_ASSOC);

        $map = [
            'actor_id' => 'id',
            'first_name' => 'firstName',
            'last_name' => 'lastName',
            'film_id' => 'film_id'
        ];

        $grouped = Collection::from($rows)
            ->map(Helpers::renameKeys($map))
            ->groupByKey('film_id')
            ->map('DusanKasan\Knapsack\toArray');

        return $this->promiseAdapter->createAll(Helpers::dataLoaderResponseFromGrouped($keys, $grouped));
    }
}