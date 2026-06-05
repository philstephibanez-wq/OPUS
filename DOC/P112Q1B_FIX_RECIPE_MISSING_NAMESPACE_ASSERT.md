# P112Q1B Fix — Missing Namespace Recipe Assertion

## Cause

The first P112Q1B recipe expected a `RouteCompilerException` when scanning a non-empty namespace with no indexed classes.

That expectation was wrong.

## Correct contract

- Empty namespace string: invalid.
- Non-empty namespace with no indexed classes: returns an empty route list.
- No fallback route is generated.

## Fix

The recipe now asserts:

`$provider->routes('ASAP\\Missing\\Namespace') === []`
