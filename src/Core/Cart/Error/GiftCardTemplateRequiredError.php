<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

final class GiftCardTemplateRequiredError extends Error
{
    private const KEY = 'ictech-gift-card-template-required';

    public function __construct(private readonly string $lineItemId)
    {
        parent::__construct();
    }

    public function getId(): string
    {
        return self::KEY . '-' . $this->lineItemId;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return [];
    }
}
