<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardTemplate;

use ICTECHGiftCard\Core\Content\GiftCard\GiftCardCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

use ICTECHGiftCard\Core\Content\GiftCardTemplate\Aggregate\GiftCardTemplateTranslation\GiftCardTemplateTranslationCollection;

class GiftCardTemplateEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $name = '';

    protected string $tag = '';

    protected ?string $mediaId = null;

    protected ?MediaEntity $media = null;

    protected bool $active = true;

    protected ?GiftCardCollection $giftCards = null;

    protected ?GiftCardTemplateTranslationCollection $translations = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function setTag(string $tag): void
    {
        $this->tag = $tag;
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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getGiftCards(): ?GiftCardCollection
    {
        return $this->giftCards;
    }

    public function setGiftCards(GiftCardCollection $giftCards): void
    {
        $this->giftCards = $giftCards;
    }

    public function getTranslations(): ?GiftCardTemplateTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(GiftCardTemplateTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
