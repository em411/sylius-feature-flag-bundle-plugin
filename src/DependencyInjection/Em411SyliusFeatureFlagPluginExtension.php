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
