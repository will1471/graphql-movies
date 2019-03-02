<?php

namespace GraphQLMovies;

use GraphQL\Error\Debug;
use GraphQL\Language\Parser;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use Overblog\DataLoader\DataLoader;
use Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter;

class GraphQLFactory
{
    private $schema;
    private $cache;
    private $pdo;
    private $dataLoaderPromiseAdapter;
    private $actorLoader;
    private $categoryLoader;

    public function __construct(string $schema, string $cache, \PDO $pdo)
    {
        $this->schema = $schema;
        $this->cache = $cache;
        $this->pdo = $pdo;
        $this->dataLoaderPromiseAdapter = new \Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter();
        $this->actorLoader = new DataLoader(
            new ActorLoader($this->pdo, $this->dataLoaderPromiseAdapter),
            $this->dataLoaderPromiseAdapter);
        $this->categoryLoader = new DataLoader(
            new CategoryLoader($this->pdo, $this->dataLoaderPromiseAdapter),
            $this->dataLoaderPromiseAdapter
        );
    }

    public function listMovies(): ListMoviesQuery
    {
        return new ListMoviesQuery($this->pdo);
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
        $serverConfig->setFieldResolver(function ($ctx, $args, $context, ResolveInfo $resolveInfo) use ($pdo, $actorLoader, $categoryLoader) {
            if (isset($ctx[$resolveInfo->fieldName])) {
                return $ctx[$resolveInfo->fieldName];
            }
            if ($resolveInfo->fieldName == 'listMovies') {
                return ($this->listMovies())($ctx, $args, $context, $resolveInfo);
            }
            if ($resolveInfo->fieldName == 'category') {
                return $this->categoryLoader->load($ctx['id']);
            }
            if ($resolveInfo->fieldName == 'actors') {
                return $this->actorLoader->load($ctx['id']);
            }
            throw new \Exception('unsupported');
        });

        return new StandardServer($serverConfig);
    }
}
