<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Cart;

use ICTECHGiftCard\Core\Cart\Error\GiftCardTemplateRequiredError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class GiftCardCartValidator implements CartValidatorInterface
{
    /**
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCard\GiftCardCollection> $giftCardRepository
     */
    public function __construct(
        private readonly EntityRepository $giftCardRepository,
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
            $this->validateLineItem($lineItem, $cart, $errors, $active, $context->getContext());
        }
    }

    private function validateLineItem(
        LineItem $lineItem,
        Cart $cart,
        ErrorCollection $errors,
        bool $active,
        Context $context,
    ): void {
        if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return;
        }

        $productId = $lineItem->getReferencedId();
        if ($productId === null) {
            return;
        }

        $this->checkGiftCardLineItem($lineItem, $productId, $cart, $errors, $active, $context);
    }

    private function checkGiftCardLineItem(
        LineItem $lineItem,
        string $productId,
        Cart $cart,
        ErrorCollection $errors,
        bool $active,
        Context $context,
    ): void {
        $giftCard = $this->findGiftCardByProductId($productId, $context);
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
    private function findGiftCardByProductId(string $productId, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->setLimit(1);

        /** @var \ICTECHGiftCard\Core\Content\GiftCard\GiftCardEntity|null $giftCard */
        $giftCard = $this->giftCardRepository->search($criteria, $context)->first();
        if ($giftCard === null) {
            return null;
        }

        return [
            'id' => $giftCard->getId(),
        ];
    }
}
