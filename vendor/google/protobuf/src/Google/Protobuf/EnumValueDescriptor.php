<?php


// Copyright 2008 Google Inc.  All rights reserved.


// license that can be found in the LICENSE file or at
// https://developers.google.com/open-source/licenses/bsd

namespace Google\Protobuf;

class EnumValueDescriptor
{
    private $internal_desc;

    /**
     * @internal
     */
    public function __construct($internal_desc)
    {
        $this->internal_desc = $internal_desc;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->internal_desc->getName();
    }

    /**
     * @return int
     */
    public function getNumber()
    {
        return $this->internal_desc->getNumber();
    }
}
