---
name: eloquent-nested-eager-loads
description: "Use when writing, reviewing, or moving Eloquent eager loads with with() or load(), especially nested relations such as posts.comments. A later with() or load() for a child relation drops the parent relation callback registered earlier."
license: MIT
---

# Nested eager-load constraints

Eloquent stores every `with()` on the same builder in one map. A dotted name also registers each parent. If that parent was not passed in the same `with()` call, Eloquent stores an empty callback for it. The next `with()` then replaces any callback already stored for that parent.

This has worked this way since Laravel 4. Putting the calls in a different order, or splitting them so a static analyser can infer a type, changes which rows are loaded.

`User` has many `Post`s, and `Post` has many `Comment`s. Users `1` and `2` exist. User `1` has published post `10` and unpublished post `12`. User `2` has published post `11`.

## Keep parent and child constraints in one with()

```php
$users = User::query()
    ->with([
        'posts' => static function (Relation $query): void {
            $query->where('published', true);
        },
        'posts.comments' => static function (Relation $query): void {
            $query->where('approved', true);
        },
    ])
    ->get();
```

Query log:

```text
select * from `users`
bindings: []

select * from `posts` where `posts`.`user_id` in (?, ?) and `published` = ?
bindings: [1, 2, 1]

select * from `comments` where `comments`.`post_id` in (?, ?) and `approved` = ?
bindings: [10, 11, 1]
```

Both callbacks run. The child name does not replace the parent callback, because the parent is already present in that same call. Only published posts are loaded, and only their approved comments.

## Do not chain a child with() after a constrained parent

```php
$users = User::query()
    ->with([
        'posts' => static function (Relation $query): void {
            $query->where('published', true);
        },
    ])
    ->with([
        'posts.comments' => static function (Relation $query): void {
            $query->where('approved', true);
        },
    ])
    ->get();
```

Query log:

```text
select * from `users`
bindings: []

select * from `posts` where `posts`.`user_id` in (?, ?)
bindings: [1, 2]

select * from `comments` where `comments`.`post_id` in (?, ?, ?) and `approved` = ?
bindings: [10, 11, 12, 1]
```

The second call loads `posts.comments` and replaces `posts` with an empty callback. `published` is missing, so unpublished post `12` is loaded too. The same thing happens when the second call is `with('posts.comments', ...)` or a later statement on the same builder variable.

A child `with()` that runs before the parent constraint does not hit this case: the later parent callback is the one that remains. Do not rely on that order. Pass both in one array.

Type the callback parameter as `Relation`. `with('posts.comments', static function (Relation $query) { ... })` is narrowed to that relation. An array callback stays `Relation`, so a local scope such as `newest()` on `Comment` is not visible there.

When the child callback calls a scope, register that child on the Eloquent builder before the parent constraint:

```php
$users = User::query()
    ->with('posts.comments.author')
    ->with('posts.comments', static function (Relation $query): void {
        $query->where('approved', true)->newest();
    })
    ->with([
        'posts' => static function (Relation $query): void {
            $query->where('published', true);
        },
    ])
    ->get();
```

The later `with('posts', ...)` replaces only `posts`. The child callback remains. Do not put `posts.comments` or anything under it in that later `with()`.

## load() reloads the parent

`load()` runs immediately. A later `load('posts.comments')` still registers `posts` with an empty callback, queries every post again, and replaces the constrained posts already on the model. Unpublished post `12` comes back.

```php
$users->load([
    'posts' => static function (Relation $query): void {
        $query->where('published', true);
    },
]);

$users->load([
    'posts.comments' => static function (Relation $query): void {
        $query->where('approved', true);
    },
]);
```

Query log for the second `load()`:

```text
select * from `posts` where `posts`.`user_id` in (?, ?)
bindings: [1, 2]

select * from `comments` where `comments`.`post_id` in (?, ?, ?) and `approved` = ?
bindings: [10, 11, 12, 1]
```

Pass both relations in one `load()` array. When `posts` is already loaded, call `loadMissing()` for `posts.comments`. `loadMissing()` does not reload `posts`.

The same reload happens when `with()` already constrained `posts` and a later `load('posts.comments')` runs on the result.

`EsfahanAhan\PHPStan\Rules\NestedEagerLoadWithOverwritesConstraintRule` reports the chained `with()` form and the later `load()` form. A single `with()` or `load()` for one relation is a normal call.
