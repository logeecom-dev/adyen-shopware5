<?php

declare(strict_types=1);

namespace AdyenPayment\Components;

use AdyenPayment\Models\PaymentInfo;
use Shopware\Components\Model\ModelManager;

class OrderMailService
{
    /** @var ModelManager */
    private $modelManager;

    /** @var BasketService */
    private $basketService;

    /** @var bool */
    private $isOrderConfirmationEmailRestricted = false;

    public function __construct(ModelManager $modelManager, BasketService $basketService)
    {
        $this->modelManager = $modelManager;
        $this->basketService = $basketService;
    }

    /**
     * Executes provided callback without sending order confirmation email.
     *
     * @param callable $callback The callback to execute without email sending
     * @param array    $args     The parameters to be passed to the callback, as an indexed array
     */
    public function doWithoutSendingOrderConfirmationMail(callable $callback, array $args = [])
    {
        $this->isOrderConfirmationEmailRestricted = true;

        try {
            $result = call_user_func_array($callback, $args);
        } finally {
            $this->isOrderConfirmationEmailRestricted = false;
        }

        return $result;
    }

    /**
     * Sends the mail after a payment is confirmed.
     */
    public function sendOrderConfirmationMail(string $orderNumber): void
    {
        $order = $this->basketService->getOrderByOrderNumber($orderNumber);
        if (!$order) {
            return;
        }

        $paymentInfoRepository = $this->modelManager->getRepository(PaymentInfo::class);
        /** @var PaymentInfo $paymentInfo */
        $paymentInfo = $paymentInfoRepository->findOneBy([
            'orderId' => $order->getId(),
        ]);

        if (!$paymentInfo || null === $paymentInfo->getOrdermailVariables()) {
            return;
        }

        $variables = json_decode($paymentInfo->getOrdermailVariables(), true);
        if (is_array($variables)) {
            $sOrder = Shopware()->Modules()->Order();
            $sOrder->sUserData = $variables;
            $sOrder->sendMail($variables);
        }

        $paymentInfo->setOrdermailVariables(null);
        $this->modelManager->persist($paymentInfo);
        $this->modelManager->flush($paymentInfo);
    }

    public function isOrderConfirmationEmailRestricted(): bool
    {
        return $this->isOrderConfirmationEmailRestricted;
    }
}
