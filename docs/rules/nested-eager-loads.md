---
layout: default
title: Nested eager-load with()
nav_order: 2
permalink: /rules/nested-eager-loads/
---

# Nested eager-load `with()`

Identifier: `esfahanahan.nestedEagerLoadOverwritesConstraint`

Class: `EsfahanAhan\PHPStan\Rules\NestedEagerLoadWithOverwritesConstraintRule`

Eloquent stores every `with()` on one builder in one map. A dotted name such as `variations.attributeItems` also stores a callback for each parent. If that parent was not passed in the same `with()` call, Eloquent stores an empty callback for it. The next `with()` replaces any callback already stored for that parent.

This has worked this way since Laravel 4. Splitting the calls so a static analyser can infer a narrower type changes which rows are loaded.

## Reported

A later `with()` names a child, and an earlier `with()` on the same builder had a real callback for the parent.

```php
$query->with([
    'variations' => function (Relation $query): void {
        $query->where('table_show', true);
    },
])->with([
    'variations.attributeItems' => function (Relation $query): void {
        $query->where('is_show', true);
    },
]);
```

The same replacement happens for `with('variations.attributeItems', ...)` and for a later statement on the same variable:

```php
$query->with('variations', function (Relation $query): void {
    $query->where('table_show', true);
});

$query->with('variations.attributeItems', function (Relation $query): void {
    $query->where('is_show', true);
});
```

`where('table_show', true)` does not run. Hidden variations are loaded.

## Keep both constraints in one call

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

The child name does not replace the parent callback, because the parent is already present in that same call.

## When the child callback calls a model scope

An array callback is typed as `Relation`, so a scope such as `attributeOrderBy()` is not visible to PHPStan there. `with('variations.attributeItems', function (Relation $query) { ... })` is narrowed to that relation.

Register the child before the parent constraint. The later `with('variations', ...)` replaces only `variations`. Do not name `variations.attributeItems`, or anything under it, in that later call. A deeper name in a later `with()` stores an empty callback for `variations.attributeItems` and drops the scope.

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

## Not reported

- Parent and child passed in the same `with()` array.
- The parent constraint registered after the child. The later parent callback is the one that remains. Do not rely on that order.
- A new builder assigned over the variable before the child `with()`.
- Unrelated relation names.
- `load()`. Each `load()` runs its own query and does not merge into the builder's eager-load map.

## Laravel Boost

The package includes the skill `eloquent-nested-eager-loads` at `resources/boost/skills/eloquent-nested-eager-loads`. Add `esfahanahan/phpstan-rules` to the `packages` list in `boost.json` and keep the skill name in `skills`.
