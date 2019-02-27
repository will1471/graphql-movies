<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Slim\Http\Request;
use Slim\Http\Response;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../vendor/autoload.php';

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

    $actorType = new ObjectType([
        'name' => 'Actor',
        'fields' => [
            'id' => ['type' => Type::id()],
            'firstName' => ['type' => Type::nonNull(Type::string())],
            'lastName' => ['type' => Type::nonNull(Type::string())],
        ]
    ]);

    $movieType = new ObjectType([
        'name' => 'Movie',
        'fields' => [
            'id' => ['type' => Type::id()],
            'name' => ['type' => Type::string()],
            'description' => ['type' => Type::string()],
            'year' => ['type' => Type::int()],
            'actors' => [
                'type' => Type::listOf($actorType),
                'resolve' => function ($movie) use ($pdo) {
                    $sql = <<<SQL
SELECT actor.*
FROM actor
JOIN film_actor ON actor.actor_id = film_actor.actor_id
WHERE film_id = :film_id;
SQL;
                    $select = $pdo->prepare($sql);
                    $select->execute(['film_id' => $movie['id']]);
                    $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
                    return array_map(function (array $row): array {
                        return [
                            'id' => $row['actor_id'],
                            'firstName' => $row['first_name'],
                            'lastName' => $row['last_name'],
                        ];
                    }, $rows);
                }
            ],
            'category' => [
                'type' => Type::listOf(Type::string()),
                'resolve' => function ($movie) use ($pdo) {
                    $sql = <<<SQL
SELECT name
FROM category
JOIN film_category fc ON category.category_id = fc.category_id
WHERE fc.film_id = :film_id
SQL;
                    $select = $pdo->prepare($sql);
                    $select->execute(['film_id' => $movie['id']]);
                    $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
                    return array_map(function (array $row): string {
                        return $row['name'];
                    }, $rows);
                }
            ]
        ]
    ]);

    $queryType = new ObjectType([
        'name' => 'Query',
        'fields' => [
            'echo' => [
                'type' => Type::string(),
                'args' => [
                    'message' => Type::nonNull(Type::string()),
                ],
                'resolve' => function ($root, $args) {
                    return $root['prefix'] . $args['message'];
                }
            ],

            'listMovies' => [
                'type' => Type::listOf($movieType),
                'args' => [
                    'limit' => [
                        'name' => 'limit',
                        'type' => Type::int(),
                        'defaultValue' => 10
                    ]
                ],
                'resolve' => function ($root, $args, $context, ResolveInfo $resolveInfo) use ($pdo) {
                    $limit = (int)$args['limit'];

                    // var_dump($resolveInfo->getFieldSelection());
                    $select = $pdo->prepare('SELECT * FROM film LIMIT ' . $limit);
                    $select->execute([]);
                    $rows = $select->fetchAll(\PDO::FETCH_ASSOC);
                    return array_map(function (array $row): array {
                        return [
                            'id' => $row['film_id'],
                            'name' => $row['title'],
                            'description' => $row['description'],
                            'year' => $row['release_year']
                        ];
                    }, $rows);
                }
            ]
        ],
    ]);

    $schema = new \GraphQL\Type\Schema([
        'query' => $queryType
    ]);
    $serverConfig = new \GraphQL\Server\ServerConfig();
    $serverConfig->setSchema($schema);
    $serverConfig->setDebug(true);
    $server = new \GraphQL\Server\StandardServer($serverConfig);
    return $server->processPsrRequest($request, $response, $response->getBody());
});

$app->run();