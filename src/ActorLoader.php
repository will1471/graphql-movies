<?php

namespace GraphQLMovies;

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
        $ids = array_unique($keys);
        $ids = array_map('intval', $ids);
        $ids = join(',', $ids);

        /* @todo only load requested rows */
        $columns = build_columns([
            'id' => '`a`.`actor_id`',
            'firstName' => '`a`.`first_name`',
            'lastName' => '`a`.`last_name`',
        ], ['id', 'firstName', 'lastName'], 'id');

        $sql = <<<SQL
SELECT fa.film_id, $columns
FROM actor a
JOIN film_actor fa ON a.actor_id = fa.actor_id
WHERE fa.film_id IN ($ids);
SQL;
        $select = $this->pdo->prepare($sql);
        $select->execute([]);
        $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
        $data = mysql_rows_to_data($rows, [
            'actor_id' => 'id',
            'first_name' => 'firstName',
            'last_name' => 'lastName',
            'film_id' => 'film_id'
        ]);

        $groups = group_by_key($data, 'film_id');
        $result = [];
        foreach ($keys as $key) {
            if (isset($groups[$key])) {
                $result[] = $groups[$key];
            } else {
                $result[] = [];
            }
        }
        return $this->promiseAdapter->createAll($result);
    }
}