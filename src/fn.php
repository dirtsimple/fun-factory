<?php
namespace dirtsimple;

class fn extends fun\Factory {

	function __construct() {
		throw new \BadMethodCallException(static::class . " is not instantiable");
	}

	static function _() {
		static $factory, $cache=array();
		$ct = func_num_args();
		if ( $ct === 1 ) {
			$fn = func_get_arg(0);
			if ( $fn instanceof fun\Factory ) return $fn;
			if ( is_string($fn) && array_key_exists($fn, $cache) ) return $cache[$fn];
			# ... else fall through to composing a new factory
		} elseif ( $ct === 0 ) {
			return $factory = $factory ?: new fun\Factory(array());
		}

		$ops = array();
		foreach (func_get_args() as $fn) {
			if (is_string($fn) && ! static::is_callable_name($fn)) {
				if ( $ct === 1 ) return $cache[$fn] = static::expr($fn);
				$fn = static::expr($fn);
			}
			if ($fn instanceof fun\Factory) {
				if ($fn = $fn->ops) array_push($ops, ...$fn);
				continue;
			} elseif (is_array($fn)) {
				if (count($fn)==3) {
					array_push($ops, $fn);
					continue;
				} elseif (count($fn)==1) {
					$fn = $fn[0];
				}
			}
			if ( is_callable($fn, true) ) array_push($ops, [$fn, null, null]);
			else throw new \BadFunctionCallException("$fn is not callable");
		}
		$fn = new fun\Factory($ops);
		if ( $ct === 1 && is_string($key = func_get_arg(0)) ) $cache[$key] = $fn;
		return $fn;
	}

	static function bind($callable, ...$args) {
		if (is_callable($callable)) return fn()->andThen($callable, $args);
		else throw new \BadFunctionCallException("$callable is not callable");
	}

	static function is_callable_name($fn) {
		return (bool) preg_match('/^(\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)+(::[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)?$/', $fn);
	}

	static $exprs = [];

	static function expr($expr) {
		return array_key_exists($expr, static::$exprs)
			? static::$exprs[$expr]
			: static::$exprs[$expr] = (
				$expr == '$_' ? fn() : fn()->andThen(eval("return function(\$_) { return $expr; };"))
			);
	}

	static function transform($schema, $input=null, $out=array(), $reducer=null){
		$reducer = $reducer ?: function($out, $key, $fn, $in) { $out[$key] = $fn($in); return $out; };
		foreach ($schema as $key => $fn) $out = $reducer($out, $key, $fn, $input);
		return $out;
	}

	static function schema($schema){
		return function(...$args) use($schema) { return static::transform($schema, ...$args); };
	}

	static function val($value) {
		return fn()->andThen(fun\Factory::OP_VAL, $value);
	}

	static function tap(...$callables) {
		return fn()->andThen(fun\Factory::OP_TAP, fn(...$callables));
	}

	static function when($cond, $ifTrue, $ifFalse='$_') {
		return fn()->andThen(fun\Factory::OP_COND, fn($cond), [fn($ifTrue), fn($ifFalse)]);
	}

	static function unless($cond, $ifFalse, $ifTrue='$_') {
		return fn()->andThen(fun\Factory::OP_COND, fn($cond), [fn($ifTrue), fn($ifFalse)]);
	}
}