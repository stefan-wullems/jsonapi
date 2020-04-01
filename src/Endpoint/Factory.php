<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint;

use Proglum\JsonApi\Endpoint\Contracts\Endpoint;
use Proglum\JsonApi\Models\Model;
use Illuminate\Support\Str;
use DirectoryIterator;
use RuntimeException;
use Throwable;

class Factory
{
    /**
     * @param string $version
     * @param string $suffix
     * @return Endpoint|null
     */
    public static function trySuffix(string $version, string $suffix): ?Endpoint
    {
        // Find endpoint based on table name
        $className = __NAMESPACE__ . '\\' . $version . '\\' . $suffix;
        if (!class_exists($className)) {
            return null;
        }

        try {
            $endpoint = app()->make($className);
        } catch (Throwable $exception) {
            return null;
        }

        // This check is here because on Linux and in PHARs, filenames are case sensitive.
        if (get_class($endpoint) !== $className) {
            return null;
        }

        return $endpoint;
    }

    /**
     * @param string $version Including the leading 'v'
     * @param Model $relatedModel
     * @return RestEndpoint
     */
    public static function create(string $version, Model $relatedModel): Endpoint
    {
        // Find endpoint based on table name
        if ($endpoint = self::trySuffix($version, Str::studly($relatedModel->getTable()))) {
            return $endpoint;
        }

        // Find endpoint based on resource name
        if ($endpoint = self::trySuffix($version, ucfirst($relatedModel->resourceName()))) {
            return $endpoint;
        }

        // Find endpoint by iterating all classes in the $version directory
        foreach (new DirectoryIterator(__DIR__ . '/' . $version) as $fileInfo) {
            if ($fileInfo->isDot() || $fileInfo->isDir() || strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $baseName = $fileInfo->getBasename('.php');
            if ($endpoint = self::trySuffix($version, $baseName)) {
                // If endopint is a RestEndpoint, and the static property $model matches our related model,
                // we have a winner!
                if ($endpoint instanceof RestEndpoint && $endpoint->model() === get_class($relatedModel)) {
                    return $endpoint;
                }
            }
        }

        throw new RuntimeException("Could not find endpoint for " . get_class($relatedModel));
    }
}
