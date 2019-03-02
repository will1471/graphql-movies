<?php

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

$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true,
    ]
]);

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

$app->map(['GET', 'POST'], '/graphql', function (Request $request, Response $response) use ($pdo): ResponseInterface {
    $server = (new \GraphQLMovies\GraphQLFactory(
        __DIR__ . '/../schema.graphqls',
        __DIR__ . '/../var/cached_schema.php',
        $pdo
    ))->build();
    return $server->processPsrRequest($request, $response, $response->getBody());
});

$app->run();