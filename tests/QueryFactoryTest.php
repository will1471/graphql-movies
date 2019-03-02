<?php

namespace GraphQlMovies\Tests;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQLMovies\Exception\UnknownQuery;
use GraphQLMovies\GraphQLFactory;
use GraphQLMovies\QueryFactory;
use PHPUnit\Framework\TestCase;

class QueryFactoryTest extends TestCase
{

    /**
     * @var QueryFactory
     */
    private $factory;

    public function setUp(): void
    {
        $this->factory = new QueryFactory(
            $this->createMock(\PDO::class)
        );
    }

    public function testInvalidQuery()
    {
        $this->expectException(UnknownQuery::class);
        $this->factory->get('not a valid query');
    }

    /**
     * @dataProvider findQueries
     */
    public function testBuildsExpectedQueries(string $name)
    {
        $i = $this->factory->get($name);
        $this->assertIsCallable($i);
    }

    public function findQueries()
    {
        $schema = (new GraphQLFactory(
            __DIR__ . '/../schema.graphqls',
            __DIR__ . '/../var/cached_schema.php',
            $this->createMock(\PDO::class)
        ))->buildSchema();

        return array_map(
            function (FieldDefinition $fieldDefinition) {
                return [$fieldDefinition->name];
            },
            $schema->getQueryType()->getFields()
        );
    }
}
