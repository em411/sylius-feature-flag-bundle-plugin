# Sylius FeatureFlag Bundle Plugin

[![CI](https://github.com/em411/sylius-feature-flag-bundle-plugin/actions/workflows/ci.yml/badge.svg)](https://github.com/em411/sylius-feature-flag-bundle-plugin/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/github/license/em411/sylius-feature-flag-bundle-plugin)](LICENSE)
[![Latest Stable Version](https://img.shields.io/github/v/release/em411/sylius-feature-flag-bundle-plugin?sort=semver)](https://github.com/em411/sylius-feature-flag-bundle-plugin/releases)

Manage [`em411/feature-flag-bundle`](https://github.com/em411/feature-flag-bundle)
feature flags from the **Sylius admin panel**.

Tested on **PHP 7.4 – 8.1** with **Sylius 1.8+** on **Symfony 4.4**.

Feature flags become a Sylius resource: an admin can create, edit and delete
them in a grid. Each flag has a `code`, an `enabled` toggle, an optional `value`
and a `description`. A Doctrine-backed provider feeds the flags into the feature
flag chain at a high priority, so a database flag overrides a code-defined
feature (`@AsFeature`) of the same code.

## Installation

```bash
composer require em411/sylius-feature-flag-bundle-plugin
```

### 1. Register the bundles

```php
// config/bundles.php
return [
    // ...
    Ajgarlag\FeatureFlagBundle\FeatureFlagBundle::class => ['all' => true],
    Em411\SyliusFeatureFlagPlugin\Em411SyliusFeatureFlagPlugin::class => ['all' => true],
];
```

### 2. Import the admin routing

```yaml
# config/routes/em411_sylius_feature_flag.yaml
em411_sylius_feature_flag_admin:
    resource: "@Em411SyliusFeatureFlagPlugin/Resources/config/routing/admin.yaml"
    prefix: /admin
```

### 3. Create the database table

The plugin ships the Doctrine mapping; generate and run a migration in your app:

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

### 4. Clear the cache

After installing or upgrading, clear the Symfony cache so the bundle's
translations and grid config are picked up:

```bash
bin/console cache:clear
```

### 5. (BitBag ACL plugin only) grant the permission

If your project uses [BitBagCommerce/SyliusAclPlugin](https://github.com/BitBagCommerce/SyliusAclPlugin)
(or a fork of it), open **Configuration → Administrators → Roles**, edit the
role that should manage feature flags, and tick the **Feature flag** entries.
Without this step the grid renders without **Edit** / **Delete** buttons because
the ACL plugin filters out actions for routes the role hasn't been granted.

## Usage

Open **Configuration → Feature flags** in the Sylius admin to manage flags.

Check a flag anywhere a `FeatureCheckerInterface` is available:

```php
$featureChecker->isEnabled('my_flag');   // plain on/off flags
$featureChecker->getValue('my_flag');    // value-carrying flags
```

### Enabling a feature for a group of users

The plugin is intentionally context-unaware. To target a group, store the group
identifiers in the flag's **value** and check membership in your application —
for example, a flag whose value is a comma-separated list of Sylius customer
group codes:

```php
$allowed = $featureChecker->getValue('beta_checkout');        // "wholesale,vip" or false
$groups  = false === $allowed ? [] : explode(',', $allowed);
$enabled = \in_array($customer->getGroup()->getCode(), $groups, true);
```

## Configuration

```yaml
# config/packages/em411_sylius_feature_flag_plugin.yaml
em411_sylius_feature_flag_plugin:
    provider_priority: 100   # priority of the DB provider in the feature flag chain
```

## License

MIT.
