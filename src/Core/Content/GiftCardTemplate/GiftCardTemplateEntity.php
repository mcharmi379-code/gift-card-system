<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardTemplate;

use ICTECHGiftCard\Core\Content\GiftCard\GiftCardCollection;
use ICTECHGiftCard\Core\Content\GiftCardTemplate\Aggregate\GiftCardTemplateTranslation\GiftCardTemplateTranslationCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class GiftCardTemplateEntity extends Entity
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
        $name = \trim($this->name);
        if ($name !== '') {
            return $name;
        }

        $translations = $this->getTranslations();
        if ($translations !== null) {
            foreach ($translations as $translation) {
                $tName = \trim($translation->getName());
                if ($tName !== '') {
                    return $tName;
                }
            }
        }

        return '';
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTag(): string
    {
        $tag = \trim($this->tag);
        if ($tag !== '') {
            return $tag;
        }

        $translations = $this->getTranslations();
        if ($translations !== null) {
            foreach ($translations as $translation) {
                $tTag = \trim($translation->getTag());
                if ($tTag !== '') {
                    return $tTag;
                }
            }
        }

        return 'Various';
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

    /**
     * @return array<string, mixed>
     */
    public function getTranslated(): array
    {
        $translated = parent::getTranslated();

        if ($this->isFieldEmpty($translated, 'name')) {
            $translated['name'] = $this->getName();
        }

        if ($this->isFieldEmpty($translated, 'tag')) {
            $translated['tag'] = $this->getTag();
        }

        return $translated;
    }

    /**
     * @param array<string, mixed> $translated
     */
    private function isFieldEmpty(array $translated, string $field): bool
    {
        if (! isset($translated[$field])) {
            return true;
        }

        $val = $translated[$field];

        return ! \is_string($val) || \trim($val) === '';
    }
}
