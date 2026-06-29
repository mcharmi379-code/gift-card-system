<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Subscriber;

use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

final class ProductDetailRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
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
        try {
            $productIdBin = \hex2bin($productIdHex);
        } catch (\Throwable) {
            return false;
        }

        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `ictech_gift_card` WHERE `product_id` = :productId LIMIT 1',
            ['productId' => $productIdBin]
        );
    }
}
