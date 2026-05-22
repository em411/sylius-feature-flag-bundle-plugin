# Design: Sylius FeatureFlag Bundle Plugin

**Date:** 2026-05-22
**Status:** Approved

## Goal

A new, standalone Symfony bundle / Sylius plugin that lets feature flags be
**managed from the Sylius admin panel**. It depends on `em411/feature-flag-bundle`
and contributes a Doctrine-entity-backed `ProviderInterface` whose flags are
exposed as a Sylius resource (CRUD + grid + admin menu entry).

Target platform: **Sylius 1.8 / Symfony 4.4 / PHP 7.4** — the same platform as
`em411/feature-flag-bundle`.

## Names

- Composer package: `em411/sylius-feature-flag-bundle-plugin`
- Repository: `github.com/em411/sylius-feature-flag-bundle-plugin` (new)
- PHP namespace: `Em411\SyliusFeatureFlagPlugin\` (PSR-4, `src/`)
- Bundle class: `Em411\SyliusFeatureFlagPlugin\Em411SyliusFeatureFlagPlugin`
- DI extension alias / semantic config root: `em411_sylius_feature_flag_plugin`
- Sylius resource name: `em411_sylius_feature_flag.feature_flag`

## Scope

**In scope:** the `FeatureFlag` Doctrine entity, the Doctrine-backed provider
wired into `em411/feature-flag-bundle`'s provider chain, Sylius resource
registration (CRUD), a grid, a form type, admin routing, an admin-menu entry,
translations, semantic configuration (`provider_priority`), unit tests, an
integration (container + schema) test, and CI on PHP 7.4.

**Out of scope:** native per-user / per-group flag evaluation (the bundle is
context-unaware — see "Group targeting" below); non-string typed values; a
custom admin UI beyond the standard Sylius CRUD templates; a full Sylius
Behat/browser functional suite; REST / API Platform exposure; shipping a
Doctrine migration (migrations are app-specific — see "Host application setup").

## The `FeatureFlag` entity

A Doctrine ORM entity implementing Sylius's
`Sylius\Component\Resource\Model\ResourceInterface`.

| Field | Type | Notes |
|---|---|---|
| `id` | `int`, PK, auto | `getId(): ?int` |
| `code` | `string`, unique, not null | the feature name checked via `isEnabled()` / `getValue()` |
| `enabled` | `bool`, not null, default `false` | the on/off toggle |
| `value` | `string`, nullable | opaque payload (e.g. a list of allowed customer-group codes) |
| `description` | `string`, nullable | admin-only, shown in the grid |

ORM mapping: **XML**, in `Resources/config/doctrine/FeatureFlag.orm.xml`. The
bundle registers this mapping itself (see "Doctrine mapping registration"), so
the host application needs no `doctrine.orm.mappings` configuration.

## Provider behaviour and value semantics

`Provider\DoctrineFeatureFlagProvider` implements
`Ajgarlag\FeatureFlagBundle\Provider\ProviderInterface`.

- It is constructed with the `FeatureFlag` repository.
- On the **first** `get()` call it loads **all** `FeatureFlag` rows into a
  `code => FeatureFlag` map (one `SELECT` per request) and caches it. If no
  feature is ever checked in a request, no query runs.
- `get(string $featureName): ?callable` returns `null` when no row has that
  `code`; otherwise it returns a closure that resolves the flag's value.
- It implements `Symfony\Contracts\Service\ResetInterface` and is tagged
  `kernel.reset`, clearing the cached map between requests (for worker runtimes).

The feature-flag-bundle's model is one feature → one callable → one value
(`isEnabled()` is true only when that value is exactly `true`; `getValue()`
returns the raw value). A `FeatureFlag` row carries both an `enabled` flag and
an optional `value`, so the resolving closure encodes both into one return
value:

| Row state | Closure returns |
|---|---|
| `enabled = false` | `false` |
| `enabled = true`, `value` is `null` or `''` | `true` |
| `enabled = true`, `value` is a non-empty string | the `value` string |

Consequences:
- A plain on/off flag (no `value`) works with `isEnabled('code')`.
- A value-carrying flag is read with `getValue('code')` — it returns the string
  when the flag is on and `false` when off.

### Group targeting (app-side)

The bundle is intentionally **context-unaware**: `isEnabled()` / `getValue()`
take only a feature name, no user. To enable a feature for a group of users, an
admin stores the group identifiers in the flag's `value`, and the host
application performs the membership check. The README documents this pattern
with a worked example: a flag whose `value` is a comma-separated list of Sylius
customer-group codes, checked in application code against the logged-in
customer's group, e.g.:

```php
$allowed = $featureChecker->getValue('beta_checkout');           // "wholesale,vip" or false
$groups  = false === $allowed ? [] : explode(',', $allowed);
$isOn    = \in_array($customer->getGroup()->getCode(), $groups, true);
```

Native per-user evaluation was considered and deliberately excluded to keep the
bundle generic and the cache model simple.

## Provider chaining and priority

`DoctrineFeatureFlagProvider` is registered as a service tagged
`ajgarlag.feature_flag.provider`. `em411/feature-flag-bundle`'s `ChainProvider`
receives all such providers as a **priority-sorted** tagged iterator
(`type="tagged"` is priority-ordered since Symfony 4.3 via
`ResolveTaggedIteratorArgumentPass`) and the first provider returning a non-null
callable wins.

The Doctrine provider is tagged with a **high priority** (default `100`,
configurable) so a flag stored in the database **overrides** a code-defined
feature (`@AsFeature` / service tag) of the same `code`. The feature-flag-bundle's
own provider carries no priority (effectively `0`).

The priority is exposed as semantic configuration:

```yaml
em411_sylius_feature_flag_plugin:
    provider_priority: 100   # default
```

The DI extension stores this in a container parameter; the provider service's
tag in XML reads it: `<tag name="ajgarlag.feature_flag.provider"
priority="%em411_sylius_feature_flag_plugin.provider_priority%"/>`.

## Sylius admin integration

### Resource registration
The bundle registers the resource through `sylius_resource` configuration. The
DI extension implements `PrependExtensionInterface` and **prepends** the
`sylius_resource` and `sylius_grid` configuration, so the host application only
has to register the bundle (no resource/grid config to copy):

```yaml
sylius_resource:
    resources:
        em411_sylius_feature_flag.feature_flag:
            classes:
                model: Em411\SyliusFeatureFlagPlugin\Entity\FeatureFlag
                form: Em411\SyliusFeatureFlagPlugin\Form\Type\FeatureFlagType
```

This yields the standard Sylius resource services (repository, factory, manager,
CRUD controller) under the `em411_sylius_feature_flag.*` prefix.

### Grid
A `sylius_grid` grid `em411_sylius_feature_flag_admin_feature_flag`, driver
`doctrine/orm`:
- Fields: `code` (string, sortable), `enabled` (twig, rendered with Sylius's
  yes/no field template), `description` (string).
- Filters: `code` (string), `enabled` (boolean).
- Actions: `create` (main); `update`, `delete` (item).

### Form
`Form\Type\FeatureFlagType` extends Sylius's `AbstractResourceType`. Fields:
`code` (text), `enabled` (checkbox, not required), `value` (text, not required),
`description` (text, not required).

### Routing
The bundle ships `Resources/config/routing/admin.yaml` declaring the
`sylius.resource` route for the resource (section `admin`, templates
`@SyliusAdmin/Crud`, grid bound). Routing cannot be prepended, so the host
application imports this one file under its `/admin` prefix — the single
documented installation step beyond registering the bundle. No custom CRUD
templates are needed; the generic `@SyliusAdmin/Crud` templates are reused.

### Admin menu
`Menu\AdminMenuListener` listens on the `sylius.menu.admin.main` event and adds
a "Feature Flags" item under the admin "Configuration" section, linking to the
resource index route. Registered as a `kernel.event_listener`.

### Translations
`Resources/translations/messages.en.yaml` provides the labels referenced by the
grid, the menu item, the form, and the CRUD page headers.

## Doctrine mapping registration

`Em411SyliusFeatureFlagPlugin::build()` adds a
`DoctrineOrmMappingsPass::createXmlMappingDriver()` compiler pass mapping
`Resources/config/doctrine/` to the `Em411\SyliusFeatureFlagPlugin\Entity`
namespace. This makes the `FeatureFlag` entity's mapping load without any host
application Doctrine configuration.

## Bundle structure

```
src/
  Em411SyliusFeatureFlagPlugin.php          bundle class (+ build(): mapping pass)
  DependencyInjection/
    Em411SyliusFeatureFlagPluginExtension.php   loads services, prepends sylius_resource/sylius_grid, sets the priority parameter
    Configuration.php                            semantic config (provider_priority)
  Entity/
    FeatureFlag.php                           ResourceInterface entity
  Provider/
    DoctrineFeatureFlagProvider.php           ProviderInterface, ResetInterface
  Form/Type/
    FeatureFlagType.php
  Menu/
    AdminMenuListener.php
Resources/
  config/
    services.xml                              service definitions
    doctrine/FeatureFlag.orm.xml              ORM mapping
    routing/admin.yaml                        sylius.resource route
    grids/em411_sylius_feature_flag.yaml      sylius_grid config (loaded/prepended)
  translations/messages.en.yaml
tests/
  ...
```

## Host application setup

Documented in the README:
1. `composer require em411/sylius-feature-flag-bundle-plugin`.
2. Register `Em411SyliusFeatureFlagPlugin` (and, if not already, the
   `em411/feature-flag-bundle` bundle) in `config/bundles.php`.
3. Import the admin routing file under the `/admin` prefix.
4. Generate and run a Doctrine migration for the `feature_flag` table
   (`bin/console doctrine:migrations:diff` then `migrate`, or
   `make:migration`). The bundle ships the ORM mapping but not the migration,
   since migration files belong to the application's own namespace/history.

## Dependencies

- `php: >=7.4`
- `em411/feature-flag-bundle: ^1.0`
- `sylius/resource-bundle` — the version bundled with Sylius 1.8 (`^1.6`)
- `sylius/grid-bundle` — the Sylius 1.8 line (`^1.8`)
- `doctrine/orm`, `doctrine/doctrine-bundle`
- `symfony/* : ^4.4` components used (config, dependency-injection, http-kernel,
  event-dispatcher, form)
- The exact constraints are pinned in the implementation plan and verified by
  `composer update` in CI / Docker.

## Testing

- **Unit tests** (PHPUnit, no Sylius app):
  - `DoctrineFeatureFlagProvider` — the value-resolution table (disabled →
    `false`; enabled, no value → `true`; enabled, value → the string),
    `null` for an unknown code, single-query lazy loading, and `reset()`,
    using an in-memory fake repository.
  - `FeatureFlag` — getters/setters and defaults.
  - `AdminMenuListener` — adds the expected item to a real KnpMenu menu.
- **Integration test** — a minimal Symfony test kernel registering the plugin,
  `em411/feature-flag-bundle`, SyliusResourceBundle, SyliusGridBundle and
  DoctrineBundle against an in-memory SQLite database. It asserts: the container
  compiles; the resource/grid/provider services exist; the `DoctrineFeatureFlagProvider`
  is in the `ajgarlag.feature_flag.provider`-tagged set at the configured
  priority; and the ORM schema for `FeatureFlag` can be created (exercising the
  XML mapping). A full Sylius admin Behat/browser test is out of scope.
- **CI / verification:** GitHub Actions on PHP 7.4 (Sylius 1.8's platform); a
  PHP 7.4 Docker image for local verification, as in the other repositories.
  PHP-CS-Fixer runs as a standalone tool (it cannot be a Composer dependency on
  a Symfony 4.4 stack).

## Risks / open points

- Booting SyliusResourceBundle + SyliusGridBundle in a lightweight test kernel
  needs their transitive prerequisites (translator, property-access, etc.); the
  integration-test kernel must register enough to compile. CI is the source of
  truth.
- `sylius/resource-bundle` and `sylius/grid-bundle` version constraints for the
  Sylius 1.8 line are confirmed during `composer update`; the plan adjusts them
  if resolution requires it.
