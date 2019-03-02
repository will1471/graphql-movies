<?php

namespace GraphQLMovies;

use Overblog\DataLoader\DataLoader;

class DataLoaderFactory
{

    private $pdo;

    private $dataLoaderPromiseAdapter;

    /**
     * @var DataLoader|null
     */
    private $movieActors;

    /**
     * @var DataLoader|null
     */
    private $movieCategories;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->dataLoaderPromiseAdapter = new \Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter();
    }

    public function movieActors(): DataLoader
    {
        if (!isset($this->movieActors)) {
            $this->movieActors = new DataLoader(
                new ActorLoader(
                    $this->pdo,
                    $this->dataLoaderPromiseAdapter
                ),
                $this->dataLoaderPromiseAdapter
            );
        }
        return $this->movieActors;
    }

    public function movieCategories(): DataLoader
    {
        if (!isset($this->movieCategories)) {
            $this->movieCategories = new DataLoader(
                new CategoryLoader(
                    $this->pdo,
                    $this->dataLoaderPromiseAdapter
                ),
                $this->dataLoaderPromiseAdapter
            );
        }
        return $this->movieCategories;
    }
}