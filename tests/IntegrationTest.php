<?php

namespace GraphQlMovies\Tests;

use GraphQL\Server\OperationParams;
use GraphQL\Server\StandardServer;
use GraphQLMovies\GraphQLFactory;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    /**
     * @var StandardServer
     */
    private $server;

    public function setUp(): void
    {
        $pdo = new \PDO(
            "mysql:host=127.0.0.1;dbname=sakila;charset=utf8mb4",
            'dev',
            '123456',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $this->server = (new GraphQLFactory(
            __DIR__ . '/../schema.graphqls',
            __DIR__ . '/../var/cached_schema.php',
            $pdo
        ))->build();
    }

    public function tearDown(): void
    {
        unset($this->server);
    }

    public function getCases()
    {
        $files = glob(__DIR__ . '/_case/*');
        return array_map(function ($filename) {
            return [$filename];
        }, $files);
    }

    /**
     * @dataProvider getCases
     */
    public function testCases($filename)
    {
        $data = file_get_contents($filename);
        list($query, $expectedResult) = explode('=====', $data);
        $result = $this->server->executeRequest(OperationParams::create([
            'query' => $query
        ]));
        $this->assertCount(0, $result->errors, json_encode($result->errors));
        $this->assertJsonStringEqualsJsonString($expectedResult, json_encode($result->data));
    }
}
