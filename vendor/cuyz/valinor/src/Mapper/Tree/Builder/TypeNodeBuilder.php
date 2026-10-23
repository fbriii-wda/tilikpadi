<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Mapper\Tree\Builder;

use CuyZ\Valinor\Mapper\Tree\Shell;
use CuyZ\Valinor\Type\Types\ArrayType;
use CuyZ\Valinor\Type\Types\EnumType;
use CuyZ\Valinor\Type\Types\InterfaceType;
use CuyZ\Valinor\Type\Types\IterableType;
use CuyZ\Valinor\Type\Types\ListType;
use CuyZ\Valinor\Type\Types\MixedType;
use CuyZ\Valinor\Type\Types\NativeClassType;
use CuyZ\Valinor\Type\Types\NonEmptyArrayType;
use CuyZ\Valinor\Type\Types\NonEmptyListType;
use CuyZ\Valinor\Type\Types\NullType;
use CuyZ\Valinor\Type\Types\ShapedArrayType;
use CuyZ\Valinor\Type\Types\ShapedListType;
use CuyZ\Valinor\Type\Types\UndefinedObjectType;
use CuyZ\Valinor\Type\Types\UnionType;

/** @internal */
final class TypeNodeBuilder implements NodeBuilder
{
    public function __construct(
        private ArrayNodeBuilder $arrayNodeBuilder,
        private ListNodeBuilder $listNodeBuilder,
        private ScalarNodeBuilder $scalarNodeBuilder,
        private UnionNodeBuilder $unionNodeBuilder,
        private NullNodeBuilder $nullNodeBuilder,
        private MixedNodeBuilder $mixedNodeBuilder,
        private UndefinedObjectNodeBuilder $undefinedObjectNodeBuilder,
        private KeyConverterNodeBuilder $shapedArrayNodeBuilder,
        private ObjectNodeBuilder $objectNodeBuilder,
    ) {}

    public function build(Shell $shell): Node
    {
        $builder = match ($shell->type::class) {
            
            ListType::class,
            NonEmptyListType::class => $this->listNodeBuilder,

            
            ArrayType::class,
            NonEmptyArrayType::class,
            IterableType::class => $this->arrayNodeBuilder,

            
            ShapedArrayType::class,
            ShapedListType::class => $this->shapedArrayNodeBuilder,

            
            UnionType::class => $this->unionNodeBuilder,

            
            NullType::class => $this->nullNodeBuilder,

            
            MixedType::class => $this->mixedNodeBuilder,

            
            UndefinedObjectType::class => $this->undefinedObjectNodeBuilder,

            
            NativeClassType::class,
            EnumType::class,
            InterfaceType::class => $this->objectNodeBuilder,

            
            default => $this->scalarNodeBuilder,
        };

        return $builder->build($shell);
    }
}
