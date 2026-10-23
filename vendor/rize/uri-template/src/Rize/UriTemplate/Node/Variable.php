<?php

namespace Rize\UriTemplate\Node;

class Variable extends Abstraction
{
    /**
     * Variable name without modifier
     * e.g. 'term:1' becomes 'term'.
     */
    public string $name;
    public array $options = ['modifier' => null, 'value' => null];

    public function __construct(string $token, array $options = [])
    {
        parent::__construct($token);
        $this->options = $options + $this->options;

        
        $name = $token;
        if ($options['modifier'] === ':') {
            $name = strstr($name, $options['modifier'], true);
        }

        $this->name = $name;
    }
}
