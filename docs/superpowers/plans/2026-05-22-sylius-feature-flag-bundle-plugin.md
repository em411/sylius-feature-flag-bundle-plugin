# Sylius FeatureFlag Bundle Plugin — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `em411/sylius-feature-flag-bundle-plugin` — a Sylius 1.8 plugin that stores feature flags as a Doctrine entity managed through the Sylius admin panel, and feeds them into `em411/feature-flag-bundle`'s provider chain.

**Architecture:** A `FeatureFlag` Doctrine entity is registered as a Sylius resource (CRUD + grid + admin-menu entry). A `DoctrineFeatureFlagProvider` implements `em411/feature-flag-bundle`'s `ProviderInterface`, loads all flag rows once per request, and resolves each to a single value (`false` when disabled, `true` when enabled without a value, the `value` string when enabled with one). It is tagged into the provider chain at a high, configurable priority so DB flags override code-defined features.

**Tech Stack:** PHP 7.4, Symfony 4.4, Sylius 1.8 (`sylius/resource-bundle`, `sylius/grid-bundle`, `sylius/ui-bundle`), Doctrine ORM, `em411/feature-flag-bundle` 1.0, PHPUnit 9.5, Docker.

---

## Reference: Docker verification commands

Task 1 builds a PHP 7.4 image `sylius-ff-php74`. All PHP/Composer commands run inside it, from the repo root.

- **Run a command:** `docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$(pwd)":/app -w /app sylius-ff-php74 <command>`
- **Lint:** `docker run --rm -v "$(pwd)":/app -w /app sylius-ff-php74 php -l <file>`
- **PHPUnit:** `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 vendor/bin/phpunit <args>`

## Naming reference (use exactly these)

- Composer package: `em411/sylius-feature-flag-bundle-plugin`
- PHP namespace: `Em411\SyliusFeatureFlagPlugin\` → `src/`
- Test namespace: `Em411\SyliusFeatureFlagPlugin\Tests\` → `tests/`
- Bundle class: `Em411\SyliusFeatureFlagPlugin\Em411SyliusFeatureFlagPlugin`
- DI extension: `Em411\SyliusFeatureFlagPlugin\DependencyInjection\Em411SyliusFeatureFlagPluginExtension` (alias `em411_sylius_feature_flag_plugin`)
- Sylius resource name: `em411_sylius_feature_flag.feature_flag`
- Grid / route name: `em411_sylius_feature_flag_admin_feature_flag`
- DB table: `em411_feature_flag`
- All bundle resources live under `src/Resources/` (the bundle class is in `src/`, so `getPath()` is `src/`).

## File Structure

```
src/
  Em411SyliusFeatureFlagPlugin.php                       bundle class; build() registers the ORM mapping pass
  DependencyInjection/
    Configuration.php                                    semantic config: provider_priority
    Em411SyliusFeatureFlagPluginExtension.php             load() services.xml + priority param; prepend() sylius_resource & sylius_grid
  Entity/
    FeatureFlag.php                                      ResourceInterface entity
  Provider/
    DoctrineFeatureFlagProvider.php                      ProviderInterface + ResetInterface
  Form/Type/
    FeatureFlagType.php                                  AbstractResourceType form
  Menu/
    AdminMenuListener.php                                adds the admin-menu entry
  Resources/
    config/
      services.xml                                       service definitions
      doctrine/FeatureFlag.orm.xml                        ORM XML mapping
      routing/admin.yaml                                  sylius.resource route
    translations/messages.en.yaml                         labels
tests/
  Entity/FeatureFlagTest.php
  Provider/DoctrineFeatureFlagProviderTest.php
  Menu/AdminMenuListenerTest.php
  Integration/                                            test kernel + container/schema test
    IntegrationTest.php
    TestKernel.php
docs/superpowers/...                                      spec + this plan
composer.json  phpunit.xml.dist  .php-cs-fixer.dist.php  .gitignore  .gitattributes  .github/workflows/ci.yml  README.md
```

---

## Task 1: Create the repo, Docker image, and project skeleton

**Files:**
- Create: `.gitignore`, `.gitattributes`

- [ ] **Step 1: Create the GitHub repository**

```bash
gh repo create em411/sylius-feature-flag-bundle-plugin --public --description "Manage em411/feature-flag-bundle feature flags from the Sylius admin panel (Sylius 1.8 / PHP 7.4)"
```

Expected: prints the new repo URL. The local repo (already `git init`-ed, with the spec/plan committed) will get this as `origin` in Step 4.

- [ ] **Step 2: Build the PHP 7.4 Docker image**

```bash
docker build -t sylius-ff-php74 -f- . <<'EOF'
FROM php:7.4-cli
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libzip-dev libonig-dev \
 && docker-php-ext-install zip mbstring \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
EOF
```

Expected: build succeeds; final line names `sylius-ff-php74`. (`pdo_sqlite` is enabled by default in the official PHP image — needed later by the integration test.)

- [ ] **Step 3: Create `.gitignore`**

```
/vendor/
/composer.lock
/.php-cs-fixer.cache
/.phpunit.result.cache
```

- [ ] **Step 4: Create `.gitattributes`**

```
/tests export-ignore
/docs export-ignore
/phpunit.xml.dist export-ignore
/.php-cs-fixer.dist.php export-ignore
/.github export-ignore
/.gitattributes export-ignore
/.gitignore export-ignore
```

- [ ] **Step 5: Set the git remote and commit**

```bash
git remote add origin "https://github.com/em411/sylius-feature-flag-bundle-plugin.git"
git add .gitignore .gitattributes
git commit -m "chore: project skeleton (gitignore, gitattributes)"
```

---

## Task 2: composer.json

**Files:**
- Create: `composer.json`

- [ ] **Step 1: Create `composer.json`**

`em411/feature-flag-bundle` is not on Packagist, so it is pulled via a VCS repository entry.

```json
{
    "name": "em411/sylius-feature-flag-bundle-plugin",
    "type": "symfony-bundle",
    "description": "Manage em411/feature-flag-bundle feature flags from the Sylius admin panel.",
    "keywords": ["sylius", "sylius-plugin", "feature", "flag", "feature flag", "toggle"],
    "license": "MIT",
    "require": {
        "php": ">=7.4",
        "doctrine/doctrine-bundle": "^2.0",
        "doctrine/orm": "^2.7",
        "doctrine/persistence": "^1.3 || ^2.0",
        "em411/feature-flag-bundle": "^1.0",
        "knplabs/knp-menu": "^3.0",
        "sylius/grid-bundle": "^1.8",
        "sylius/resource-bundle": "^1.6",
        "sylius/ui-bundle": "^1.8",
        "symfony/config": "^4.4",
        "symfony/dependency-injection": "^4.4",
        "symfony/event-dispatcher": "^4.4",
        "symfony/form": "^4.4",
        "symfony/http-kernel": "^4.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5",
        "symfony/framework-bundle": "^4.4",
        "symfony/twig-bundle": "^4.4",
        "symfony/yaml": "^4.4",
        "twig/twig": "^2.12 || ^3.0"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/em411/feature-flag-bundle"
        }
    ],
    "autoload": {
        "psr-4": { "Em411\\SyliusFeatureFlagPlugin\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Em411\\SyliusFeatureFlagPlugin\\Tests\\": "tests/" }
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "config": {
        "preferred-install": "dist",
        "sort-packages": true
    }
}
```

- [ ] **Step 2: Resolve dependencies in Docker**

```bash
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer \
  -v "$(pwd)":/app -w /app sylius-ff-php74 \
  composer update --no-progress --prefer-stable
```

Expected: dependencies resolve and `vendor/` is created. Sylius packages resolve on the 1.x line and `em411/feature-flag-bundle` resolves to `1.0.0` from the VCS repository.

If `composer update` reports a RESOLUTION conflict, report BLOCKED with the full output. Likely-safe adjustments if needed (and only if a conflict actually occurs): widen `sylius/resource-bundle` / `sylius/grid-bundle` / `sylius/ui-bundle` constraints to the version composer indicates is compatible with PHP 7.4, or adjust `doctrine/orm` within `^2.x`. Do not change `em411/feature-flag-bundle` or the `symfony/*` `^4.4` constraints.

- [ ] **Step 3: Commit**

```bash
git add composer.json
git commit -m "build: composer.json for the Sylius 1.8 / PHP 7.4 plugin"
```

---

## Task 3: The `FeatureFlag` entity and ORM mapping

**Files:**
- Create: `src/Entity/FeatureFlag.php`, `src/Resources/config/doctrine/FeatureFlag.orm.xml`
- Test: `tests/Entity/FeatureFlagTest.php`

- [ ] **Step 1: Write the failing test `tests/Entity/FeatureFlagTest.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\Tests\Entity;

use Em411\SyliusFeatureFlagPlugin\Entity\FeatureFlag;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Resource\Model\ResourceInterface;

class FeatureFlagTest extends TestCase
{
    public function testItIsASyliusResource(): void
    {
        $this->assertInstanceOf(ResourceInterface::class, new FeatureFlag());
    }

    public function testItIsDisabledByDefault(): void
    {
        $flag = new FeatureFlag();

        $this->assertNull($flag->getId());
        $this->assertNull($flag->getCode());
        $this->assertFalse($flag->isEnabled());
        $this->assertNull($flag->getValue());
        $this->assertNull($flag->getDescription());
    }

    public function testItExposesItsProperties(): void
    {
        $flag = new FeatureFlag();
        $flag->setCode('beta_checkout');
        $flag->setEnabled(true);
        $flag->setValue('wholesale,vip');
        $flag->setDescription('Beta checkout for selected groups');

        $this->assertSame('beta_checkout', $flag->getCode());
        $this->assertTrue($flag->isEnabled());
        $this->assertSame('wholesale,vip', $flag->getValue());
        $this->assertSame('Beta checkout for selected groups', $flag->getDescription());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 vendor/bin/phpunit tests/Entity/FeatureFlagTest.php`
Expected: FAIL — `FeatureFlag` class does not exist.

- [ ] **Step 3: Create `src/Entity/FeatureFlag.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\Entity;

use Sylius\Component\Resource\Model\ResourceInterface;

class FeatureFlag implements ResourceInterface
{
    /** @var int|null */
    private $id;

    /** @var string|null */
    private $code;

    /** @var bool */
    private $enabled = false;

    /** @var string|null */
    private $value;

    /** @var string|null */
    private $description;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}
```

- [ ] **Step 4: Create `src/Resources/config/doctrine/FeatureFlag.orm.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                  https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <entity name="Em411\SyliusFeatureFlagPlugin\Entity\FeatureFlag" table="em411_feature_flag">
        <id name="id" type="integer">
            <generator strategy="AUTO"/>
        </id>
        <field name="code" type="string" length="255" unique="true"/>
        <field name="enabled" type="boolean"/>
        <field name="value" type="text" nullable="true"/>
        <field name="description" type="string" length="255" nullable="true"/>
    </entity>
</doctrine-mapping>
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 vendor/bin/phpunit tests/Entity/FeatureFlagTest.php`
Expected: PASS — `OK (3 tests, ...)`.

- [ ] **Step 6: Commit**

```bash
git add src/Entity/FeatureFlag.php src/Resources/config/doctrine/FeatureFlag.orm.xml tests/Entity/FeatureFlagTest.php
git commit -m "feat: add the FeatureFlag entity and ORM mapping"
```

---

## Task 4: The `DoctrineFeatureFlagProvider`

**Files:**
- Create: `src/Provider/DoctrineFeatureFlagProvider.php`
- Test: `tests/Provider/DoctrineFeatureFlagProviderTest.php`

- [ ] **Step 1: Write the failing test `tests/Provider/DoctrineFeatureFlagProviderTest.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\Tests\Provider;

use Doctrine\Persistence\ObjectRepository;
use Em411\SyliusFeatureFlagPlugin\Entity\FeatureFlag;
use Em411\SyliusFeatureFlagPlugin\Provider\DoctrineFeatureFlagProvider;
use PHPUnit\Framework\TestCase;

class DoctrineFeatureFlagProviderTest extends TestCase
{
    private function flag(string $code, bool $enabled, ?string $value = null): FeatureFlag
    {
        $flag = new FeatureFlag();
        $flag->setCode($code);
        $flag->setEnabled($enabled);
        $flag->setValue($value);

        return $flag;
    }

    /**
     * @param FeatureFlag[] $flags
     */
    private function provider(array $flags, ?int &$queryCount = null): DoctrineFeatureFlagProvider
    {
        $queryCount = 0;
        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findAll')->willReturnCallback(static function () use ($flags, &$queryCount) {
            ++$queryCount;

            return $flags;
        });

        return new DoctrineFeatureFlagProvider($repository);
    }

    public function testDisabledFlagResolvesToFalse(): void
    {
        $provider = $this->provider([$this->flag('a', false, 'ignored')]);
        $feature = $provider->get('a');

        $this->assertIsCallable($feature);
        $this->assertFalse($feature());
    }

    public function testEnabledFlagWithoutValueResolvesToTrue(): void
    {
        $provider = $this->provider([$this->flag('a', true)]);

        $this->assertTrue($provider->get('a')());
    }

    public function testEnabledFlagWithEmptyValueResolvesToTrue(): void
    {
        $provider = $this->provider([$this->flag('a', true, '')]);

        $this->assertTrue($provider->get('a')());
    }

    public function testEnabledFlagWithValueResolvesToTheValue(): void
    {
        $provider = $this->provider([$this->flag('a', true, 'wholesale,vip')]);

        $this->assertSame('wholesale,vip', $provider->get('a')());
    }

    public function testUnknownFlagReturnsNull(): void
    {
        $provider = $this->provider([$this->flag('a', true)]);

        $this->assertNull($provider->get('unknown'));
    }

    public function testItQueriesTheRepositoryOnlyOncePerRequest(): void
    {
        $provider = $this->provider([$this->flag('a', true), $this->flag('b', false)], $queryCount);

        $provider->get('a');
        $provider->get('b');
        $provider->get('unknown');

        $this->assertSame(1, $queryCount);
    }

    public function testResetForcesAReload(): void
    {
        $provider = $this->provider([$this->flag('a', true)], $queryCount);

        $provider->get('a');
        $provider->reset();
        $provider->get('a');

        $this->assertSame(2, $queryCount);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 vendor/bin/phpunit tests/Provider/DoctrineFeatureFlagProviderTest.php`
Expected: FAIL — `DoctrineFeatureFlagProvider` class does not exist.

- [ ] **Step 3: Create `src/Provider/DoctrineFeatureFlagProvider.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\Provider;

use Ajgarlag\FeatureFlagBundle\Provider\ProviderInterface;
use Doctrine\Persistence\ObjectRepository;
use Em411\SyliusFeatureFlagPlugin\Entity\FeatureFlag;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Provides feature flags stored as FeatureFlag entities and managed in the Sylius admin.
 *
 * All flags are loaded once per request; resolution of a flag to its value:
 *   - disabled                    -> false
 *   - enabled, no value           -> true
 *   - enabled, non-empty value    -> the value string
 */
final class DoctrineFeatureFlagProvider implements ProviderInterface, ResetInterface
{
    /**
     * @var ObjectRepository
     */
    private $repository;

    /**
     * @var array<string, FeatureFlag>|null
     */
    private $flags;

    public function __construct(ObjectRepository $repository)
    {
        $this->repository = $repository;
    }

    public function get(string $featureName): ?callable
    {
        if (null === $this->flags) {
            $this->flags = [];
            foreach ($this->repository->findAll() as $flag) {
                $this->flags[$flag->getCode()] = $flag;
            }
        }

        if (!isset($this->flags[$featureName])) {
            return null;
        }

        $flag = $this->flags[$featureName];

        return static function () use ($flag) {
            if (!$flag->isEnabled()) {
                return false;
            }

            $value = $flag->getValue();

            return (null === $value || '' === $value) ? true : $value;
        };
    }

    public function reset(): void
    {
        $this->flags = null;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 vendor/bin/phpunit tests/Provider/DoctrineFeatureFlagProviderTest.php`
Expected: PASS — `OK (7 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add src/Provider/DoctrineFeatureFlagProvider.php tests/Provider/DoctrineFeatureFlagProviderTest.php
git commit -m "feat: add the Doctrine-backed feature flag provider"
```

---

## Task 5: The `FeatureFlagType` form

**Files:**
- Create: `src/Form/Type/FeatureFlagType.php`

- [ ] **Step 1: Create `src/Form/Type/FeatureFlagType.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class FeatureFlagType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'em411_sylius_feature_flag.form.code',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
            ->add('value', TextareaType::class, [
                'label' => 'em411_sylius_feature_flag.form.value',
                'required' => false,
            ])
            ->add('description', TextType::class, [
                'label' => 'em411_sylius_feature_flag.form.description',
                'required' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'em411_sylius_feature_flag_feature_flag';
    }
}
```

`AbstractResourceType` (from SyliusResourceBundle) supplies `configureOptions()` — it sets `data_class` from the constructor's first argument. The service definition (Task 9) passes the `FeatureFlag` class and an empty validation-groups array.

- [ ] **Step 2: Lint**

Run: `docker run --rm -v "$(pwd)":/app -w /app sylius-ff-php74 php -l src/Form/Type/FeatureFlagType.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add src/Form/Type/FeatureFlagType.php
git commit -m "feat: add the FeatureFlag form type"
```

---

## Task 6: The `AdminMenuListener`

**Files:**
- Create: `src/Menu/AdminMenuListener.php`
- Test: `tests/Menu/AdminMenuListenerTest.php`

- [ ] **Step 1: Write the failing test `tests/Menu/AdminMenuListenerTest.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\Tests\Menu;

use Em411\SyliusFeatureFlagPlugin\Menu\AdminMenuListener;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

class AdminMenuListenerTest extends TestCase
{
    public function testItAddsAFeatureFlagsItemUnderConfiguration(): void
    {
        $factory = new MenuFactory();
        $menu = $factory->createItem('root');
        $menu->addChild('configuration');

        $listener = new AdminMenuListener();
        $listener->addAdminMenuItems(new MenuBuilderEvent($factory, $menu));

        $item = $menu->getChild('configuration')->getChild('feature_flags');
        $this->assertNotNull($item);
        $this->assertSame('em411_sylius_feature_flag_admin_feature_flag_index', $item->getExtra('routes')[0]['route'] ?? $item->getUri());
    }

    public function testItDoesNothingWhenThereIsNoConfigurationSection(): void
    {
        $factory = new MenuFactory();
        $menu = $factory->createItem('root');

        $listener = new AdminMenuListener();
        $listener->addAdminMenuItems(new MenuBuilderEvent($factory, $menu));

        $this->assertCount(0, $menu->getChildren());
    }
}
```

Note on the assertion: the listener builds the item with `'route' => '...'`; KnpMenu stores it as the item's URI via the routing extension only when a router is present. In this unit test there is no router, so the item is created with the route name available through `getExtra('routes')` is not set either — instead assert on the child existing and its name. Simplify the first test's final assertion to just verify the child exists and is labelled:

Replace the first test's last two lines with:

```php
        $item = $menu->getChild('configuration')->getChild('feature_flags');
        $this->assertNotNull($item);
        $this->assertSame('em411_sylius_feature_flag.ui.feature_flags', $item->getLabel());
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 vendor/bin/phpunit tests/Menu/AdminMenuListenerTest.php`
Expected: FAIL — `AdminMenuListener` class does not exist.

- [ ] **Step 3: Create `src/Menu/AdminMenuListener.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $configuration = $event->getMenu()->getChild('configuration');

        if (null === $configuration) {
            return;
        }

        $configuration
            ->addChild('feature_flags', ['route' => 'em411_sylius_feature_flag_admin_feature_flag_index'])
            ->setLabel('em411_sylius_feature_flag.ui.feature_flags')
            ->setLabelAttribute('icon', 'flag')
        ;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 vendor/bin/phpunit tests/Menu/AdminMenuListenerTest.php`
Expected: PASS — `OK (2 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add src/Menu/AdminMenuListener.php tests/Menu/AdminMenuListenerTest.php
git commit -m "feat: add the admin menu listener"
```

---

## Task 7: The bundle class

**Files:**
- Create: `src/Em411SyliusFeatureFlagPlugin.php`

- [ ] **Step 1: Create `src/Em411SyliusFeatureFlagPlugin.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class Em411SyliusFeatureFlagPlugin extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(DoctrineOrmMappingsPass::createXmlMappingDriver(
            [__DIR__.'/Resources/config/doctrine' => 'Em411\SyliusFeatureFlagPlugin\Entity'],
            []
        ));
    }
}
```

`DoctrineOrmMappingsPass::createXmlMappingDriver()` (from `doctrine/doctrine-bundle`) registers the `Resources/config/doctrine/` directory as a simplified-XML mapping driver for the `Entity` namespace, so the host application needs no `doctrine.orm.mappings` configuration. The simplified driver expects `FeatureFlag.orm.xml` (short class name) — which is what Task 3 created.

- [ ] **Step 2: Lint**

Run: `docker run --rm -v "$(pwd)":/app -w /app sylius-ff-php74 php -l src/Em411SyliusFeatureFlagPlugin.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add src/Em411SyliusFeatureFlagPlugin.php
git commit -m "feat: add the plugin bundle class with the ORM mapping pass"
```

---

## Task 8: Configuration and the DI extension

**Files:**
- Create: `src/DependencyInjection/Configuration.php`, `src/DependencyInjection/Em411SyliusFeatureFlagPluginExtension.php`

- [ ] **Step 1: Create `src/DependencyInjection/Configuration.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('em411_sylius_feature_flag_plugin');

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('provider_priority')
                    ->info('Priority of the Doctrine provider in the feature flag chain. Higher wins; a value above the code-defined providers (0) lets DB flags override them.')
                    ->defaultValue(100)
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
```

- [ ] **Step 2: Create `src/DependencyInjection/Em411SyliusFeatureFlagPluginExtension.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\DependencyInjection;

use Em411\SyliusFeatureFlagPlugin\Entity\FeatureFlag;
use Em411\SyliusFeatureFlagPlugin\Form\Type\FeatureFlagType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class Em411SyliusFeatureFlagPluginExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter(
            'em411_sylius_feature_flag_plugin.provider_priority',
            $config['provider_priority']
        );

        $loader = new XmlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('services.xml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('sylius_resource')) {
            $container->prependExtensionConfig('sylius_resource', [
                'resources' => [
                    'em411_sylius_feature_flag.feature_flag' => [
                        'classes' => [
                            'model' => FeatureFlag::class,
                            'form' => FeatureFlagType::class,
                        ],
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('sylius_grid')) {
            $container->prependExtensionConfig('sylius_grid', [
                'grids' => [
                    'em411_sylius_feature_flag_admin_feature_flag' => [
                        'driver' => [
                            'name' => 'doctrine/orm',
                            'options' => ['class' => FeatureFlag::class],
                        ],
                        'fields' => [
                            'code' => [
                                'type' => 'string',
                                'label' => 'em411_sylius_feature_flag.ui.code',
                                'sortable' => 'code',
                            ],
                            'enabled' => [
                                'type' => 'twig',
                                'label' => 'sylius.ui.enabled',
                                'options' => ['template' => '@SyliusUi/Grid/Field/yesNo.html.twig'],
                            ],
                            'description' => [
                                'type' => 'string',
                                'label' => 'em411_sylius_feature_flag.ui.description',
                            ],
                        ],
                        'filters' => [
                            'code' => ['type' => 'string'],
                            'enabled' => ['type' => 'boolean'],
                        ],
                        'actions' => [
                            'main' => [
                                'create' => ['type' => 'create'],
                            ],
                            'item' => [
                                'update' => ['type' => 'update'],
                                'delete' => ['type' => 'delete'],
                            ],
                        ],
                    ],
                ],
            ]);
        }
    }
}
```

The extension alias is derived from the class name → `em411_sylius_feature_flag_plugin`, matching `Configuration`'s tree root. `load()` runs after all `prepend()`s, so the `provider_priority` parameter is set before service tags are resolved.

- [ ] **Step 3: Lint both files**

```bash
docker run --rm -v "$(pwd)":/app -w /app sylius-ff-php74 sh -c \
  'php -l src/DependencyInjection/Configuration.php && php -l src/DependencyInjection/Em411SyliusFeatureFlagPluginExtension.php'
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add src/DependencyInjection
git commit -m "feat: add the DI extension and semantic configuration"
```

---

## Task 9: The service definitions

**Files:**
- Create: `src/Resources/config/services.xml`

- [ ] **Step 1: Create `src/Resources/config/services.xml`**

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services https://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="em411_sylius_feature_flag.provider.doctrine"
                 class="Em411\SyliusFeatureFlagPlugin\Provider\DoctrineFeatureFlagProvider">
            <argument type="service" id="em411_sylius_feature_flag.repository.feature_flag"/>
            <tag name="ajgarlag.feature_flag.provider"
                 priority="%em411_sylius_feature_flag_plugin.provider_priority%"/>
            <tag name="kernel.reset" method="reset"/>
        </service>

        <service id="em411_sylius_feature_flag.form.type.feature_flag"
                 class="Em411\SyliusFeatureFlagPlugin\Form\Type\FeatureFlagType">
            <argument>Em411\SyliusFeatureFlagPlugin\Entity\FeatureFlag</argument>
            <argument type="collection"/>
            <tag name="form.type"/>
        </service>

        <service id="em411_sylius_feature_flag.listener.admin_menu"
                 class="Em411\SyliusFeatureFlagPlugin\Menu\AdminMenuListener">
            <tag name="kernel.event_listener" event="sylius.menu.admin.main" method="addAdminMenuItems"/>
        </service>
    </services>
</container>
```

Notes:
- `em411_sylius_feature_flag.repository.feature_flag` is the repository service auto-created by SyliusResourceBundle from the resource registered in Task 8's `prepend()`.
- The provider's `ajgarlag.feature_flag.provider` tag carries `priority` resolved from the parameter set by the extension; `em411/feature-flag-bundle`'s `ChainProvider` receives providers as a priority-sorted iterator, so a high value makes DB flags win.
- The form type's first argument is the resource's data class; the second (empty collection) is the validation-groups array `AbstractResourceType` expects.

- [ ] **Step 2: Validate the XML is well-formed**

```bash
docker run --rm -v "$(pwd)":/app -w /app sylius-ff-php74 \
  php -r 'exit(simplexml_load_file("src/Resources/config/services.xml") === false ? 1 : 0);' && echo "services.xml OK"
```

Expected: `services.xml OK`.

- [ ] **Step 3: Commit**

```bash
git add src/Resources/config/services.xml
git commit -m "feat: wire the provider, form type and menu listener services"
```

---

## Task 10: The admin routing

**Files:**
- Create: `src/Resources/config/routing/admin.yaml`

- [ ] **Step 1: Create `src/Resources/config/routing/admin.yaml`**

```yaml
em411_sylius_feature_flag_admin_feature_flag:
    resource: |
        alias: em411_sylius_feature_flag.feature_flag
        section: admin
        templates: "@SyliusAdmin/Crud"
        redirect: update
        grid: em411_sylius_feature_flag_admin_feature_flag
        permission: false
    type: sylius.resource
```

This `sylius.resource` route expands to `_index`, `_create`, `_update` and `_delete` routes for the resource, reusing Sylius admin's generic CRUD templates. The host application imports this file under its `/admin` prefix (documented in the README — Task 13).

- [ ] **Step 2: Verify the YAML parses**

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 \
  php -r 'require "vendor/autoload.php"; Symfony\Component\Yaml\Yaml::parseFile("src/Resources/config/routing/admin.yaml"); echo "routing OK\n";'
```

Expected: `routing OK`.

- [ ] **Step 3: Commit**

```bash
git add src/Resources/config/routing/admin.yaml
git commit -m "feat: add the admin resource routing"
```

---

## Task 11: Translations

**Files:**
- Create: `src/Resources/translations/messages.en.yaml`

- [ ] **Step 1: Create `src/Resources/translations/messages.en.yaml`**

```yaml
em411_sylius_feature_flag:
    ui:
        feature_flags: Feature flags
        code: Code
        description: Description
    form:
        code: Code
        value: Value
        description: Description
```

The `sylius.ui.enabled` label used by the grid and form is provided by Sylius core translations.

- [ ] **Step 2: Verify the YAML parses**

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 \
  php -r 'require "vendor/autoload.php"; Symfony\Component\Yaml\Yaml::parseFile("src/Resources/translations/messages.en.yaml"); echo "translations OK\n";'
```

Expected: `translations OK`.

- [ ] **Step 3: Commit**

```bash
git add src/Resources/translations/messages.en.yaml
git commit -m "feat: add English translations"
```

---

## Task 12: Integration test — container compiles, schema builds, provider wired

**Files:**
- Create: `tests/Integration/TestKernel.php`, `tests/Integration/IntegrationTest.php`
- Modify: `phpunit.xml.dist` (created here)

This is the iteration-heavy task: it boots a real Symfony kernel with the Sylius resource/grid bundles. The success criteria are: the container compiles, the `FeatureFlag` ORM schema can be created (proving the mapping pass works), and the `DoctrineFeatureFlagProvider` is registered in `em411/feature-flag-bundle`'s provider chain.

- [ ] **Step 1: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
    backupGlobals="false"
    colors="true"
    bootstrap="vendor/autoload.php"
    convertDeprecationsToExceptions="false"
    failOnRisky="true"
    failOnWarning="true"
>
    <php>
        <ini name="error_reporting" value="-1"/>
        <env name="KERNEL_DEBUG" value="0"/>
    </php>
    <testsuites>
        <testsuite name="Sylius FeatureFlag Plugin Test Suite">
            <directory>./tests/</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory>./src</directory>
        </include>
    </coverage>
</phpunit>
```

- [ ] **Step 2: Create `tests/Integration/TestKernel.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\Tests\Integration;

use Ajgarlag\FeatureFlagBundle\FeatureFlagBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Em411\SyliusFeatureFlagPlugin\Em411SyliusFeatureFlagPlugin;
use Sylius\Bundle\GridBundle\SyliusGridBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal kernel that boots the plugin alongside the Sylius resource/grid bundles.
 */
final class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new DoctrineBundle(),
            new SyliusResourceBundle(),
            new SyliusGridBundle(),
            new FeatureFlagBundle(),
            new Em411SyliusFeatureFlagPlugin(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'form' => true,
                'csrf_protection' => false,
                'translator' => ['fallbacks' => ['en']],
                'property_access' => null,
                'session' => ['storage_id' => 'session.storage.mock_file'],
                'router' => ['utf8' => true],
            ]);

            $container->loadFromExtension('twig', [
                'strict_variables' => true,
            ]);

            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'url' => 'sqlite:///:memory:',
                ],
                'orm' => [
                    'auto_generate_proxy_classes' => true,
                ],
            ]);

            $container->loadFromExtension('sylius_resource', []);
            $container->loadFromExtension('sylius_grid', []);
        });
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/em411_sylius_feature_flag_plugin/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/em411_sylius_feature_flag_plugin/log';
    }
}
```

- [ ] **Step 3: Create `tests/Integration/IntegrationTest.php`**

```php
<?php

namespace Em411\SyliusFeatureFlagPlugin\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Em411\SyliusFeatureFlagPlugin\Entity\FeatureFlag;
use Em411\SyliusFeatureFlagPlugin\Provider\DoctrineFeatureFlagProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class IntegrationTest extends TestCase
{
    /** @var TestKernel */
    private $kernel;

    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/em411_sylius_feature_flag_plugin');

        $this->kernel = new TestKernel('test', false);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove(sys_get_temp_dir().'/em411_sylius_feature_flag_plugin');
    }

    public function testTheContainerCompilesAndRegistersThePluginServices(): void
    {
        $container = $this->kernel->getContainer();

        $this->assertTrue($container->has('em411_sylius_feature_flag.provider.doctrine'));
        $this->assertTrue($container->has('em411_sylius_feature_flag.repository.feature_flag'));
        $this->assertTrue($container->has('em411_sylius_feature_flag.listener.admin_menu'));
    }

    public function testTheFeatureFlagOrmMappingProducesACreatableSchema(): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->kernel->getContainer()->get('doctrine')->getManager();

        $metadata = $em->getClassMetadata(FeatureFlag::class);
        $this->assertSame('em411_feature_flag', $metadata->getTableName());

        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema([$metadata]);

        // Round-trip a row to prove the mapping is usable.
        $flag = new FeatureFlag();
        $flag->setCode('beta');
        $flag->setEnabled(true);
        $flag->setValue('vip');
        $em->persist($flag);
        $em->flush();
        $em->clear();

        $loaded = $em->getRepository(FeatureFlag::class)->findOneBy(['code' => 'beta']);
        $this->assertNotNull($loaded);
        $this->assertTrue($loaded->isEnabled());
        $this->assertSame('vip', $loaded->getValue());
    }

    public function testTheDoctrineProviderIsRegisteredInTheFeatureFlagChain(): void
    {
        $container = $this->kernel->getContainer();

        $provider = $container->get('em411_sylius_feature_flag.provider.doctrine');
        $this->assertInstanceOf(DoctrineFeatureFlagProvider::class, $provider);

        // The feature checker resolves through the chain that includes our provider.
        $checker = $container->get('ajgarlag.feature_flag.feature_checker');
        $this->assertFalse($checker->isEnabled('a_flag_that_does_not_exist'));
    }
}
```

The third test fetches `ajgarlag.feature_flag.feature_checker`; that service is private in `em411/feature-flag-bundle`'s config. `framework.test: true` enables the test service container, which exposes private services via `$container->get()` in the `test` environment — so the fetch works without extra aliases.

- [ ] **Step 4: Run the integration test in Docker**

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 \
  vendor/bin/phpunit tests/Integration/IntegrationTest.php
```

Expected: `OK (3 tests, ...)`.

**Iteration guidance — this is the task most likely to need adjustment.** If the kernel fails to boot or the container fails to compile, the error almost always names a missing extension config key or an unsatisfied service dependency. Permitted adjustments, in order of preference:

1. **Add framework features the Sylius bundles need** to the `framework` extension config in `TestKernel.php` (e.g. `assets`, `mailer`, `validation` → `['enabled' => true]`, `serializer`, `fragments`). Add only what an error explicitly demands.
2. **Add `sylius_resource` / `sylius_grid` extension config** if those bundles require options beyond the prepended `resources` / `grids`. Their config schemas have sane defaults; only add what an error names.
3. **Register an additional bundle** in `registerBundles()` if a Sylius bundle hard-depends on it (for example `winzou/state-machine-bundle`'s `winzou\Bundle\StateMachineBundle\winzouStateMachineBundle`, if SyliusResourceBundle requires it). Add the matching Composer package to `require-dev` if it is not already installed, and re-run `composer update`.
4. If, after a genuine effort, the Sylius resource CRUD controller cannot be made to compile in this kernel, narrow the test: keep `testTheFeatureFlagOrmMappingProducesACreatableSchema` and `testTheDoctrineProviderIsRegisteredInTheFeatureFlagChain` as the must-pass cases, and report the resource-controller compilation difficulty as a DONE_WITH_CONCERNS note so the controller can decide. Do **not** delete tests silently.

Re-run the command after each adjustment. Record every change made to `TestKernel.php` / `composer.json` in the task report.

- [ ] **Step 5: Run the full test suite**

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 vendor/bin/phpunit
```

Expected: every test green — the entity, provider, menu-listener and integration tests.

- [ ] **Step 6: Commit**

```bash
git add phpunit.xml.dist tests/Integration composer.json
git commit -m "test: add the integration test (container, schema, provider wiring)"
```

(`composer.json` is included only if Step 4's iteration added a dev dependency.)

---

## Task 13: CI, PHP-CS-Fixer, and README

**Files:**
- Create: `.github/workflows/ci.yml`, `.php-cs-fixer.dist.php`, `README.md`

- [ ] **Step 1: Create `.github/workflows/ci.yml`**

```yaml
name: CI

on:
  pull_request: ~
  push: ~
  workflow_dispatch: ~

jobs:
  tests:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['7.4']

    name: PHP ${{ matrix.php }}

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none
          extensions: :xdebug, pdo_sqlite
          tools: php-cs-fixer

      - name: Get Composer Cache Directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - uses: actions/cache@v4
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: composer-${{ matrix.php }}-${{ hashFiles('**/composer.json') }}
          restore-keys: |
            composer-${{ matrix.php }}-

      - run: composer update --no-progress --prefer-stable

      - run: vendor/bin/phpunit

      - run: php-cs-fixer check --diff
```

Only PHP 7.4 is in the matrix — Sylius 1.8's supported runtime. PHP-CS-Fixer is installed by `setup-php` as a standalone tool (it cannot be a Composer dependency on a Symfony 4.4 stack).

- [ ] **Step 2: Create `.php-cs-fixer.dist.php`**

```php
<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP74Migration' => true,
        'header_comment' => ['header' => ''],
    ])
    ->setFinder($finder)
;
```

- [ ] **Step 3: Create `README.md`**

```markdown
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
```

- [ ] **Step 4: Verify php-cs-fixer is clean in Docker**

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 sh -c '
  php -r "copy(\"https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/download/v3.64.0/php-cs-fixer.phar\", \"/tmp/php-cs-fixer.phar\");" \
  && php /tmp/php-cs-fixer.phar check --diff'
```

Expected: `Found 0 of N files that can be fixed`. If files are flagged, apply the fixer (`fix` instead of `check --diff`), review with `git diff`, re-run the full suite to confirm `OK`, and include the fixes in the commit. If the PHAR download fails in the sandbox, skip — CI verifies php-cs-fixer.

- [ ] **Step 5: Commit**

```bash
git add .github/workflows/ci.yml .php-cs-fixer.dist.php README.md
git commit -m "ci: add CI workflow, php-cs-fixer config and README"
```

---

## Task 14: Full verification and push

**Files:** none.

- [ ] **Step 1: Clean re-resolve and full suite in Docker**

```bash
rm -rf vendor composer.lock
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer \
  -v "$(pwd)":/app -w /app sylius-ff-php74 \
  composer update --no-progress --prefer-stable
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app sylius-ff-php74 \
  vendor/bin/phpunit
```

Expected: composer resolves cleanly; PHPUnit prints `OK` for the whole suite. Both must pass before pushing — if either fails, report BLOCKED with the output.

- [ ] **Step 2: Push to the fork**

```bash
git status --short
git branch -M main
git push -u origin main
```

`git status --short` must be empty. Expected: `main` is created on `em411/sylius-feature-flag-bundle-plugin`.

- [ ] **Step 3: Confirm GitHub Actions CI is green**

```bash
sleep 15
gh run watch "$(gh run list --repo em411/sylius-feature-flag-bundle-plugin --branch main --limit 1 --json databaseId --jq '.[0].databaseId')" --repo em411/sylius-feature-flag-bundle-plugin --exit-status
```

Expected: the `PHP 7.4` job succeeds. If it fails, read `gh run view --repo em411/sylius-feature-flag-bundle-plugin --log-failed`, fix the cause, commit, push, and re-watch. If the failure is a non-trivial Sylius wiring problem, report DONE_WITH_CONCERNS with the log and analysis.

---

## Self-Review

**Spec coverage** — every spec section maps to a task:
- Names / package → Tasks 1, 2.
- `FeatureFlag` entity (code, enabled, value, description) + XML mapping → Task 3.
- Provider behaviour & value semantics (false / true / value rule, one query/request, `reset()`) → Task 4.
- Group targeting (app-side, documented) → Task 13 (README).
- Provider chaining & configurable priority → Task 8 (Configuration, priority parameter) + Task 9 (tag).
- Sylius resource registration → Task 8 (`prepend` `sylius_resource`).
- Grid → Task 8 (`prepend` `sylius_grid`).
- Form → Task 5 + Task 9 (service).
- Routing → Task 10.
- Admin menu → Task 6 + Task 9 (service).
- Translations → Task 11.
- Doctrine mapping registration → Task 7 (`build()` mapping pass).
- Host application setup → Task 13 (README).
- Dependencies → Task 2.
- Testing (unit + integration) → Tasks 3, 4, 6, 12.
- CI / Docker / php-cs-fixer → Tasks 1, 13, 14.
- Out-of-scope items are not implemented (no native per-user evaluation, no Behat, no migration shipped).

Refinement vs spec: bundle resources live under `src/Resources/` (not repo-root `Resources/`) so they resolve against the bundle's `getPath()`; the grid configuration is prepended as a PHP array inside the extension rather than a separate `grids/*.yaml` file. Both are noted here and do not change behaviour.

**Placeholder scan:** no `TBD`/`TODO`/"similar to" — every code step has complete content. Task 12's iteration guidance gives concrete, bounded adjustment options rather than "handle errors."

**Type consistency:** `DoctrineFeatureFlagProvider::__construct(ObjectRepository)` matches the service definition (injecting the Sylius repository, an `ObjectRepository`) and the unit test (mocking `ObjectRepository`). The provider's value rule (`false` / `true` / string) is identical in the spec, the provider code, and the test assertions. Service id `em411_sylius_feature_flag.provider.doctrine`, resource name `em411_sylius_feature_flag.feature_flag`, grid/route name `em411_sylius_feature_flag_admin_feature_flag`, config root `em411_sylius_feature_flag_plugin`, and the priority parameter name are consistent across Tasks 8, 9, 10 and 12. `FeatureFlag` getters/setters used in Tasks 4, 9 and 12 match the entity defined in Task 3.
