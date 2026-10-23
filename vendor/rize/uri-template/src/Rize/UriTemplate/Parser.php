<?php

namespace Rize\UriTemplate;

use Rize\UriTemplate\Node\Abstraction;
use Rize\UriTemplate\Node\Expression;
use Rize\UriTemplate\Node\Variable;
use Rize\UriTemplate\Operator\UnNamed;

class Parser
{
    private const REGEX_VARNAME = '[A-z0-9.]|%[0-9a-fA-F]{2}';

    /**
     * Parses URI Template and returns nodes.
     *
     * @return Node\Abstraction[]
     */
    public function parse(string $template): array
    {
        $parts = preg_split('#(\{[^}]+})#', $template, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $nodes = [];

        foreach ($parts as $part) {
            $node = $this->createNode($part);

            
            
            if ($node instanceof Expression && $node->getOperator()->id === '.') {
                if (count($nodes) > 0) {
                    $previousNode = $nodes[count($nodes) - 1];
                    if ($previousNode instanceof Expression && $previousNode->getOperator() instanceof UnNamed) {
                        $previousNode->setForwardLookupSeparator($node->getOperator()->id);
                    }
                }
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    protected function createNode(string $token): Abstraction
    {
        
        if ($token[0] !== '{') {
            $node = $this->createLiteralNode($token);
        } else {
            
            $node = $this->parseExpression(substr($token, 1, -1));
        }

        return $node;
    }

    protected function parseExpression(string $expression): Expression
    {
        $token  = $expression;
        $prefix = $token[0];

        
        if (!Operator\Abstraction::isValid($prefix)) {
            
            if (!preg_match('#' . self::REGEX_VARNAME . '#', $token)) {
                throw new \InvalidArgumentException("Invalid operator [{$prefix}] found at {$token}");
            }

            
            
            
            $prefix = '';
        }

        
        if ($prefix) {
            $token = substr($token, 1);
        }

        
        $vars = [];
        foreach (explode(',', $token) as $var) {
            $vars[] = $this->parseVariable($var);
        }

        return $this->createExpressionNode(
            $token,
            $this->createOperatorNode($prefix),
            $vars,
        );
    }

    protected function parseVariable(string $var): Variable
    {
        $var      = trim($var);
        $val      = null;
        $modifier = null;

        
        if (str_contains($var, ':')) {
            $modifier = ':';
            [$varname, $val] = explode(':', $var);

            
            if (!is_numeric($val)) {
                throw new \InvalidArgumentException("Value for `:` modifier must be numeric value [{$varname}:{$val}]");
            }
        }

        switch ($last = substr($var, -1)) {
            case '*':
            case '%':
                
                if ($modifier) {
                    throw new \InvalidArgumentException("Multiple modifiers per variable are not allowed [{$var}]");
                }

                $modifier = $last;
                $var = substr($var, 0, -1);

                break;
        }

        return $this->createVariableNode(
            $var,
            ['modifier' => $modifier, 'value' => $val],
        );
    }

    protected function createVariableNode($token, $options = []): Variable
    {
        return new Variable($token, $options);
    }

    protected function createExpressionNode($token, ?Operator\Abstraction $operator = null, array $vars = []): Expression
    {
        return new Expression($token, $operator, $vars);
    }

    protected function createLiteralNode(string $token): Node\Literal
    {
        return new Node\Literal($token);
    }

    protected function createOperatorNode($token): Operator\Abstraction
    {
        return Operator\Abstraction::createById($token);
    }
}
