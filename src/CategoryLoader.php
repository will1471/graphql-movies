<?php

namespace GraphQLMovies;

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
        $ids = array_unique($keys);
        $ids = array_map('intval', $ids);
        $ids = join(',', $ids);

        $sql = <<<SQL
SELECT fc.film_id, name
FROM category c
JOIN film_category fc ON c.category_id = fc.category_id
WHERE fc.film_id IN ($ids);
SQL;
        $select = $this->pdo->prepare($sql);
        $select->execute([]);
        $rows = $select->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = group_by_key($rows, 'film_id');
        $result = [];
        foreach ($keys as $key) {
            if (isset($grouped[$key])) {
                $result[] = array_map(function (array $row): string {
                    return $row['name'];
                }, $grouped[$key]);
            } else {
                $result[] = [];
            }
        }
        return $this->promiseAdapter->createFulfilled($result);
    }
}