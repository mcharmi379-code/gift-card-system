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
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $productId = $lineItem->getReferencedId();
            if ($productId === null) {
                continue;
            }

            // Check if this product is a gift card product
            $giftCard = $this->findGiftCardByProductId($productId);
            if ($giftCard === null) {
                continue;
            }

            // 1. If plugin is not active for this sales channel, remove line item
            if (!$active) {
                $cart->getLineItems()->remove($lineItem->getId());
                continue;
            }

            // 2. If gift card template is not selected, remove the line item and add an error
            $payload = $lineItem->getPayload();
            $templateId = $payload['giftCardTemplateId'] ?? null;
            if (!$templateId || !\is_string($templateId) || \trim($templateId) === '') {
                $cart->getLineItems()->remove($lineItem->getId());
                $errors->add(new GiftCardTemplateRequiredError($lineItem->getId()));
            }
        }
    }

    private function findGiftCardByProductId(string $productId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) AS id FROM ictech_gift_card WHERE product_id = UNHEX(:productId) LIMIT 1',
            ['productId' => $productId]
        );
        return $row !== false ? $row : null;
    }
}
