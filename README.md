# EsfahanAhan PHPStan rules

[![Latest Version on Packagist](https://img.shields.io/packagist/v/esfahanahan/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/esfahanahan/phpstan-rules)
[![Tests](https://img.shields.io/github/actions/workflow/status/esfahanahan/phpstan-rules/tests.yml?branch=master&label=tests&style=flat-square)](https://github.com/esfahanahan/phpstan-rules/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/esfahanahan/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/esfahanahan/phpstan-rules)
[![PHP Version](https://img.shields.io/packagist/php-v/esfahanahan/phpstan-rules.svg?style=flat-square)](https://packagist.org/packages/esfahanahan/phpstan-rules)
[![License](https://img.shields.io/packagist/l/esfahanahan/phpstan-rules.svg?style=flat-square)](LICENSE)

Dev-only PHPStan rules and extensions for EsfahanAhan. Install it with `--dev`. It does not belong in production dependencies.

Usage docs: <https://esfahanahan.github.io/phpstan-rules>

## Requirements

- PHP 8.4 or higher
- PHPStan 2.1 or higher
- [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer) (recommended)

The nested eager-load rule inspects Eloquent `with()` call sites. The package does not depend on `illuminate/*`.

## Installation

```bash
composer require --dev esfahanahan/phpstan-rules
```

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

`where('table_show', true)` is gone. Pass both relations in one `with()` array. The [usage doc](https://esfahanahan.github.io/phpstan-rules/rules/nested-eager-loads/) shows the safe forms, including when a child callback calls a model scope.

## Laravel Boost

The package ships a skill at `resources/boost/skills/eloquent-nested-eager-loads`. Boost discovers that directory on a direct dependency. Add `esfahanahan/phpstan-rules` to `boost.json` `packages`, keep `eloquent-nested-eager-loads` in `skills`, then run Boost's update.

## Development

```bash
composer install
composer test
composer phpstan
composer format
```
