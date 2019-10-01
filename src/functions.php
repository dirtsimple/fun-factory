<?php

namespace dirtsimple;

function fun(...$args) {
	if ( count($args) >1 ) $args = array_reverse($args);
	return fun::_(...$args);
}