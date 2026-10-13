---
name: eloquent-nested-eager-loads
description: "Use when writing, reviewing, or moving Eloquent eager loads with with(), especially nested relations such as variations.attributeItems. A later with() for a child relation deletes the parent relation callback registered by an earlier with()."
license: MIT
---

# Nested eager-load constraints

Eloquent stores every `with()` on the same builder in one map. A dotted name also registers each parent. If that parent was not passed in the same `with()` call, Eloquent stores an empty callback for it. The next `with()` then replaces any callback already stored for that parent.

This has worked this way since Laravel 4. Putting the calls in a different order, or splitting them so a static analyser can infer a type, changes which rows are loaded.

## Keep parent and child constraints in one with()

```php
$query->with([
    'variations' => function (Relation $query): void {
        $query->where('table_show', true);
    },
    'variations.attributeItems' => function (Relation $query): void {
        $query->where('is_show', true);
    },
]);
```

Both callbacks run. The child name does not replace the parent callback, because the parent is already present in that same call.

## Do not chain a child with() after a constrained parent

```php
$query->with([
    'variations' => function (Relation $query): void {
        $query->where('table_show', true);
    },
])
->with([
    'variations.attributeItems' => function (Relation $query): void {
        $query->where('is_show', true);
    },
]);
```

The second call loads `variations.attributeItems` and replaces `variations` with an empty callback. `where('table_show', true)` is gone. The same thing happens when the second call is `with('variations.attributeItems', ...)` or a later statement on the same builder variable.

A child `with()` that runs before the parent constraint does not hit this case: the later parent callback is the one that remains. Do not rely on that order. Pass both in one array.

Type the callback parameter as `Relation`. `with('variations', function (Relation $query) { ... })` is narrowed to that relation. An array callback stays `Relation`, so a scope such as `attributeOrderBy()` is not visible there.

When the child callback calls a scope, register that child on the Eloquent builder before the parent constraint:

```php
$query
    ->with('variations.attributeItems.attribute')
    ->with('variations.attributeItems', function (Relation $query): void {
        $query->where('is_show', true)->attributeOrderBy();
    })
    ->with([
        'variations' => function (Relation $query): void {
            $query->where('table_show', true);
        },
    ]);
```

The later `with('variations', ...)` replaces only `variations`. The child callback remains. Do not put `variations.attributeItems` or anything under it in that later `with()`.

`EsfahanAhan\PHPStan\Rules\NestedEagerLoadWithOverwritesConstraintRule` reports the chained form. A single `with()` for one relation is a normal call.
