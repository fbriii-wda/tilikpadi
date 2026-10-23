<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Mapper\Tree\Builder;

use CuyZ\Valinor\Mapper\Http\HttpRequest;
use CuyZ\Valinor\Mapper\Tree\Exception\SourceMustBeIterable;
use CuyZ\Valinor\Mapper\Tree\Exception\UnexpectedKeyInSource;
use CuyZ\Valinor\Mapper\Tree\Shell;
use CuyZ\Valinor\Type\CompositeTraversableType;
use CuyZ\Valinor\Type\Types\MixedType;
use CuyZ\Valinor\Type\Types\ShapedArrayType;
use CuyZ\Valinor\Type\Types\ShapedListType;
use CuyZ\Valinor\Type\Types\UnresolvableType;
use CuyZ\Valinor\Type\VacantType;
use CuyZ\Valinor\Utility\Polyfill;

use function array_diff_key;
use function array_key_exists;
use function assert;
use function count;
use function is_array;
use function is_int;
use function is_iterable;
use function iterator_to_array;

/** @internal */
final class ShapedArrayNodeBuilder implements NodeBuilder
{
    public function __construct(
        private HttpRequestNodeBuilder $httpRequestNodeBuilder,
    ) {}

    public function build(Shell $shell): Node
    {
        $value = $shell->value();

        if ($value instanceof HttpRequest && $shell->type instanceof ShapedArrayType) {
            return $this->httpRequestNodeBuilder->build($shell);
        }

        if (! is_array($value) && is_iterable($value)) {
            $value = iterator_to_array($value);
            $shell = $shell->withValue($value);
        }

        
        
        
        if ($value === null && $shell->allowUndefinedValues) {
            $value = [];
            $shell = $shell->withValue($value);
        }

        if ($shell->wrapSingleValueIfNeeded) {
            $shell = $this->wrapSingleValueIfNeeded($shell);
            $value = $shell->value();
        }

        $type = $shell->type;

        assert($type instanceof ShapedArrayType || $type instanceof ShapedListType);

        if (! is_array($value)) {
            return $shell->error(new SourceMustBeIterable($value));
        }

        $children = [];
        $errors = [];
        $absentOptionalElements = 0;

        
        
        foreach ($type->elements as $key => $element) {
            $hasValue = array_key_exists($key, $value);

            if (! $hasValue && $element->isOptional()) {
                $absentOptionalElements++;
                continue;
            }

            $child = $shell
                ->child((string)$key, $element->type())
                ->withAttributes($element->attributes());

            if ($hasValue) {
                $child = $child->withValue($value[$key]);
            }

            $child = $child->build();

            if ($child->isValid()) {
                $children[$key] = $child->value();
            } else {
                $errors[] = $child;
            }

            unset($value[$key]);
        }

        if ($value === []) {
            $shell->childrenCount += $absentOptionalElements;
        }

        
        
        if ($type->isUnsealed()) {
            if ($type instanceof ShapedListType) {
                $unsealedType = $type->unsealedType();
                $elementType = $unsealedType instanceof VacantType
                    ? MixedType::get()
                    : $unsealedType->subType();
                $expectedKey = count($type->elements);

                foreach ($value as $key => $val) {
                    if (! is_int($key) || $key !== $expectedKey) {
                        $errors[] = $shell
                            ->child((string)$key, UnresolvableType::forSuperfluousValue())
                            ->withValue($val)
                            ->error(new UnexpectedKeyInSource());
                        continue;
                    }

                    $expectedKey++;

                    $child = $shell
                        ->child((string)$key, $elementType)
                        ->withValue($val)
                        ->build();

                    if ($child->isValid()) {
                        $children[$key] = $child->value();
                    } else {
                        $errors[] = $child;
                    }
                }
            } else {
                $unsealedNode = $shell
                    ->withType($type->unsealedType())
                    ->withValue($value)
                    ->build();

                if ($unsealedNode->isValid()) {
                    // @phpstan-ignore assignOp.invalid (we know value is an array)
                    $children += $unsealedNode->value();
                } else {
                    $errors[] = $unsealedNode;
                }
            }
        } elseif (! $shell->allowSuperfluousKeys) {
            
            
            $diff = array_diff_key($value, $children, $shell->allowedSuperfluousKeys);

            foreach ($diff as $key => $val) {
                $errors[] = $shell->child((string)$key, UnresolvableType::forSuperfluousValue())->withValue($val)->error(new UnexpectedKeyInSource());
            }
        }

        if ($errors === []) {
            return $shell->node($children);
        }

        return $shell->errors($errors);
    }

    private function wrapSingleValueIfNeeded(Shell $shell): Shell
    {
        assert($shell->type instanceof ShapedArrayType);

        if (count($shell->type->elements) !== 1) {
            return $shell;
        }

        $value = $shell->value();
        $element = Polyfill::array_first($shell->type->elements);
        $key = $element->key()->value();
        $elementIsTraversable = $element->type() instanceof CompositeTraversableType;

        
        
        
        
        
        $alreadyShaped = is_array($value)
            && array_key_exists($key, $value)
            && (! $elementIsTraversable || $shell->allowSuperfluousKeys || count($value) === 1);

        
        
        $emptyNonTraversable = $value === [] && ! $elementIsTraversable;

        if ($alreadyShaped || $emptyNonTraversable) {
            return $shell;
        }

        
        
        
        
        
        return $shell
            ->withValue([$key => $value])
            ->withPathMap(["$shell->path.$key" => $shell->path]);
    }
}
