<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Cart;

use Doctrine\DBAL\Connection;
use ICTECHGiftCard\Core\Cart\Error\GiftCardTemplateRequiredError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class GiftCardCartValidator implements CartValidatorInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function validate(
        Cart $cart,
        ErrorCollection $errors,
        SalesChannelContext $context
    ): void {
        $active = $this->systemConfigService->getBool('ICTECHGiftCard.config.active', $context->getSalesChannelId());

        foreach ($cart->getLineItems() as $lineItem) {
            $this->validateLineItem($lineItem, $cart, $errors, $active);
        }
    }

    private function validateLineItem(
        LineItem $lineItem,
        Cart $cart,
        ErrorCollection $errors,
        bool $active,
    ): void {
        if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return;
        }

        $productId = $lineItem->getReferencedId();
        if ($productId === null) {
            return;
        }

        $this->checkGiftCardLineItem($lineItem, $productId, $cart, $errors, $active);
    }

    private function checkGiftCardLineItem(
        LineItem $lineItem,
        string $productId,
        Cart $cart,
        ErrorCollection $errors,
        bool $active,
    ): void {
        $giftCard = $this->findGiftCardByProductId($productId);
        if ($giftCard === null) {
            return;
        }

        if (! $active) {
            $cart->getLineItems()->remove($lineItem->getId());
            return;
        }

        $payload = $lineItem->getPayload();
        $templateId = $payload['giftCardTemplateId'] ?? null;
        if (! \is_string($templateId) || \trim($templateId) === '') {
            $cart->getLineItems()->remove($lineItem->getId());
            $errors->add(new GiftCardTemplateRequiredError($lineItem->getId()));
        }
    }

    /**
     * @return array{id: string}|null
     */
    private function findGiftCardByProductId(string $productId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) AS id FROM ictech_gift_card WHERE product_id = UNHEX(:productId) LIMIT 1',
            ['productId' => $productId]
        );
        if ($row === false) {
            return null;
        }
        $id = $row['id'] ?? '';
        return [
            'id' => \is_scalar($id) ? (string) $id : '',
        ];
    }
}
