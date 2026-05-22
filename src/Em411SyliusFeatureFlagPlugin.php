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
