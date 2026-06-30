<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardTemplate\Aggregate\GiftCardTemplateTranslation;

use ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

final class GiftCardTemplateTranslationEntity extends TranslationEntity
{
    protected ?string $ictechGiftCardTemplateId = null;

    protected string $name = '';

    protected string $tag = '';

    protected ?GiftCardTemplateEntity $ictechGiftCardTemplate = null;

    public function getIctechGiftCardTemplateId(): ?string
    {
        return $this->ictechGiftCardTemplateId;
    }

    public function setIctechGiftCardTemplateId(string $ictechGiftCardTemplateId): void
    {
        $this->ictechGiftCardTemplateId = $ictechGiftCardTemplateId;
    }

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

    public function getIctechGiftCardTemplate(): ?GiftCardTemplateEntity
    {
        return $this->ictechGiftCardTemplate;
    }

    public function setIctechGiftCardTemplate(GiftCardTemplateEntity $ictechGiftCardTemplate): void
    {
        $this->ictechGiftCardTemplate = $ictechGiftCardTemplate;
    }
}
