<?php
namespace dirtsimple\fun\test;

use function dirtsimple\fun;
use dirtsimple\fun;
use \Mockery;

function tearDown($func) {
	return function() use($func) {
		try { return $func(); }
		finally { \Brain\Monkey\tearDown(); }
	};
}

describe("fun", function() {
	it('() => ($x) => $x', function() {
		$f = fun(); expect($f(42))->to->equal(42);
	});
	it('() => () => null', function() {
		$f = fun(); expect($f())->to->equal(null);
	});
	it("() => the same callable each time", function() {
		$f = fun();
		expect(fun())->to->equal($f);
	});
	it("()->method(args...) => applier of method(args...)", tearDown(function() {
		$fn = fun()->x(99);
		$mock = Mockery::mock();
		$mock->shouldReceive('x')->with(99)->once()->andReturn("foo");
		expect($fn($mock))->to->equal("foo");
	}));
	it("()->property => getter of property", function() {
		$fn = fun()->foo;
		$data = (object) ['foo'=>42];
		expect($fn($data))->to->equal(42);
	});
	it('($string) => the same object each time', function(){
		$f = fun("is_array"); expect(fun("is_array"))->to->equal($f);
		expect($f([]))->to->be->true;
		expect($f(42))->to->be->false;
	});
	it('($expr) => callable that evaluates $expr w/$_ as parameter', function() {
		$f = fun('is_array($_)');
		expect($f([]))->to->be->true;
		expect($f("blah"))->to->be->false;
	});
	it('($factory) => $factory', function() {
		$f = fun(); expect(fun($f))->to->equal($f);
	});
	it('()[$key] => item-getter of key', function() {
		$fn = fun()[1];
		$data = ['foo','bar'];
		expect($fn($data))->to->equal('bar');
	});
	it("performs actions in their apparent order", tearDown(function(){
		$f = fun()->qq(3,4)->foo['bar'];
		$mock = Mockery::mock();
		$mock->shouldReceive('qq')->with(3,4)->once()->andReturn(
			(object) ['foo'=>['bar'=>23]]
		);
		expect($f($mock))->to->equal(23);
	}));
	it('composes multiple arguments', function() {
		$f = fun('array_flip','array_reverse');
		expect($f(['foo','bar']))->to->equal(['bar'=>0,'foo'=>1]);
	});
	it("can't be instantiated with new", function() {
		expect(function(){ new fun; })->to->throw(\BadMethodCallException::class, fun::class . " is not instantiable");
	});

	describe("ArrayAccess polymorphism:", function(){
		it("[] calls ->offsetGet() on objects", tearDown(function(){
			$f = fun()->offsetGet('foo');
			$mock = Mockery::mock(\ArrayAccess::class);
			$mock->shouldReceive('offsetGet')->with('foo')->once()->andReturn(42);
			expect($f($mock))->to->equal(42);
		}));
		it("offsetSet() assigns array subscripts", function(){
			$f = fun()->offsetSet('foo', 42);
			expect($f(['bar'=>99]))->to->equal(['bar'=>99,'foo'=>42]);
		});
		it("offsetSet() calls ->offsetSet() on objects", tearDown(function(){
			$f = fun()->offsetSet('foo', 'bar');
			$mock = Mockery::mock(\ArrayAccess::class);
			$mock->shouldReceive('offsetSet')->with('foo','bar')->once();
			expect($f($mock))->to->equal($mock);
		}));
		it("offsetUnset() unsets array subscripts", function(){
			$f = fun()->offsetUnset('foo');
			expect($f(['bar'=>99,'foo'=>42]))->to->equal(['bar'=>99]);
		});
		it("offsetUnset() calls ->offsetUnset() on objects", tearDown(function(){
			$f = fun()->offsetUnset('foo');
			$mock = Mockery::mock(\ArrayAccess::class);
			$mock->shouldReceive('offsetUnset')->with('foo')->once();
			expect($f($mock))->to->equal($mock);
		}));
		it("offsetExists() uses array_key_exists() on arrays", function(){
			$f = fun()->offsetExists('foo');
			expect($f(['foo'=>42]))->to->be->true;
			expect($f(['bar'=>99]))->to->be->false;
		});
		it("offsetExists() calls ->offsetExists() on objects", tearDown(function(){
			$f = fun()->offsetExists('foo');
			$mock = Mockery::mock(\ArrayAccess::class);
			$mock->shouldReceive('offsetExists')->with('foo')->once()->andReturn(true);
			expect($f($mock))->to->be->true;
		}));
	});

});

describe('fun::_()', function() {
	it('pipes multiple arguments', function() {
		$f = fun::_('array_reverse','array_flip');
		expect($f(['foo','bar']))->to->equal(['bar'=>0,'foo'=>1]);
	});
});

describe('fun::bind($callable, ...$args)', function() {
	it('returns a callable that receives $args before the arg', tearDown(function(){
		$mock = Mockery::mock();
		$mock->shouldReceive('testMe')->with('blue', 32, 'bar')->once()->andReturn("foo");
		$f = fun::bind([$mock, 'testMe'], 'blue', 32);
		expect($f('bar'))->to->equal('foo');
	}));
	it('receives null after $args if called with no arguments', tearDown(function(){
		$mock = Mockery::mock();
		$mock->shouldReceive('testMe')->with('blue', 32, null)->once()->andReturn("foo");
		$f = fun::bind([$mock, 'testMe'], 'blue', 32);
		expect($f())->to->equal('foo');
	}));
});

describe('fun::is_callable_name($string)', function() {
	it("accepts plain or namespaced functions or static methods", function() {
		expect(fun::is_callable_name('foo_bar'))->to->be->true;
		expect(fun::is_callable_name('foo_bar::baz'))->to->be->true;
		expect(fun::is_callable_name('a\bc'))->to->be->true;
		expect(fun::is_callable_name('\a\b::c_d0'))->to->be->true;
	});
	it("rejects strings w/any non-identifier chars", function() {
		expect(fun::is_callable_name('foo bar'))->to->be->false;
		expect(fun::is_callable_name('foo(bar)'))->to->be->false;
		expect(fun::is_callable_name('$_'))->to->be->false;
	});
	it("rejects strings w/invalid separators", function() {
		expect(fun::is_callable_name('foo\\\\bar'))->to->be->false;
		expect(fun::is_callable_name('foo::bar::baz'))->to->be->false;
		expect(fun::is_callable_name('baz:::spam'))->to->be->false;
	});
});

describe('fun::expr($string)', function() {
	it('returns the identity instance for $_', function() {
		expect(fun::expr('$_'))->to->equal(fun());
	});
	it('passes its argument to the expression as $_', function() {
		$f = fun::expr('is_array($_)');
		expect($f([]))->to->be->true;
		expect($f("blah"))->to->be->false;
	});
	it('returns the same instance for the same string', function() {
		$f = fun::expr('is_array($_)');
		expect(fun::expr('is_array($_)'))->to->equal($f);
	});
});

describe('fun::tap(...$callables)', function() {
	it('returns a function that applies $callables as a side-effect', tearDown(function() {
		$mock = Mockery::mock();
		$f = fun::tap([$mock, 'method'], '$_*2');
		$mock->shouldReceive('method')->once()->with(84)->andReturn(43);
		expect($f(42))->to->equal(42);
	}));
});

describe('fun::val($value)', function() {
	it('returns a callable that returns $value', function() {
		$f = fun::val(42);
		expect($f(27))->to->equal(42);
	});
});

describe('fun::when($cond, $ifTrue, $ifFalse=\'$_\')', function() {
	it('returns a callable for $cond($_) ? $ifTrue($_) : $ifFalse($_)', function() {
		$f = fun::when('$_==1', '$_*2', '$_+4');
		expect($f(1))->to->equal(2);
		expect($f(2))->to->equal(6);
		$f = fun::when('$_==2', '$_*3');
		expect($f(1))->to->equal(1);
		expect($f(2))->to->equal(6);
	});
});

describe('fun::unless($cond, $ifFalse, $ifTrue=\'$_\')', function() {
	it('returns a callable for $cond($_) ? $ifTrue($_) : $ifFalse($_)', function() {
		$f = fun::unless('$_==1', '$_*2', '$_+4');
		expect($f(1))->to->equal(5);
		expect($f(2))->to->equal(4);
		$f = fun::unless('$_==2', '$_*3');
		expect($f(1))->to->equal(3);
		expect($f(2))->to->equal(2);
	});
});

function test_transformer($scope, $target) {
	beforeEach(function() use ($target) { $this->to_test = $target; });

	it("defaults to an empty array as output", function(){
		expect( $this->to_test( [], 42 ) )->to->equal([]);
	});
	it("maps the schema functions into the output", function(){
		expect( $this->to_test( ['x'=>fun('$_*2'), 'y'=>fun('$_')], 42 ) )->to->equal(
			['x'=>84, 'y'=>42]
		);
	});
	it("includes or overwrites the passed-in output", function(){
		expect( $this->to_test(
			['x'=>fun('$_*2'), 'y'=>fun('$_')], 42, ['x'=>99, 'z'=>21]
		) )->to->equal(
			['x'=>84, 'z'=>21, 'y'=>42]
		);
	});
	it("calls the reducer with (out, key, fn, in)", function(){
		$schema = ['x'=>'$_*2', 'y'=>'$_'];
		$log = ['initial'];
		$logger = function($out, $key, $fn, $in) {
			$out[] = [$out, $key, $fn, $in];
			return $out;
		};
		$expected = $logger($log, 'x', '$_*2', 42);
		$expected = $logger($expected, 'y', '$_', 42);

		expect( $this->to_test($schema, 42, $log, $logger) )->to->equal($expected);
	});
}

describe('fun::transform()', function(){
	test_transformer($this, 'dirtsimple\fun::transform');
});

describe('fun::schema($schema)', function() {
	describe('returns a function bound to $schema that', function() {
		test_transformer($this, function($schema, ...$args) {
			return fun::schema($schema)(...$args);
		});
	});
});

