<?php
namespace dirtsimple\fun\test;

use function dirtsimple\fn;
use dirtsimple\fn;
use \Mockery;

function tearDown($func) {
	return function() use($func) {
		try { return $func(); } 
		finally { \Brain\Monkey\tearDown(); }
	};
}

describe("fn", function() {
	it('() => ($x) => $x', function() {
		$f = fn(); expect($f(42))->to->equal(42);
	});
	it("() => the same callable each time", function() {
		$f = fn();
		expect(fn())->to->equal($f);
	});
	it("()->method(args...) => applier of method(args...)", tearDown(function() {
		$fn = fn()->x(99);
		$mock = Mockery::mock();
		$mock->shouldReceive('x')->with(99)->once()->andReturn("foo");
		expect($fn($mock))->to->equal("foo");
	}));
	it("()->property => getter of property", function() {
		$fn = fn()->foo;
		$data = (object) ['foo'=>42];
		expect($fn($data))->to->equal(42);
	});
	it('($string) => the same object each time', function(){
		$f = fn("is_array"); expect(fn("is_array"))->to->equal($f);
		expect($f([]))->to->be->true;
		expect($f(42))->to->be->false;
	});
	it('($expr) => callable that evaluates $expr w/$_ as parameter', function() {
		$f = fn('is_array($_)');
		expect($f([]))->to->be->true;
		expect($f("blah"))->to->be->false;
	});
	it('($factory) => $factory', function() {
		$f = fn(); expect(fn($f))->to->equal($f);
	});
	it('()[$key] => item-getter of key', function() {
		$fn = fn()[1];
		$data = ['foo','bar'];
		expect($fn($data))->to->equal('bar');
	});
	it("performs actions in their apparent order", tearDown(function(){
		$f = fn()->qq(3,4)->foo['bar'];
		$mock = Mockery::mock();
		$mock->shouldReceive('qq')->with(3,4)->once()->andReturn(
			(object) ['foo'=>['bar'=>23]]
		);
		expect($f($mock))->to->equal(23);
	}));
	it('composes multiple arguments', function() {
		$f = fn('array_flip','array_reverse');
		expect($f(['foo','bar']))->to->equal(['bar'=>0,'foo'=>1]);
	});
	it("can't be instantiated with new", function() {
		expect(function(){ new fn; })->to->throw(\BadMethodCallException::class, fn::class . " is not instantiable");
	});

	describe("ArrayAccess polymorphism:", function(){
		it("[] calls ->offsetGet() on objects", tearDown(function(){
			$f = fn()->offsetGet('foo');
			$mock = Mockery::mock(\ArrayAccess::class);
			$mock->shouldReceive('offsetGet')->with('foo')->once()->andReturn(42);
			expect($f($mock))->to->equal(42);
		}));
		it("offsetSet() assigns array subscripts", function(){
			$f = fn()->offsetSet('foo', 42);
			expect($f(['bar'=>99]))->to->equal(['bar'=>99,'foo'=>42]);
		});
		it("offsetSet() calls ->offsetSet() on objects", tearDown(function(){
			$f = fn()->offsetSet('foo', 'bar');
			$mock = Mockery::mock(\ArrayAccess::class);
			$mock->shouldReceive('offsetSet')->with('foo','bar')->once();
			expect($f($mock))->to->equal($mock);
		}));
		it("offsetUnset() unsets array subscripts", function(){
			$f = fn()->offsetUnset('foo');
			expect($f(['bar'=>99,'foo'=>42]))->to->equal(['bar'=>99]);
		});
		it("offsetUnset() calls ->offsetUnset() on objects", tearDown(function(){
			$f = fn()->offsetUnset('foo');
			$mock = Mockery::mock(\ArrayAccess::class);
			$mock->shouldReceive('offsetUnset')->with('foo')->once();
			expect($f($mock))->to->equal($mock);
		}));
		it("offsetExists() uses array_key_exists() on arrays", function(){
			$f = fn()->offsetExists('foo');
			expect($f(['foo'=>42]))->to->be->true;
			expect($f(['bar'=>99]))->to->be->false;
		});
		it("offsetExists() calls ->offsetExists() on objects", tearDown(function(){
			$f = fn()->offsetExists('foo');
			$mock = Mockery::mock(\ArrayAccess::class);
			$mock->shouldReceive('offsetExists')->with('foo')->once()->andReturn(true);
			expect($f($mock))->to->be->true;
		}));
	});

});

describe('fn::bind($callable, ...$args)', function() {
	it('returns a callable that receives $args before the arg', tearDown(function(){
		$mock = Mockery::mock();
		$mock->shouldReceive('testMe')->with('blue', 32, 'bar')->once()->andReturn("foo");
		$f = fn::bind([$mock, 'testMe'], 'blue', 32);
		expect($f('bar'))->to->equal('foo');
	}));
});

describe('fn::is_callable_name($string)', function() {
	it("accepts plain or namespaced functions or static methods", function() {
		expect(fn::is_callable_name('foo_bar'))->to->be->true;
		expect(fn::is_callable_name('foo_bar::baz'))->to->be->true;
		expect(fn::is_callable_name('a\bc'))->to->be->true;
		expect(fn::is_callable_name('\a\b::c_d0'))->to->be->true;
	});
	it("rejects strings w/any non-identifier chars", function() {
		expect(fn::is_callable_name('foo bar'))->to->be->false;
		expect(fn::is_callable_name('foo(bar)'))->to->be->false;
		expect(fn::is_callable_name('$_'))->to->be->false;
	});
	it("rejects strings w/invalid separators", function() {
		expect(fn::is_callable_name('foo\\\\bar'))->to->be->false;
		expect(fn::is_callable_name('foo::bar::baz'))->to->be->false;
		expect(fn::is_callable_name('baz:::spam'))->to->be->false;
	});
});

describe('fn::expr($string)', function() {
	it('returns the identity instance for $_', function() {
		expect(fn::expr('$_'))->to->equal(fn());
	});
	it('passes its argument to the expression as $_', function() {
		$f = fn::expr('is_array($_)');
		expect($f([]))->to->be->true;
		expect($f("blah"))->to->be->false;
	});
	it('returns the same instance for the same string', function() {
		$f = fn::expr('is_array($_)');
		expect(fn::expr('is_array($_)'))->to->equal($f);
	});
});

describe('fn::tap(...$callables)', function() {
	it('returns a function that applies $callables as a side-effect', tearDown(function() {
		$mock = Mockery::mock();
		$f = fn::tap([$mock, 'method'], '$_*2');
		$mock->shouldReceive('method')->once()->with(84)->andReturn(43);
		expect($f(42))->to->equal(42);
	}));
});

describe('fn::val($value)', function() {
	it('returns a callable that returns $value', function() {
		$f = fn::val(42);
		expect($f(27))->to->equal(42);
	});
});

describe('fn::when($cond, $ifTrue, $ifFalse=\'$_\')', function() {
	it('returns a callable for $cond($_) ? $ifTrue($_) : $ifFalse($_)', function() {
		$f = fn::when('$_==1', '$_*2', '$_+4');
		expect($f(1))->to->equal(2);
		expect($f(2))->to->equal(6);
		$f = fn::when('$_==2', '$_*3');
		expect($f(1))->to->equal(1);
		expect($f(2))->to->equal(6);
	});
});

describe('fn::unless($cond, $ifFalse, $ifTrue=\'$_\')', function() {
	it('returns a callable for $cond($_) ? $ifTrue($_) : $ifFalse($_)', function() {
		$f = fn::unless('$_==1', '$_*2', '$_+4');
		expect($f(1))->to->equal(5);
		expect($f(2))->to->equal(4);
		$f = fn::unless('$_==2', '$_*3');
		expect($f(1))->to->equal(3);
		expect($f(2))->to->equal(2);
	});
});

