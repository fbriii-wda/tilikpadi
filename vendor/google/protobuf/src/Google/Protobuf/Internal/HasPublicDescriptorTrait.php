<?php


// Copyright 2008 Google Inc.  All rights reserved.


// license that can be found in the LICENSE file or at
// https://developers.google.com/open-source/licenses/bsd

namespace Google\Protobuf\Internal;

trait HasPublicDescriptorTrait
{
    private $public_desc;

    public function getPublicDescriptor()
    {
        return $this->public_desc;
    }
}
