<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models\Concerns;

use App\Log;
use BadMethodCallException;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use LogicException;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionException;
use ReflectionMethod;

trait ChecksRelations
{
    /**
     * @param string $attribute
     * @return bool
     */
    public static function isRelation(string $attribute): bool
    {
        try {
            return (bool) self::getRelationType($attribute);
        } catch (BadMethodCallException $e) {
            return false;
        } catch (LogicException $e) {
            return false;
        }
    }

    /**
     * @param string $attribute
     * @return null|string
     * @throws ReflectionException
     */
    public static function getRelationType(string $attribute): ?string
    {
        Log::debug('ChecksRelations - getRelationType()');
        // Make sure the method exists
        if (!method_exists(get_called_class(), $attribute)) {
            $errorMessage = sprintf('Method does not exist: %s->%s', get_called_class(), $attribute);
            Log::info('Throwing excepiton: ' . $errorMessage);
            throw new BadMethodCallException($errorMessage);
        }

        $method = new ReflectionMethod(get_called_class(), $attribute);

        // First see if the return type is in the code
        if ($method->getReturnType()) {
            return $method->getReturnType()->getName();
        }

        // Not in code, let's check the phpdoc
        try {
            $docBlock = DocBlockFactory::createInstance()->create($method);
        } catch (InvalidArgumentException $exception) {
            // DocBlock error
            $errorMessage = sprintf(
                'Unable to magically load return type for: %s->%s',
                get_called_class(),
                $attribute
            );
            Log::info('Throwing exception: ' . $errorMessage);
            throw new LogicException($errorMessage);
        }

        /** @var Return_ $returnTag */
        $returnTag = current($docBlock->getTagsByName('return'));
        if ($returnTag) {
            // Found @return tag in docblock
            $returnType = (string) $returnTag->getType();
            if (is_subclass_of($returnType, Relation::class)) {
                // Removing leading back slash
                $returnType = trim($returnType, "\\");
                return $returnType;
            }
        }

        // Not in code or docblock. Throw exception so it can be fixed.
        $errorMessage = sprintf(
            'Unable to magically load return type for: %s->%s',
            get_called_class(),
            $attribute
        );
        Log::info('Throwing excepiton: ' . $errorMessage);
        throw new LogicException($errorMessage);
    }
}
