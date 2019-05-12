<?php

namespace dirtsimple;

function fn(...$args) {
	if ( count($args) >1 ) $args = array_reverse($args);
	return fn::_(...$args);
}