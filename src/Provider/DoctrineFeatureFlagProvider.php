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
                $code = $flag->getCode();
                if (null === $code) {
                    continue;
                }
                $this->flags[$code] = $flag;
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
