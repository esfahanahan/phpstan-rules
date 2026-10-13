# esfahanahan/phpstan-rules

Custom [PHPStan](https://phpstan.org) rules and extensions for EsfahanAhan.

Install the package and PHPStan loads `extension.neon` through [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer). New rules and extensions belong in this package.

## Installation

```bash
composer require --dev esfahanahan/phpstan-rules
```

The API consumes the package from Git until it is on Packagist:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/esfahanahan/phpstan-rules.git"
        }
    ],
    "require-dev": {
        "esfahanahan/phpstan-rules": "^1.0",
        "phpstan/extension-installer": "^1.4"
    }
}
```

`phpstan/extension-installer` must be allowed:

```json
{
    "config": {
        "allow-plugins": {
            "phpstan/extension-installer": true
        }
    }
}
```

## Rules

### Nested eager-load `with()` must not drop a parent callback

Identifier: `esfahanahan.nestedEagerLoadOverwritesConstraint`

Eloquent keeps one eager-load map on the builder. A dotted name such as `variations.attributeItems` also stores an empty callback for `variations` when that parent was not passed in the same `with()` call. A later `with()` then replaces a callback registered earlier.

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

`where('table_show', true)` is gone. Pass both relations in one `with()` array:

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

## Laravel Boost

The package ships a Boost skill at `resources/boost/skills/eloquent-nested-eager-loads`. Boost discovers `resources/boost/skills` on a direct Composer dependency. Add `esfahanahan/phpstan-rules` to the project's `boost.json` `packages` list, keep `eloquent-nested-eager-loads` in `skills`, and run Boost's install or update so the skill is copied to the agent skills directory.

## Development

```bash
composer install
composer test
composer phpstan
composer format
```
