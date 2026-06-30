<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

final class ProductDetailRedirectSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<\ICTECHGiftCard\Core\Content\GiftCard\GiftCardCollection> $giftCardRepository
     */
    public function __construct(
        private readonly EntityRepository $giftCardRepository,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if ($route !== 'frontend.detail.page') {
            return;
        }

        $this->handleDetailRedirect($event);
    }

    private function handleDetailRedirect(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $productIdHex = $request->attributes->get('productId');
        if (! \is_string($productIdHex) || $productIdHex === '') {
            return;
        }

        if ($this->isGiftCardProduct($productIdHex)) {
            $redirectUrl = $this->router->generate('frontend.ictech.gift_card.page');
            $event->setResponse(new RedirectResponse($redirectUrl));
        }
    }

    private function isGiftCardProduct(string $productIdHex): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productIdHex));
        $criteria->setLimit(1);

        try {
            return $this->giftCardRepository->searchIds($criteria, Context::createDefaultContext())->getTotal() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
