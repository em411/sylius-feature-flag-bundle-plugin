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
