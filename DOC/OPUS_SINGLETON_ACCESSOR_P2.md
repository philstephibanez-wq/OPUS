# P2 — OPUS Singleton / Accessor contract

## Status

Delivered as controlled tooling first.

## Why

The historical ASAP idea is kept as an OPUS contract:

- classes keep their internal state in protected properties;
- external code must access state through getters/setters;
- getters/setters should not have to be manually declared for every property;
- one framework singleton instance must be addressable per site/application scope when needed.

## Contract

### Accessor

`OPUS_AccessorInterface` declares the explicit access contract:

```php
public function get($property);
public function set($property, $value);
public function has($property): bool;
```

`OPUS_Singleton` implements:

```text
getXxx()
setXxx($value)
hasXxx()
get('xxx')
set('xxx', $value)
has('xxx')
```

Property resolution supports both:

```text
xxx
_xxx
```

So `getSite()` may resolve the protected `$_site` property.

### Singleton per scope

`OPUS_Singleton::getInstance()` keeps the historical default singleton behavior.

Additional scope helpers are added:

```php
OPUS_Singleton::getInstanceForSite('logandplay')
OPUS_Singleton::getInstanceForApplication('studio')
```

This gives one instance per concrete class and per scope.

## Important distinction

The singleton controls one PHP runtime/request scope. It does not, by itself, block two browsers or two sessions.

A later explicit guard must handle that:

```text
OPUS_SiteSessionGuard
OPUS_ApplicationLock
```

## Scope of P2

Allowed:

- `Opus/AccessorInterface.class.php`
- `Opus/Singleton.class.php`
- `tools/`
- `DOC/`

Forbidden:

- `sites/`
- `vendor/`
- `var/cache/`
- `var/log/`
- `var/tmp/`
- FSM behavior
- Router behavior
- site extraction

## Commands

Preview:

```cmd
cd /d H:\OPUS
python tools\apply_opus_singleton_accessor_p2.py
```

Apply:

```cmd
cd /d H:\OPUS
python tools\apply_opus_singleton_accessor_p2.py --write
php tools\smoke_opus_singleton_accessor_p2.php
php tools\smoke_opus_boot_render_p1.php
php tools\smoke_opus_view_scoretemplate_p1b.php
python tools\smoke_opus_naming_p1d.py
git status --short
```

Expected:

```text
P2_SINGLETON_ACCESSOR_APPLY_OK
P2_OPUS_SINGLETON_ACCESSOR_SMOKE_OK
P1_OPUS_BOOT_RENDER_SMOKE_OK
P1B_OPUS_VIEW_SCORETEMPLATE_SMOKE_OK
P1D_OPUS_NAMING_SMOKE_OK
```

## Next later

After this contract is validated, audit root-level OPUS files and decide what remains core, facade, internal, review, or delete-later.
