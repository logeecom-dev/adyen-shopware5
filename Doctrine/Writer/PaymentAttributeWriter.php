<?php

declare(strict_types=1);

namespace AdyenPayment\Doctrine\Writer;

use AdyenPayment\AdyenPayment;
use AdyenPayment\Models\Payment\PaymentMethod;
use AdyenPayment\Shopware\Crud\AttributeWriterInterface;
use Shopware\Bundle\AttributeBundle\Service\DataPersisterInterface;
use Shopware\Bundle\AttributeBundle\Service\TypeMappingInterface;

final class PaymentAttributeWriter implements PaymentAttributeWriterInterface
{
    private DataPersisterInterface $dataPersister;
    private AttributeWriterInterface $attributeUpdater;

    public function __construct(DataPersisterInterface $dataPersister, AttributeWriterInterface $attributeUpdater)
    {
        $this->dataPersister = $dataPersister;
        $this->attributeUpdater = $attributeUpdater;
    }

    public function __invoke(int $paymentMeanId, PaymentMethod $adyenPaymentMethod): void
    {
        $attributesColumns = [AdyenPayment::ADYEN_CODE => TypeMappingInterface::TYPE_STRING];

        $dataPersister = $this->dataPersister;
        $this->attributeUpdater->writeReadOnlyAttributes(
            $table = 's_core_paymentmeans_attributes',
            $attributesColumns,
            static fn() => $dataPersister->persist(
                [
                    '_table' => $table,
                    '_foreignKey' => $paymentMeanId,
                    AdyenPayment::ADYEN_CODE => $adyenPaymentMethod->code(),
                ],
                's_core_paymentmeans_attributes',
                $paymentMeanId
            )
        );
    }
}