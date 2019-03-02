<?php

namespace GraphQLMovies;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

final class FieldResolver
{
    /**
     * @var QueryFactory
     */
    private $queryFactory;
    /**
     * @var DataLoaderFactory
     */
    private $dataLoaderFactory;

    public function __construct(QueryFactory $queryFactory, DataLoaderFactory $dataLoaderFactory)
    {
        $this->queryFactory = $queryFactory;
        $this->dataLoaderFactory = $dataLoaderFactory;
    }

    /**
     * @param mixed $ctx
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $resolveInfo
     * @return mixed
     */
    public function __invoke($ctx, $args, $context, ResolveInfo $resolveInfo)
    {
        if (!is_string($resolveInfo->fieldName)) {
            throw new \LogicException();
        }

        if (!$resolveInfo->parentType instanceof ObjectType) {
            throw new \LogicException();
        }

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
    }
}
