---
name: eloquent-nested-eager-loads
description: "Use when writing, reviewing, or moving Eloquent eager loads with with(), especially nested relations such as posts.comments. A later with() for a child relation deletes the parent relation callback registered by an earlier with()."
license: MIT
---

# Nested eager-load constraints

Eloquent stores every `with()` on the same builder in one map. A dotted name also registers each parent. If that parent was not passed in the same `with()` call, Eloquent stores an empty callback for it. The next `with()` then replaces any callback already stored for that parent.

This has worked this way since Laravel 4. Putting the calls in a different order, or splitting them so a static analyser can infer a type, changes which rows are loaded.

`User` has many `Post`s, and `Post` has many `Comment`s.

## Keep parent and child constraints in one with()

```php
$users = User::with([
    'posts' => function (Relation $query): void {
        $query->where('published', true);
    },
    'posts.comments' => function (Relation $query): void {
        $query->where('approved', true);
    },
])->get();
```

Both callbacks run. The child name does not replace the parent callback, because the parent is already present in that same call. Only published posts are loaded, and only their approved comments.

## Do not chain a child with() after a constrained parent

```php
$users = User::with([
    'posts' => function (Relation $query): void {
        $query->where('published', true);
    },
])->with([
    'posts.comments' => function (Relation $query): void {
        $query->where('approved', true);
    },
])->get();
```

The second call loads `posts.comments` and replaces `posts` with an empty callback. `where('published', true)` is gone, so unpublished posts are loaded too. The same thing happens when the second call is `with('posts.comments', ...)` or a later statement on the same builder variable.

A child `with()` that runs before the parent constraint does not hit this case: the later parent callback is the one that remains. Do not rely on that order. Pass both in one array.

Type the callback parameter as `Relation`. `with('posts.comments', function (Relation $query) { ... })` is narrowed to that relation. An array callback stays `Relation`, so a local scope such as `newest()` on `Comment` is not visible there.

When the child callback calls a scope, register that child on the Eloquent builder before the parent constraint:

```php
$users = User::query()
    ->with('posts.comments.author')
    ->with('posts.comments', function (Relation $query): void {
        $query->where('approved', true)->newest();
    })
    ->with([
        'posts' => function (Relation $query): void {
            $query->where('published', true);
        },
    ])
    ->get();
```

The later `with('posts', ...)` replaces only `posts`. The child callback remains. Do not put `posts.comments` or anything under it in that later `with()`.

`EsfahanAhan\PHPStan\Rules\NestedEagerLoadWithOverwritesConstraintRule` reports the chained form. A single `with()` for one relation is a normal call.
