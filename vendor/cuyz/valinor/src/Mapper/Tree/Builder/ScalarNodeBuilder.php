<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Mapper\Tree\Builder;

use CuyZ\Valinor\Mapper\Configurator\MapAsBool;
use CuyZ\Valinor\Mapper\Configurator\MapAsFloat;
use CuyZ\Valinor\Mapper\Configurator\MapAsInt;
use CuyZ\Valinor\Mapper\Configurator\MapAsString;
use CuyZ\Valinor\Mapper\Tree\Shell;
use CuyZ\Valinor\Type\BooleanType;
use CuyZ\Valinor\Type\FloatType;
use CuyZ\Valinor\Type\IntegerType;
use CuyZ\Valinor\Type\ScalarType;

use function assert;
use function is_int;

/** @internal */
final class ScalarNodeBuilder implements NodeBuilder
{
    public function build(Shell $shell): Node
    {
        assert($shell->type instanceof ScalarType);

        $value = $shell->value();

        
        
        
        
        if ($shell->type instanceof FloatType && is_int($value)) {
            $value = (float)$value;
        }

        if ($shell->type->accepts($value)) {
            return $shell->node($value);
        }

        $converter = match (true) {
            $shell->allowCastingToBoolean !== [] && $shell->type instanceof BooleanType => static fn ($value) => MapAsBool::convert($value, $shell->allowCastingToBoolean['true'], $shell->allowCastingToBoolean['false']),
            $shell->allowCastingToInteger && $shell->type instanceof IntegerType => MapAsInt::convert(...),
            $shell->allowCastingToFloat && $shell->type instanceof FloatType => MapAsFloat::convert(...),
            $shell->allowCastingToString => MapAsString::convert(...),
            default => null,
        };

        if ($converter) {
            $newValue = $converter($value);

            if ($shell->type->accepts($newValue)) {
                return $shell->node($newValue);
            }
        }

        return $shell->error($shell->type->errorMessage());
    }
}
