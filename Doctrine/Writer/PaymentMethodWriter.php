<?php

declare(strict_types=1);

namespace AdyenPayment\Doctrine\Writer;

use AdyenPayment\Exceptions\PaymentExistsException;
use AdyenPayment\Exceptions\PaymentNotImportedException;
use AdyenPayment\Models\Enum\PaymentMethod\ImportStatus;
use AdyenPayment\Models\Payment\PaymentFactoryInterface;
use AdyenPayment\Models\Payment\PaymentMethod;
use AdyenPayment\Models\PaymentMethod\ImportResult;
use AdyenPayment\Shopware\Repository\PaymentRepositoryInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Shop\Shop;

final class PaymentMethodWriter implements PaymentMethodWriterInterface
{
    /** @var ModelManager */
    private $entityManager;

    /** @var PaymentFactoryInterface */
    private $paymentFactory;

    /** @var PaymentAttributeWriterInterface */
    private $paymentAttributeWriter;

    /** @var PaymentRepositoryInterface */
    private $paymentRepository;

    public function __construct(
        ModelManager $entityManager,
        PaymentFactoryInterface $paymentFactory,
        PaymentAttributeWriterInterface $paymentAttributeWriter,
        PaymentRepositoryInterface $paymentRepository
    ) {
        $this->entityManager = $entityManager;
        $this->paymentFactory = $paymentFactory;
        $this->paymentAttributeWriter = $paymentAttributeWriter;
        $this->paymentRepository = $paymentRepository;
    }

    public function __invoke(PaymentMethod $adyenPaymentMethod, Shop $shop): ImportResult
    {
        $payment = $this->providePaymentModel($adyenPaymentMethod, $shop);
        if ($this->paymentExists($payment)) {
            return ImportResult::fromException(
                $shop,
                $adyenPaymentMethod,
                PaymentExistsException::withName($payment->getName())
            );
        }

        $this->entityManager->persist($payment);
        $this->entityManager->flush($payment);
        if (null === $payment->getId()) {
            return ImportResult::fromException(
                $shop,
                $adyenPaymentMethod,
                PaymentNotImportedException::forPayment($adyenPaymentMethod, $payment, $shop)
            );
        }

        ($this->paymentAttributeWriter)($payment->getId(), $adyenPaymentMethod);

        return ImportResult::success($shop, $adyenPaymentMethod, ImportStatus::created());
    }

    private function paymentExists(Payment $payment): bool
    {
        if (null === $payment->getId()) {
            return $this->paymentRepository->existsByName($payment->getName());
        }

        return $this->paymentRepository->existsDuplicate($payment);
    }

    private function providePaymentModel(PaymentMethod $adyenPaymentMethod, Shop $shop): Payment
    {
        $swPayment = $this->paymentRepository->findByCode($adyenPaymentMethod->code());
        if (!$swPayment) {
            return $this->paymentFactory->createFromAdyen($adyenPaymentMethod, $shop);
        }

        return $this->paymentFactory->updateFromAdyen($swPayment, $adyenPaymentMethod, $shop);
    }
}
