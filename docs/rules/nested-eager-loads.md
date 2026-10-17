---
layout: default
title: Nested eager-load with()
nav_order: 2
permalink: /rules/nested-eager-loads/
---

# Nested eager-load `with()`

Written by [Hossein Hosni](https://github.com/hosni).

Identifier: `esfahanahan.nestedEagerLoadOverwritesConstraint`

Class: `EsfahanAhan\PHPStan\Rules\NestedEagerLoadWithOverwritesConstraintRule`

Eloquent stores every `with()` on one builder in one map. A dotted name such as `posts.comments` also stores a callback for each parent. If that parent was not passed in the same `with()` call, Eloquent stores an empty callback for it. The next `with()` replaces any callback already stored for that parent.

This has worked this way since Laravel 4. Splitting the calls so a static analyser can infer a narrower type changes which rows are loaded.

`User` has many `Post`s, and `Post` has many `Comment`s. Users `1` and `2` exist. User `1` has published post `10` and unpublished post `12`. User `2` has published post `11`.

## Reported

A later `with()` names a child, and an earlier `with()` on the same builder had a real callback for the parent.

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

```sql
select * from `users`

select * from `posts` where `posts`.`user_id` in (1, 2)

select * from `comments` where `comments`.`post_id` in (10, 11, 12) and `approved` = 1
```

`where published = 1` is gone. Unpublished post `12` is loaded. The comments constraint stays.

The same replacement happens for `with('posts.comments', ...)` and for a later statement on the same variable:

```php
$users = User::query()
    ->with('posts', static function (Relation $query): void {
        $query->where('published', true);
    });

$users->with('posts.comments', static function (Relation $query): void {
    $query->where('approved', true);
});
```

`where('published', true)` does not run. Unpublished posts are loaded.

## Keep both constraints in one call

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

```sql
select * from `users`

select * from `posts` where `posts`.`user_id` in (1, 2) and `published` = 1

select * from `comments` where `comments`.`post_id` in (10, 11) and `approved` = 1
```

The child name does not replace the parent callback, because the parent is already present in that same call. Comments are loaded only for published posts `10` and `11`.

## When the child callback calls a model scope

An array callback is typed as `Relation`, so a local scope such as `newest()` on `Comment` is not visible to PHPStan there. `with('posts.comments', static function (Relation $query) { ... })` is narrowed to that relation.

Register the child before the parent constraint. The later `with('posts', ...)` replaces only `posts`. Do not name `posts.comments`, or anything under it, in that later call. A deeper name in a later `with()` stores an empty callback for `posts.comments` and drops the scope.

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

## Not reported

- Parent and child passed in the same `with()` array.
- The parent constraint registered after the child. The later parent callback is the one that remains. Do not rely on that order.
- A new builder assigned over the variable before the child `with()`.
- Unrelated relation names.
- `load()`. Each `load()` runs its own query and does not merge into the builder's eager-load map.

## Laravel Boost

The package includes the skill `eloquent-nested-eager-loads` at `resources/boost/skills/eloquent-nested-eager-loads`. Add `esfahanahan/phpstan-rules` to the `packages` list in `boost.json` and keep the skill name in `skills`.
