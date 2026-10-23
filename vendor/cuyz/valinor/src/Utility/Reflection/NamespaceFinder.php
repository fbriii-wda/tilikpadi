<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Utility\Reflection;

use PhpToken;

use function count;

/** @internal */
final class NamespaceFinder
{
    public function findNamespace(string $content): ?string
    {
        $tokens = PhpToken::tokenize($content);
        $tokensCount = count($tokens);
        
        $pointer = $tokensCount - 1;

        while (! $tokens[$pointer]->is(T_NAMESPACE)) {
            
            if ($pointer === 0) {
                return null;
            }

            $pointer--;
        }

        while (! $tokens[$pointer]->is([T_NAME_QUALIFIED, T_STRING])) {
            $pointer++;
        }

        return (string)$tokens[$pointer];
    }
}
