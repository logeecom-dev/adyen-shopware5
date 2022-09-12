<?php

use Adyen\AdyenException;
use AdyenPayment\AdyenPayment;
use AdyenPayment\Components\Adyen\PaymentMethodService;
use AdyenPayment\Components\BasketService;
use AdyenPayment\Components\Manager\OrderManagerInterface;
use AdyenPayment\Components\OrderMailService;
use AdyenPayment\Models\PaymentResultCode;
use AdyenPayment\Components\Manager\AdyenManager;
use AdyenPayment\Components\Payload\Chain;
use AdyenPayment\Components\Payload\PaymentContext;
use AdyenPayment\Components\Payload\PaymentPayloadProvider;
use AdyenPayment\Models\PaymentInfo;
use Shopware\Components\Logger;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

/**
 * Class Shopware_Controllers_Frontend_Adyen
 */
//phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace, Squiz.Classes.ValidClassName.NotCamelCaps
class Shopware_Controllers_Frontend_Adyen extends Shopware_Controllers_Frontend_Payment
{
    /** @var AdyenManager */
    private $adyenManager;

    /** @var PaymentMethodService */
    private $adyenCheckout;

    /** @var Logger */
    private $logger;

    /** @var Chain */
    private $paymentPayloadProvider;

    /** @var BasketService */
    private $basketService;

    /** @var OrderMailService */
    private $orderMailService;

    /** @var OrderManagerInterface */
    private $orderManager;

    /**
     * @return void
     */
    public function preDispatch()
    {
        $this->adyenManager = $this->get(AdyenManager::class);
        $this->adyenCheckout = $this->get(PaymentMethodService::class);
        $this->logger = $this->get('adyen_payment.logger');
        $this->paymentPayloadProvider = $this->get(PaymentPayloadProvider::class);
        $this->basketService = $this->get(BasketService::class);
        $this->orderMailService = $this->get(OrderMailService::class);
        $this->orderManager = $this->get(OrderManagerInterface::class);
    }

    public function ajaxDoPaymentAction(): void
    {
        $this->Request()->setHeader('Content-Type', 'application/json');
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        if (!Shopware()->Modules()->Admin()->sCheckUser()) {
            $this->Response()->setHttpResponseCode(401);
            return;
        }

        $context = $this->createPaymentContext();
        $paymentInfo = [];

        try {
            $payload = $this->paymentPayloadProvider->provide($context);
            $checkout = $this->adyenCheckout->getCheckout();
            $paymentInfo = $checkout->payments($payload);

            $this->adyenManager->storePaymentData(
                $context->getTransaction(),
                $paymentInfo['paymentData'] ?? ''
            );
            $this->updateOrderTransactionId($paymentInfo);

            $this->handlePaymentData($paymentInfo);

            $this->Response()->setBody(json_encode(
                [
                    'status' => 'success',
                    'content' => $paymentInfo,
                    'adyenTransactionId' => $context->getTransaction()->getId(),
                    'sUniqueID' => $context->getOrder()->getTemporaryId(),
                ]
            ));
        } catch (AdyenException $ex) {
            $this->logger->debug('AdyenException during doPayment', [
                'message' => $ex->getMessage(),
                'file' => $ex->getFile(),
                'line' => $ex->getLine(),
            ]);

            $this->Response()->setBody(json_encode(
                [
                    'status' => 'error',
                    'content' => $ex->getMessage(),
                ]
            ));

            $this->updateOrderTransactionId($paymentInfo);
            $this->basketService->cancelAndRestoreByOrderNumber($context->getOrder()->getNumber());
        }
    }

    /**
     * @throws AdyenException
     *
     * @deprecated will be removed in 3.0.0 to move closer to a generic implementation,
     * use paymentDetailsAction() instead
     */
    public function ajaxThreeDsAction(): void
    {
        $threeDSResult = (string) ($this->Request()->getPost()['details']['threeDSResult'] ?? '');
        if ('' === $threeDSResult) {
            $this->logger->error('3DS missing data', [
                'action' => $this->Request()->getPost()['action'] ?? '',
                'threeDSResult' => substr($threeDSResult, -5),
                'paymentData' => substr( $this->Request()->getPost()['paymentData'] ?? '', -5),
            ]);
        }

        $this->paymentDetailsAction();
    }

    /**
     * @return void
     * @throws AdyenException
     * @throws Enlight_Event_Exception
     * @throws Enlight_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function paymentDetailsAction(): void
    {
        $this->Request()->setHeader('Content-Type', 'application/json');
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        if (!Shopware()->Modules()->Admin()->sCheckUser()) {
            $this->Response()->setHttpResponseCode(401);
            $this->tryOrderCancelByTransactionId($this->Request()->getPost('adyenTransactionId'));

            return;
        }

        $payload = array_intersect_key($this->Request()->getPost(), ['details' => true]);
        $checkout = $this->adyenCheckout->getCheckout();
        $paymentInfo = $checkout->paymentsDetails($payload);

        $this->updateOrderTransactionId($paymentInfo);
        $this->handlePaymentData($paymentInfo);

        $this->Response()->setBody(json_encode($paymentInfo));
    }

    /**
     * @return PaymentContext
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createPaymentContext(): PaymentContext
    {
        $paymentInfo = json_decode($this->Request()->getPost('paymentMethod') ?? '{}', true);
        $transaction = $this->prepareTransaction();
        $order = $this->prepareOrder($transaction);
        $browserInfo = $this->Request()->getPost('browserInfo');
        $shopperInfo = $this->getShopperInfo();
        $origin = $this->Request()->getPost('origin');
        $storePaymentMethod = (bool) json_decode($this->Request()->getPost('storePaymentMethod', false), true);

        return new PaymentContext(
            $paymentInfo,
            $order,
            Shopware()->Modules()->Basket(),
            $browserInfo,
            $shopperInfo,
            $origin,
            $transaction,
            $storePaymentMethod
        );
    }

    /**
     * @return PaymentInfo
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function prepareTransaction(): PaymentInfo
    {
        $transaction = new PaymentInfo();
        $transaction->setOrderId(-1);
        $transaction->setPspReference('');

        $this->getModelManager()->persist($transaction);
        $this->getModelManager()->flush($transaction);

        return $transaction;
    }

    /**
     * @param PaymentInfo $transaction
     *
     * @return Order
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function prepareOrder(PaymentInfo $transaction): Order
    {
        $signature = "adyen_{$transaction->getId()}_{$this->persistBasket()}";

        Shopware()->Session()->offsetSet(
            AdyenPayment::SESSION_ADYEN_PAYMENT_INFO_ID,
            $transaction->getId()
        );

        if ($this->Request()->getParam('sComment') !== null) {
            Shopware()->Session()->offsetSet('sComment', $this->Request()->getParam('sComment'));
        }

        $orderNumber = $this->orderMailService->doWithoutSendingOrderConfirmationMail(
            [$this, 'saveOrder'], [$transaction->getId(), $signature, Status::PAYMENT_STATE_OPEN, false]
        );

        /** @var Order $order */
        $order = $this->getModelManager()->getRepository(Order::class)->findOneBy([
            'number' => $orderNumber,
        ]);

        $transaction->setOrder($order);

        $this->getModelManager()->persist($transaction);
        $this->getModelManager()->flush($transaction);

        return $order;
    }

    /**
     * @return array
     *
     * @psalm-return array{shopperIP: mixed}
     */
    private function getShopperInfo(): array
    {
        return [
            'shopperIP' => $this->request->getClientIp(),
        ];
    }

    /**
     * @param $paymentInfo
     *
     * @throws Enlight_Event_Exception
     * @throws Enlight_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function handlePaymentData($paymentInfo): void
    {
        $rawResultCode = (string) ($paymentInfo['resultCode'] ?? '');
        if (!PaymentResultCode::exists($rawResultCode)) {
            $this->handlePaymentDataError($paymentInfo);
            return;
        }

        $resultCode = PaymentResultCode::load((string) ($paymentInfo['resultCode'] ?? ''));
        if (
            !$resultCode->equals(PaymentResultCode::authorised()) &&
            !$resultCode->equals(PaymentResultCode::identifyShopper()) &&
            !$resultCode->equals(PaymentResultCode::challengeShopper()) &&
            !$resultCode->equals(PaymentResultCode::pending()) &&
            !$resultCode->equals(PaymentResultCode::redirectShopper())
        ) {
            $this->handlePaymentDataError($paymentInfo);
        }
    }

    /**
     * @param $paymentInfo
     *
     * @throws Enlight_Event_Exception
     * @throws Enlight_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function handlePaymentDataError(array $paymentResponseInfo): void
    {
        if (array_key_exists('merchantReference', $paymentResponseInfo)) {
            $this->basketService->cancelAndRestoreByOrderNumber($paymentResponseInfo['merchantReference']);
            return;
        }

        if (isset($paymentResponseInfo['action']['merchantReference'])) {
            $this->basketService->cancelAndRestoreByOrderNumber($paymentResponseInfo['action']['merchantReference']);
        }
    }

    /**
     * @return void
     * @throws Enlight_Event_Exception
     * @throws Enlight_Exception
     * @throws Zend_Db_Adapter_Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function tryOrderCancelByTransactionId($adyenTransactionId): void
    {
        /** @var PaymentInfo $transaction */
        $transaction = $this->getModelManager()->getRepository(PaymentInfo::class)->findOneBy([
            'id' => $adyenTransactionId,
        ]);
        if (!$transaction) {
            return;
        }

        $this->basketService->cancelAndRestoreByOrderNumber($transaction->getOrdernumber());
    }

    /**
     * @param array $paymentResponseInfo
     * @return void
     */
    private function updateOrderTransactionId(array $paymentResponseInfo): void
    {
        $pspReference = $paymentResponseInfo['pspReference'] ?? '';
        if (empty($pspReference)) {
            return;
        }

        $merchantReference = $paymentResponseInfo['merchantReference'] ?? null;
        if (!$merchantReference && isset($paymentResponseInfo['action']['merchantReference'])) {
            $merchantReference = $paymentResponseInfo['action']['merchantReference'];
        }

        if (!$merchantReference) {
            return;
        }

        $order = $this->basketService->getOrderByOrderNumber($merchantReference);
        if ($order) {
            $this->orderManager->updatePspReference($order, $pspReference);
            $this->orderManager->save($order);
        }
    }
}
