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

        // Create the schema so the provider can query the table (in-memory SQLite is blank on each boot).
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema([$em->getClassMetadata(FeatureFlag::class)]);

        $checker = $container->get('ajgarlag.feature_flag.feature_checker');
        $this->assertFalse($checker->isEnabled('a_flag_that_does_not_exist'));
    }
}
