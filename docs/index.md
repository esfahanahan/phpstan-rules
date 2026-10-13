---
layout: default
title: Overview
nav_order: 1
permalink: /
---

# esfahanahan/phpstan-rules

Written by [Hossein Hosni](https://github.com/hosni).

## Requirements

- PHP 8.1 or higher
- PHPStan 2.0 or higher
- [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer), unless you include `extension.neon` yourself

The rules read PHP call sites. They do not boot Laravel and do not depend on `illuminate/*`.

## Install

```bash
composer require --dev esfahanahan/phpstan-rules
```

The `dev` keyword makes Composer offer to install this package into `require-dev`.

`phpstan/extension-installer` loads [`extension.neon`](https://github.com/esfahanahan/phpstan-rules/blob/master/extension.neon) from `composer.json`:

```json
"extra": {
    "phpstan": {
        "includes": [
            "extension.neon"
        ]
    }
}
```

Allow the plugin:

```json
{
    "config": {
        "allow-plugins": {
            "phpstan/extension-installer": true
        }
    }
}
```

Without the installer:

```neon
includes:
    - vendor/esfahanahan/phpstan-rules/extension.neon
```

Use `extension.neon` as the exported file. It can register rules and later extension types. `rules.neon` is only a better name when a package contains rules and will never contain anything else.

## Credit

Written by [Hossein Hosni](https://github.com/hosni).

## Rules

- [Nested eager-load `with()`]({{ '/rules/nested-eager-loads/' | relative_url }})
