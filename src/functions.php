<?php

namespace dirtsimple;

function fn() {
	static $factory, $cache=[];
	$ct = func_num_args();
	if ( $ct === 1 ) {
		$fn = func_get_arg(0);
		if ( $fn instanceof fun\Factory ) return $fn;
		if ( is_string($fn) ) return array_key_exists($fn, $cache) ? $cache[$fn] : $cache[$fn] = fn::compose($fn);
	} elseif ( $ct === 0 ) {
		return $factory = $factory ?: fn::compose();
	}
	return fn::compose(...func_get_args()); 
}