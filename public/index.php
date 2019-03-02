<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../vendor/autoload.php';

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

$app->map(['GET', 'POST'], '/graphql', function (Request $request, Response $response) use ($pdo): ResponseInterface {
    $server = (new \GraphQLMovies\GraphQLFactory(
        __DIR__ . '/../schema.graphqls',
        __DIR__ . '/../var/cached_schema.php',
        $pdo
    ))->build();
    return $server->processPsrRequest($request, $response, $response->getBody());
});

$app->run();