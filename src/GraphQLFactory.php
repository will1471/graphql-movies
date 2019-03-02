<?php

namespace GraphQLMovies;

use GraphQL\Error\Debug;
use GraphQL\Language\Parser;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ResolveInfo;
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

    public function build(): StandardServer
    {
        if (!file_exists($this->cache)) {
            $document = Parser::parse(file_get_contents($this->schema));
            file_put_contents($this->cache, "<?php\nreturn " . var_export(AST::toArray($document), true) . ";");
        } else {
            $document = AST::fromArray(require $this->cache);
        }

        $schema = BuildSchema::build($document);

        $serverConfig = new ServerConfig();
        $serverConfig->setSchema($schema);
        $serverConfig->setDebug(
            Debug::INCLUDE_TRACE
            | Debug::INCLUDE_DEBUG_MESSAGE
            | Debug::RETHROW_INTERNAL_EXCEPTIONS
        );
        $serverConfig->setPromiseAdapter(new SyncPromiseAdapter());
        $serverConfig->setFieldResolver(function ($ctx, $args, $context, ResolveInfo $resolveInfo) {
            if (is_array($ctx) && array_key_exists($resolveInfo->fieldName, $ctx)) {
                return $ctx[$resolveInfo->fieldName];
            }

            if ($resolveInfo->parentType->name == 'Query') {
                return ($this->queryFactory->get($resolveInfo->fieldName))($ctx, $args, $context, $resolveInfo);
            }

            if ($resolveInfo->fieldName == 'category') {
                return $this->dataLoaderFactory->movieCategories()->load($ctx['id']);
            }

            if ($resolveInfo->fieldName == 'actors') {
                return $this->dataLoaderFactory->movieActors()->load($ctx['id']);
            }

            throw new \Exception('unsupported');
        });

        return new StandardServer($serverConfig);
    }
}
