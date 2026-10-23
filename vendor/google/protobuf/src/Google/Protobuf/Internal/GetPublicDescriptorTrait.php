<?php


// Copyright 2017 Google Inc.  All rights reserved.


// license that can be found in the LICENSE file or at
// https://developers.google.com/open-source/licenses/bsd

namespace Google\Protobuf\Internal;

trait GetPublicDescriptorTrait
{
    private function getPublicDescriptor($desc)
    {
        return is_null($desc) ? null : $desc->getPublicDescriptor();
    }
}
