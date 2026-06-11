<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCard;

use ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateEntity;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class GiftCardEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $name = '';

    protected float $amount = 0.0;

    protected string $codePrefix = '';

    protected int $validityDays = 365;

    protected ?int $quantity = null;

    protected int $quantityIssued = 0;

    protected bool $active = true;

    protected ?string $salesChannelId = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?string $mediaId = null;

    protected ?MediaEntity $media = null;

    protected ?string $templateId = null;

    protected ?GiftCardTemplateEntity $template = null;

    protected ?string $productId = null;

    protected ?string $productVersionId = null;

    protected ?ProductEntity $product = null;

    protected ?GiftCardVoucherCollection $vouchers = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getCodePrefix(): string
    {
        return $this->codePrefix;
    }

    public function setCodePrefix(string $codePrefix): void
    {
        $this->codePrefix = $codePrefix;
    }

    public function getValidityDays(): int
    {
        return $this->validityDays;
    }

    public function setValidityDays(int $validityDays): void
    {
        $this->validityDays = $validityDays;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getQuantityIssued(): int
    {
        return $this->quantityIssued;
    }

    public function setQuantityIssued(int $quantityIssued): void
    {
        $this->quantityIssued = $quantityIssued;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    public function setTemplateId(?string $templateId): void
    {
        $this->templateId = $templateId;
    }

    public function getTemplate(): ?GiftCardTemplateEntity
    {
        return $this->template;
    }

    public function setTemplate(?GiftCardTemplateEntity $template): void
    {
        $this->template = $template;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProductVersionId(): ?string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(?string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getVouchers(): ?GiftCardVoucherCollection
    {
        return $this->vouchers;
    }

    public function setVouchers(GiftCardVoucherCollection $vouchers): void
    {
        $this->vouchers = $vouchers;
    }
}
