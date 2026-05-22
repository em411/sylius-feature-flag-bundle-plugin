# Sylius FeatureFlag Bundle Plugin

Manage [`em411/feature-flag-bundle`](https://github.com/em411/feature-flag-bundle)
feature flags from the **Sylius admin panel**.

Compatible with **Sylius 1.8 / Symfony 4.4 / PHP 7.4**.

Feature flags become a Sylius resource: an admin can create, edit and delete
them in a grid. Each flag has a `code`, an `enabled` toggle, an optional `value`
and a `description`. A Doctrine-backed provider feeds the flags into the feature
flag chain at a high priority, so a database flag overrides a code-defined
feature (`@AsFeature`) of the same code.

## Installation

```bash
composer require em411/sylius-feature-flag-bundle-plugin
```

`em411/feature-flag-bundle` is not on Packagist — add it as a VCS repository in
your application's `composer.json` before requiring this plugin:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/em411/feature-flag-bundle" },
        { "type": "vcs", "url": "https://github.com/em411/sylius-feature-flag-bundle-plugin" }
    ]
}
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
