<?php
namespace dirtsimple;

class fn extends fun\Factory {

	function __construct() {
		throw new \BadMethodCallException(static::class . " is not instantiable");
	}

	static function compose() {
		$ops = [];
		$args = func_num_args() > 1 ? array_reverse(func_get_args()) : func_get_args();
		foreach ($args as $fn) {
			if (is_string($fn) && ! static::is_callable_name($fn)) {
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
		return new fun\Factory($ops);
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