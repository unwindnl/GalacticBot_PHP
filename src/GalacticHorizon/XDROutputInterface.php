<?php

namespace GalacticHorizon;

interface XDROutputInterface {

public function toXDRBuffer(XDRBuffer &$buffer);

public function hasValue();

}

