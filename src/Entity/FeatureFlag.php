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
