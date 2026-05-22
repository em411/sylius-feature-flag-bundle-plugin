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
        $this->assertSame('em411_sylius_feature_flag.ui.feature_flags', $item->getLabel());
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
