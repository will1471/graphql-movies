<?php

namespace GraphQLMovies;

use GraphQL\Error\Debug;
use GraphQL\Language\Parser;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter;

class GraphQLFactory
{
    private $schema;
    private $cache;
    private $queryFactory;
    private $dataLoaderFactory;

    public function __construct(string $schema, string $cache, \PDO $pdo)
    {
        $this->schema = $schema;
        $this->cache = $cache;
        $this->dataLoaderFactory = new DataLoaderFactory($pdo);
        $this->queryFactory = new QueryFactory($pdo);
    }

    public function buildSchema(): Schema
    {
        if (!file_exists($this->cache)) {
            $document = Parser::parse(file_get_contents($this->schema));
            file_put_contents(
                $this->cache,
                "<?php\nreturn " . var_export(AST::toArray($document), true) . ";"
            );
        } else {
            $document = AST::fromArray(require $this->cache);
        }

        return BuildSchema::build($document);
    }

    public function build(): StandardServer
    {
        $serverConfig = new ServerConfig();
        $serverConfig->setSchema($this->buildSchema());
        $serverConfig->setDebug(
            Debug::INCLUDE_TRACE
            | Debug::INCLUDE_DEBUG_MESSAGE
            | Debug::RETHROW_INTERNAL_EXCEPTIONS
        );
        $serverConfig->setPromiseAdapter(new SyncPromiseAdapter());
        $serverConfig->setFieldResolver(
            new FieldResolver($this->queryFactory, $this->dataLoaderFactory)
        );
        return new StandardServer($serverConfig);
    }
}
