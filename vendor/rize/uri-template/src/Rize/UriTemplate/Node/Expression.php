<?php

namespace Rize\UriTemplate\Node;

use Rize\UriTemplate\Operator;
use Rize\UriTemplate\Parser;

class Expression extends Abstraction
{
    /**
     * @param string $forwardLookupSeparator
     */
    public function __construct(string $token, private readonly Operator\Abstraction $operator, private readonly ?array $variables = null, /**
     * Whether to do a forward lookup for a given separator.
     */
        private $forwardLookupSeparator = null)
    {
        parent::__construct($token);
    }

    public function getOperator(): Operator\Abstraction
    {
        return $this->operator;
    }

    public function getVariables(): ?array
    {
        return $this->variables;
    }

    public function getForwardLookupSeparator(): string
    {
        return $this->forwardLookupSeparator;
    }

    public function setForwardLookupSeparator(string $forwardLookupSeparator): void
    {
        $this->forwardLookupSeparator = $forwardLookupSeparator;
    }

    public function expand(Parser $parser, array $params = []): ?string
    {
        $data = [];
        $op = $this->operator;

        if ($this->variables === null) {
            return $op->first;
        }

        
        foreach ($this->variables as $var) {
            $val = $op->expand($parser, $var, $params);

            
            if (!is_null($val)) {
                $data[] = $val;
            }
        }

        return $data ? $op->first . implode($op->sep, $data) : null;
    }

    /**
     * Matches given URI against current node.
     *
     * @return null|array `uri and params` or `null` if not match and $strict is true
     */
    public function match(Parser $parser, string $uri, array $params = [], bool $strict = false): ?array
    {
        $op = $this->operator;

        
        if ($op->id && isset($uri[0]) && $uri[0] !== $op->id) {
            return [$uri, $params];
        }

        
        if ($op->id) {
            $uri = substr($uri, 1);
        }

        foreach ($this->sortVariables($this->variables) as $var) {
            $regex = '#' . $op->toRegex($parser, $var) . '#';
            $val   = null;

            
            $remainingUri = '';
            $preparedUri = $uri;
            if ($this->forwardLookupSeparator) {
                $lastOccurrenceOfSeparator = stripos($uri, $this->forwardLookupSeparator);
                $preparedUri = substr($uri, 0, $lastOccurrenceOfSeparator);
                $remainingUri = substr($uri, $lastOccurrenceOfSeparator);
            }

            if (preg_match($regex, $preparedUri, $match)) {
                
                $preparedUri = preg_replace($regex, '', $preparedUri, 1);
                $val = $op->extract($parser, $var, $match[0]);
            }

            
            elseif ($strict) {
                return null;
            }

            $uri = $preparedUri . $remainingUri;

            $params[$var->getToken()] = $val;
        }

        return [$uri, $params];
    }

    /**
     * Sort variables before extracting data from uri.
     * We have to sort vars by non-explode to explode.
     */
    protected function sortVariables(array $vars): array
    {
        usort($vars, static fn($a, $b) => $a->options['modifier'] <=> $b->options['modifier']);

        return $vars;
    }
}
