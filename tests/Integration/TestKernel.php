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
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
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

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Make plugin and feature-flag services public so integration tests can fetch them from the container.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            private const PUBLIC_SERVICES = [
                'em411_sylius_feature_flag.provider.doctrine',
                'em411_sylius_feature_flag.repository.feature_flag',
                'em411_sylius_feature_flag.listener.admin_menu',
                'ajgarlag.feature_flag.feature_checker',
                'doctrine',
            ];

            public function process(ContainerBuilder $container): void
            {
                foreach (self::PUBLIC_SERVICES as $id) {
                    if ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setPublic(true);
                    } elseif ($container->hasAlias($id)) {
                        $container->getAlias($id)->setPublic(true);
                    }
                }
            }
        });
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'default_locale' => 'en',
                'form' => true,
                'csrf_protection' => false,
                'translator' => ['fallbacks' => ['en']],
                'property_access' => null,
                'session' => ['storage_id' => 'session.storage.mock_file'],
                'router' => ['resource' => 'kernel::loadRoutes', 'utf8' => true],
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

            // Sylius resource bundle's controller.xml references %locale%
            $container->setParameter('locale', 'en');
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
