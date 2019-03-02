<?php

use GraphQL\Error\Debug;
use GraphQL\Type\Definition\ResolveInfo;
use Slim\Http\Request;
use Slim\Http\Response;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../vendor/autoload.php';

function group_by_key(array $data, string $key): array
{
    $a = [];
    foreach ($data as $datum) {
        if (!isset($a[$datum[$key]])) {
            $a[$datum[$key]] = [];
        }
        $a[$datum[$key]][] = $datum;
    }
    return $a;
}

function mysql_rows_to_data(array $rows, array $map): array
{
    return array_map(function (array $row) use ($map): array {
        $d = [];
        foreach ($row as $k => $v) {
            $d[$map[$k]] = $v;
        }
        return $d;
    }, $rows);
}

function build_columns(array $map, array $usedFields, string $idKey = 'id'): string
{
    $columms = [$map[$idKey]];
    foreach ($usedFields as $k) {
        if (isset($map[$k])) $columms[] = $map[$k];
    }
    $columms = array_unique($columms);
    $columms = implode(', ', $columms);
    return $columms;
}

class BatchGetActorsForMovie
{
    private $pdo;
    private $promiseAdapter;

    public function __construct(\PDO $pdo, \Overblog\PromiseAdapter\PromiseAdapterInterface $promiseAdapter)
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

class BatchGetMovieCategories
{
    private $pdo;
    private $promiseAdapter;

    public function __construct(\PDO $pdo, \Overblog\PromiseAdapter\PromiseAdapterInterface $promiseAdapter)
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

$host = getenv('DB_HOST');
$pdo = new PDO(
    "mysql:host=$host;dbname=sakila;charset=utf8mb4",
    getenv('DB_USER'),
    getenv('DB_PASSWORD'),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$dataLoaderPromiseAdapter = new \Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter();

$actorLoader = new \Overblog\DataLoader\DataLoader(
    new BatchGetActorsForMovie($pdo, $dataLoaderPromiseAdapter),
    $dataLoaderPromiseAdapter
);

$categoryLoader = new \Overblog\DataLoader\DataLoader(
    new BatchGetMovieCategories($pdo, $dataLoaderPromiseAdapter),
    $dataLoaderPromiseAdapter
);

$app = new \Slim\App();

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});


$app->add(function (Request $request, Response $response, callable $next) {
    try {
        return $next($request, $response);
    } catch (\Exception $e) {
        echo '<pre>';
        print_r($e->getMessage());
        echo "\n";
        echo $e->getTraceAsString();
        echo "\n\n\n";
        if ($e->getPrevious()) {
            print_r($e->getPrevious()->getMessage());
            echo $e->getPrevious()->getTraceAsString();
        }
    }
});

$app->map(['GET', 'POST'], '/graphql', function (Request $request, Response $response) use ($pdo, $actorLoader, $categoryLoader, $dataLoaderPromiseAdapter): ResponseInterface {

    $cacheFilename = __DIR__ . '/../var/cached_schema.php';
    if (!file_exists($cacheFilename)) {
        $document = \GraphQL\Language\Parser::parse(file_get_contents(__DIR__ . '/schema.graphqls'));
        file_put_contents($cacheFilename, "<?php\nreturn " . var_export(\GraphQL\Utils\AST::toArray($document), true) . ";");
    } else {
        $document = \GraphQL\Utils\AST::fromArray(require $cacheFilename);
    }

    $schema = \GraphQL\Utils\BuildSchema::build($document);

    $serverConfig = new \GraphQL\Server\ServerConfig();
    $serverConfig->setSchema($schema);
    $serverConfig->setDebug(
        Debug::INCLUDE_TRACE
        | Debug::INCLUDE_DEBUG_MESSAGE
        | Debug::RETHROW_INTERNAL_EXCEPTIONS
    );
    $serverConfig->setPromiseAdapter(new \Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter());
    $serverConfig->setFieldResolver(function ($ctx, $args, $context, ResolveInfo $resolveInfo) use ($pdo, $actorLoader, $categoryLoader) {

        if (isset($ctx[$resolveInfo->fieldName])) {
            return $ctx[$resolveInfo->fieldName];
        }

        if ($resolveInfo->fieldName == 'listMovies') {
            $limit = (int)$args['limit'];
            $columns = build_columns($map = [
                'id' => 'film_id',
                'name' => 'title',
                'description' => 'description',
                'year' => 'release_year'
            ], array_keys($resolveInfo->getFieldSelection()), 'id');
            $select = $pdo->prepare($sql = "SELECT $columns FROM film LIMIT $limit;");
            $select->execute([]);
            $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
            return mysql_rows_to_data($rows, array_flip($map));
        }

        if ($resolveInfo->fieldName == 'category') {
            return $categoryLoader->load($ctx['id']);
        }

        if ($resolveInfo->fieldName == 'actors') {
            return $actorLoader->load($ctx['id']);
        }

        throw new \Exception('unsupported');
    });
    $server = new \GraphQL\Server\StandardServer($serverConfig);
    return $server->processPsrRequest($request, $response, $response->getBody());
});

$app->run();