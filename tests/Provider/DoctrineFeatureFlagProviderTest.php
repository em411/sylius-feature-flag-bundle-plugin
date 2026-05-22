<?php

namespace Em411\SyliusFeatureFlagPlugin\Tests\Provider;

use Doctrine\Persistence\ObjectRepository;
use Em411\SyliusFeatureFlagPlugin\Entity\FeatureFlag;
use Em411\SyliusFeatureFlagPlugin\Provider\DoctrineFeatureFlagProvider;
use PHPUnit\Framework\TestCase;

class DoctrineFeatureFlagProviderTest extends TestCase
{
    private function flag(?string $code, bool $enabled, ?string $value = null): FeatureFlag
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

    public function testItIgnoresFlagsWithoutACode(): void
    {
        $provider = $this->provider([$this->flag('a', true), $this->flag(null, true)]);

        $this->assertIsCallable($provider->get('a'));
        $this->assertNull($provider->get(''));
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
