<?php

namespace Rize\UriTemplate\Operator;

use Rize\UriTemplate\Node;
use Rize\UriTemplate\Parser;

/**
 * | 1   |    {?list}    ?list=red,green,blue                 | {name}=(?:\w+(?:,\w+?)*)*
 * | 2   |    {?list*}   ?list=red&list=green&list=blue       | {name}+=(?:{$value}+(?:{sep}{name}+={$value}*))*
 * | 3   |    {?keys}    ?keys=semi,%3B,dot,.,comma,%2C       | (same as 1)
 * | 4   |    {?keys*}   ?semi=%3B&dot=.&comma=%2C            | (same as 2)
 * | 5   |    {?list*}   ?list[]=red&list[]=green&list[]=blue | {name[]}+=(?:{$value}+(?:{sep}{name[]}+={$value}*))*.
 */
class Named extends Abstraction
{
    public function toRegex(Parser $parser, Node\Variable $var): string
    {
        $name    = $var->name;
        $value   = $this->getRegex();
        $options = $var->options;

        if ($options['modifier']) {
            switch ($options['modifier']) {
                case '*':
                    
                    $regex = "{$name}+=(?:{$value}+(?:{$this->sep}{$name}+={$value}*)*)"
                           . "|{$value}+=(?:{$value}+(?:{$this->sep}{$value}+={$value}*)*)";

                    break;

                case ':':
                    $regex = "{$value}\\{0,{$options['value']}\\}";

                    break;

                case '%':
                    
                    $name .= '+(?:%5B|\[)[^=]*=';
                    $regex = "{$name}(?:{$value}+(?:{$this->sep}{$name}{$value}*)*)";

                    break;

                default:
                    throw new \InvalidArgumentException("Unknown modifier `{$options['modifier']}`");
            }
        } else {
            
            $regex = "{$name}=(?:{$value}+(?:,{$value}+)*)*";
        }

        return '(?:&)?' . $regex;
    }

    public function expandString(Parser $parser, Node\Variable $var, $val): string
    {
        $val     = (string) $val;
        $options = $var->options;
        $result  = $this->encode($parser, $var, $var->name);

        
        if ($val === '') {
            return $result . $this->empty;
        }

        $result .= '=';

        if ($options['modifier'] === ':') {
            $val = mb_substr($val, 0, (int) $options['value']);
        }

        return $result . $this->encode($parser, $var, $val);
    }

    public function expandNonExplode(Parser $parser, Node\Variable $var, array $val): ?string
    {
        if (empty($val)) {
            return null;
        }

        $result = $this->encode($parser, $var, $var->name);

        $result .= '=';

        return $result . $this->encode($parser, $var, $val);
    }

    public function expandExplode(Parser $parser, Node\Variable $var, array $val): ?string
    {
        if (empty($val)) {
            return null;
        }

        $list = isset($val[0]);
        $data = [];
        foreach ($val as $k => $v) {
            
            $key = $list ? $var->name : $k;
            if ($list) {
                $data[$key][] = $v;
            } else {
                $data[$key] = $v;
            }
        }

        
        
        
        if (!$list && $var->options['modifier'] === '%') {
            $data = [$var->name => $data];
        }

        return $this->encodeExplodeVars($var, $data);
    }

    public function extract(Parser $parser, Node\Variable $var, $data): array|string
    {
        
        if ($data[0] === '&') {
            $data = substr($data, 1);
        }

        $value   = $data;
        $vals    = explode($this->sep, $data);
        $options = $var->options;

        switch ($options['modifier']) {
            case '%':
                parse_str($value, $query);

                return $query[$var->name];

            case '*':
                $value = [];

                foreach ($vals as $val) {
                    [$k, $v] = explode('=', $val);

                    
                    if ($k === $var->getToken()) {
                        $value[] = $v;
                    }

                    
                    else {
                        $value[$k] = $v;
                    }
                }

                break;

            case ':':
                break;

            default:
                
                
                $value = str_replace($var->getToken() . '=', '', $value);
                $value = explode(',', $value);

                if (count($value) === 1) {
                    $value = current($value);
                }
        }

        return $this->decode($parser, $var, $value);
    }

    public function encodeExplodeVars(Node\Variable $var, $data): null|array|string
    {
        
        
        
        $query = http_build_query($data, '', $this->sep);
        $query = str_replace('+', '%20', $query);

        
        if ($var->options['modifier'] === '%') {

            
            $query = preg_replace('#%5B\d+%5D#', '%5B%5D', $query);
        }

        
        else {
            
            
            $query = preg_replace('#%5B\d+%5D#', '', $query);
        }

        
        if ($this->reserved) {
            $query = str_replace(
                array_keys(static::$reserved_chars),
                static::$reserved_chars,
                $query,
            );
        }

        return $query;
    }
}
