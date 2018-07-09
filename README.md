## fun-factory: Make Functional Programming Fun Again

Tired of typing `function($x) use($y) { return $z; }` when all you want is a simple lamba expression, or to chain a few property accesses or method calls?  Like being able to compose functions but hate using huge "functional" libraries for PHP that nest closures ten levels deep and aren't particularly idiomatic for PHP?

Enter **fun-factory**.  Just `composer require dirtsimple/fun-factory` and `use function dirtsimple\fn;` to get the following functional programming shortcuts:

<table>
<tr><th><th>Fun Factory<th>PHP Equivalent
<tr><td>

**Identity**

<td>

```php
$f = fn();
```

<td>

```php
$f = function ($arg) { return $arg; };
```

<tr style="display:none">
<tr><td>

**Lambda Strings**

<td>

```php
$f = fn('$_ * 2');
```

<td>

```php
$f = function ($arg) { return $arg * 2; };
```
<tr style="display:none">
<tr><td>

**Chaining/Partials**

(Any number of methods, keys, and properties)

<td valign="top">

```php
$f1 = fn()->foo($bar)->baz();



$f2 = fn()->aProp[$key];
```

<td>

```php
$f1 = function ($arg) use ($bar) {
    return $arg->foo($bar)->baz();
};

$f2 = function ($arg) use ($key) {
    return $arg->aProp[$key];
};
```
<tr style="display:none">
<tr><td>

**Composition**

(Any number of PHP callables or lambda strings)

<td valign="top">

```php
$f1 = fn('array_flip', 'array_reverse');



$f2 = fn('func', [$ob, 'meth'], '$_*2');
```

<td>

```php
$f1 = function ($arg) {
    return array_flip(array_reverse($arg));
};

$f2 = function ($arg) use ($ob) {
    return func($ob->meth($arg * 2));
};
```
<tr style="display:none">
<tr><td>

**Chainable Array Item Operators**

<td valign="top">

```php
$set = fn()->offsetSet($foo, $bar);




$unset = fn()->offsetUnset($foo);




$exists = fn()->offsetExists($foo);
```

<td>

```php
$set = function ($arg) use ($foo, $bar) {
    $arg[$foo] = $bar;
    return $arg;
};

$unset = function ($arg) use ($foo) {
    unset($arg[$foo]);
    return $arg;
};

$exists = function ($arg) use ($foo) {
    return is_array($arg)
        ? array_key_exists($foo, $arg)
        : $arg->offsetExists($foo);
};
```
</table>

The `fn()` function accepts zero or more PHP callables (or lambda expression strings), returning the functional composition of those arguments.  String arguments that aren't syntactically PHP function or static method names are assumed to be PHP expressions, and converted to lambda functions taking `$_` as a parameter.  (The resulting closures are cached, so repeated calls to say, `fn('$_ * 2')`, don't use excess memory or waste time recompiling.)

The callable objects returned by `fn()` support chained property/element access and method calls, returning new callables that stack those accesses, making it easy to incrementally compose functional pipelines.  But since PHP isn't optimized for recursion, fun-factory represents composed functions as opcode arrays that are iterated over instead of recursed into, cutting function call overhead and stack depth in half compared to functional libraries based on closures.

This and other aspects of fun-factory's design are done that way to make it easy for *other* libraries to offer lambda expression and chaining support to their clients.  If your API calls `fn()` on supplied callback arguments, it gives your clients the ability to pass in lambda expressions as well as standard PHP callbacks.

You can then compose or stack the result, or expose composable/stackable results back to your clients.  (And, since`fn()` is idempotent, and calling it on something already wrapped by it is a fast no-op, it's okay to use even when somebody passes you a `fn()` to start with.)

## Additional APIs

But wait, there's more!  Add `use dirtsimple\fn;` to your code *now*, and you'll also get these fine static methods at no additional cost:

### fn::bind($callable, ...$args)

Returns a `fn()` that when called with `$x` returns `$callable(...$args, $x)`.  (That is, any arguments after `$callable` are passed in first.)  `$callable` *must* be a PHP callable (not a lambda expression string), and it is checked for validity at bind time.  (Which may cause autoloading, if it names a static method.)

(Note that `fn()` objects themselves only take one parameter, so passing a `fn()` to `bind()` effectively makes it act as if it were always being passed the first element of `$args` instead of whatever argument is actually given.)

### fn::tap(...$callables)

Returns a `fn()` that applies`fn(...$callables)` to its argument, but throws away the return value and returns the original argument instead.  Useful for creating side effects, this is roughly equivalent to:

```php
$f = fn(...$callables);
function ($_) use ($f) { $f($_); return $_; }
```

Except that a `fn()` is returned rather than a closure.

### fn::val($value)

Returns a callable that returns `$value`.  Shorthand for `function() use ($value) { return $value; }`.

### fn::when($cond, $ifTrue, $ifFalse='$_')

Returns a `fn()` roughly equivalent to `function($_) { return $cond($_) ? $ifTrue($_) : $ifFalse($_); }`, except that all three arguments can be lambda expression strings as well as PHP callables.

### fn::unless($cond, $ifFalse, $ifTrue='$_')

The same as `fn::when()`, but with the arguments swapped.

### fn::is_callable_name($string)

Returns true if `$string` is a syntactically valid PHP callable.  That is, if `$string` matches a regular expression for a possibly-namespaced function or static method.  The existence of the function, class, or method is not checked, only the syntax.  `fn()` uses this internally to distinguish callables from lambda expressions, while deferrring any class loading or function lookups until the resulting object is actually called.

## API Version Compatibility

Please note that this library's API is *only* what is documented in this README, not what is public in the classes or tested by the specs!  Anything not explicitly documented here is subject to change between even *minor* versions.  (In particular, note that whenever this document says a "callable" is returned, you should not assume what *type* of callable will be returned, except that PHP `is_callable()` will return true for it.)
