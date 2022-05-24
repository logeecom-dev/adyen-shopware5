<?php

declare(strict_types=1);

namespace AdyenPayment\Shopware\Repository;

use Shopware\Components\Model\ModelManager;
use Shopware\Components\Model\ModelRepository;
use Shopware\Models\Payment\Payment;

final class PaymentRepository implements PaymentRepositoryInterface
{
    private ModelManager $modelManager;

    public function __construct(ModelManager $modelManager)
    {
        $this->modelManager = $modelManager;
    }

    public function existsByName(string $name): bool
    {
        return null !== $this->paymentRepository()->findOneBy(['name' => $name]);
    }

    public function existsDuplicate(Payment $newPayment): bool
    {
        $payments = $this->paymentRepository()->findBy(['name' => $newPayment->getName()]) ?? [];
        if (!count($payments)) {
            return false;
        }

        /** @psalm-var list<Payment> $payments */
        foreach ($payments as $payment) {
            if ($payment->getId() !== $newPayment->getId()) {
                return true;
            }
        }

        return false;
    }

    public function findByCode(string $code): ?Payment
    {
        $query = $this->paymentRepository()
            ->createQueryBuilder('payment')
            ->innerJoin('payment.attribute', 'attribute')
            ->where('attribute.adyenType = :adyenCode')
            ->setMaxResults(1)
            ->setParameter(':adyenCode', $code);

        return $query->getQuery()->execute()[0] ?? null;
    }

    private function paymentRepository(): ModelRepository
    {
        return $this->modelManager->getRepository(Payment::class);
    }
}