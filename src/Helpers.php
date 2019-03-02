<?php

namespace GraphQLMovies;

use DusanKasan\Knapsack\Collection;

class Helpers
{
    public static function renameKeys(array $mapping): callable
    {
        return function (array $data) use ($mapping): array {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$mapping[$key]] = $value;
            }
            return $result;
        };
    }

    public static function ids(Collection $keys): string
    {
        return $keys->map('intval')
            ->interpose(',')
            ->toString();
    }

    public static function buildColumns(array $fields, string $idField, array $map): string
    {
        return Collection::from($fields)
            ->append($idField)
            ->distinct()
            ->filter(function (string $field) use ($map) {
                return array_key_exists($field, $map);
            })
            ->replace($map)
            ->interpose(',')
            ->toString();
    }

    public static function dataLoaderResponseFromGrouped(Collection $keys, Collection $grouped): array
    {
        return $keys->map(function ($key) use ($grouped) {
            return $grouped->has($key) ? $grouped->get($key) : [];
        })->toArray();
    }
}