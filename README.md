# EsfahanAhan PHPStan rules

[![Latest Version on Packagist](https://img.shields.io/packagist/v/esfahanahan/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/esfahanahan/phpstan-rules)
[![Tests](https://img.shields.io/github/actions/workflow/status/esfahanahan/phpstan-rules/tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/esfahanahan/phpstan-rules/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/esfahanahan/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/esfahanahan/phpstan-rules)
[![PHP Version](https://img.shields.io/packagist/php-v/esfahanahan/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/esfahanahan/phpstan-rules)
[![License](https://img.shields.io/packagist/l/esfahanahan/phpstan-rules.svg?style=flat-square)](LICENSE)

Written by [Hossein Hosni](https://github.com/hosni).

Usage docs: <https://esfahanahan.github.io/phpstan-rules>

## Requirements

- PHP 8.1 or higher
- PHPStan ^2.0
- [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer) (recommended)

The nested eager-load rule inspects Eloquent `with()` call sites. The package does not depend on `illuminate/*`.

## Installation

```bash
composer require --dev esfahanahan/phpstan-rules
```

The `dev` keyword in `composer.json` makes Composer offer to install this package into `require-dev` when `composer require` is run without `--dev`.

With `phpstan/extension-installer`, that is enough. The installer loads `extension.neon`.

Allow the plugin once:

```json
{
    "config": {
        "allow-plugins": {
            "phpstan/extension-installer": true
        }
    }
}
```

Without the installer, include the extension yourself:

```neon
includes:
    - vendor/esfahanahan/phpstan-rules/extension.neon
```

`extension.neon` is the file to export. This package will hold rules and other PHPStan extensions (type, reflection, and similar). `rules.neon` only fits a package that is a ruleset and nothing else.

## Rules

### Nested eager-load `with()` must not drop a parent callback

Identifier: `esfahanahan.nestedEagerLoadOverwritesConstraint`

A later `with()` for a child relation replaces the parent callback registered by an earlier `with()`.

`User` has many `Post`s, and `Post` has many `Comment`s. Users `1` and `2` exist. User `1` has published post `10` and unpublished post `12`. User `2` has published post `11`. Splitting the constraints across two `with()` calls drops `where('published', true)`:

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

`published` is missing from the posts query, so unpublished post `12` is loaded.

Pass both relations in one `with()` array:

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

Both constraints run. Comments load only for published posts `10` and `11`. The [usage doc](https://esfahanahan.github.io/phpstan-rules/rules/nested-eager-loads/) shows the safe order when a child callback calls a model scope.

`load()` has the same result. Each call runs immediately, and `posts.comments` reloads `posts` with an empty callback, so the published posts are replaced:

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

Pass both relations in one `load()` array, or call `loadMissing()` for `posts.comments` when `posts` is already loaded. `loadMissing()` does not reload `posts`.

## Laravel Boost

The package ships a skill at `resources/boost/skills/eloquent-nested-eager-loads`. Boost discovers that directory on a direct dependency. Add `esfahanahan/phpstan-rules` to `boost.json` `packages`, keep `eloquent-nested-eager-loads` in `skills`, then run Boost's update.

## Credit

Written by [Hossein Hosni](https://github.com/hosni).

## Development

```bash
composer install
composer test
composer phpstan
composer format
```
