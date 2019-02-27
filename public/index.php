<?php

use GraphQL\Type\Definition\ResolveInfo;
use Slim\Http\Request;
use Slim\Http\Response;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../vendor/autoload.php';

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

function build_columns(array $map, ResolveInfo $resolveInfo, string $idKey = 'id'): string
{
    $columms = [$map[$idKey]];
    foreach (array_keys($resolveInfo->getFieldSelection()) as $k) {
        if (isset($map[$k])) $columms[] = $map[$k];
    }
    $columms = array_unique($columms);
    $columms = implode(', ', $columms);
    return $columms;
}

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
        print_r($e);
    }
});

$app->map(['GET', 'POST'], '/', function (Request $request, Response $response): ResponseInterface {
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

    $contents = file_get_contents(__DIR__ . '/schema.graphqls');
    $schema = \GraphQL\Utils\BuildSchema::build($contents);

    $serverConfig = new \GraphQL\Server\ServerConfig();
    $serverConfig->setSchema($schema);
    $serverConfig->setDebug(true);
    $serverConfig->setFieldResolver(function ($ctx, $args, $context, ResolveInfo $resolveInfo) use ($pdo, $logger) {

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
            ], $resolveInfo, 'id');
            $select = $pdo->prepare($sql = "SELECT $columns FROM film LIMIT $limit;");
            $select->execute([]);
            $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
            return mysql_rows_to_data($rows, array_flip($map));
        }

        if ($resolveInfo->fieldName == 'category') {
            $sql = <<<SQL
SELECT name
FROM category c
JOIN film_category fc ON c.category_id = fc.category_id
WHERE fc.film_id = :film_id
SQL;
            $select = $pdo->prepare($sql);
            $select->execute($params = ['film_id' => $ctx['id']]);
            $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
            return array_column($rows, 'name');
        }

        if ($resolveInfo->fieldName == 'actors') {
            $columns = build_columns([
                'id' => '`a`.`actor_id`',
                'firstName' => '`a`.`first_name`',
                'lastName' => '`a`.`last_name`',
            ], $resolveInfo, 'id');
            $sql = <<<SQL
SELECT $columns
FROM actor a
JOIN film_actor fa ON a.actor_id = fa.actor_id
WHERE fa.film_id = :film_id;
SQL;
            $select = $pdo->prepare($sql);
            $select->execute($params = ['film_id' => $ctx['id']]);
            $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
            return mysql_rows_to_data($rows, [
                'actor_id' => 'id',
                'first_name' => 'firstName',
                'last_name' => 'lastName',
            ]);
        }

        throw new \Exception('unsupported');
    });
    $server = new \GraphQL\Server\StandardServer($serverConfig);
    return $server->processPsrRequest($request, $response, $response->getBody());
});

$app->run();