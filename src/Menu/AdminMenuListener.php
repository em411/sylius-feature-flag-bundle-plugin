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
