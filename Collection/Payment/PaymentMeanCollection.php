<?php

declare(strict_types=1);

namespace AdyenPayment\Collection\Payment;

use AdyenPayment\AdyenPayment;
use AdyenPayment\Models\Enum\PaymentMethod\SourceType;
use AdyenPayment\Models\Payment\PaymentMean;

final class PaymentMeanCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var array<PaymentMean>
     */
    private array $paymentMeans;

    public function __construct(PaymentMean ...$paymentMeans)
    {
        $this->paymentMeans = $paymentMeans;
    }

    public static function createFromShopwareArray(array $paymentMeans): self
    {
        return new self(...array_map(
            static fn(array $paymentMean): PaymentMean => PaymentMean::createFromShopwareArray($paymentMean),
            $paymentMeans
        ));
    }

    /**
     * @return \Generator<PaymentMean>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->paymentMeans;
    }

    public function count(): int
    {
        return \count($this->paymentMeans);
    }

    public function map(callable $callable): array
    {
        return array_filter(array_map($callable, $this->paymentMeans));
    }

    public function filter(callable $filter): self
    {
        return new self(...array_filter($this->paymentMeans, $filter));
    }

    public function filterBySource(SourceType $source): self
    {
        return $this->filter(
            static function(PaymentMean $paymentMean) use ($source): bool {
                return $source->equals($paymentMean->getSource());
            }
        );
    }

    public function filterExcludeAdyen(): self
    {
        return $this->filter(
            static function(PaymentMean $paymentMean): bool {
                return !$paymentMean->getSource()->equals(SourceType::adyen());
            }
        );
    }

    public function filterExcludeHidden(): self
    {
        return new self(...array_filter(
            $this->paymentMeans,
            static fn(PaymentMean $paymentMean): bool => !$paymentMean->isHidden()
        ));
    }

    public function fetchStoredMethodUmbrellaPaymentMean(): ?PaymentMean
    {
        foreach ($this->paymentMeans as $paymentMean) {
            if (AdyenPayment::ADYEN_STORED_PAYMENT_UMBRELLA_CODE === $paymentMean->getValue('name')) {
                return $paymentMean;
            }
        }

        return null;
    }

    public function fetchById(int $paymentId): ?PaymentMean
    {
        foreach ($this->paymentMeans as $paymentMean) {
            if ($paymentMean->getId() === $paymentId) {
                return $paymentMean;
            }
        }

        return null;
    }

    public function fetchByStoredMethodId(string $storedMethodId): ?PaymentMean
    {
        foreach ($this->paymentMeans as $paymentMean) {
            if ($paymentMean->getValue('stored_method_id') === $storedMethodId) {
                return $paymentMean;
            }
        }

        return null;
    }

    public function fetchByUmbrellaStoredMethodId(string $storedMethodId): ?PaymentMean
    {
        foreach ($this->paymentMeans as $paymentMean) {
            if ($paymentMean->getValue('stored_method_umbrella_id') === $storedMethodId) {
                return $paymentMean;
            }
        }

        return null;
    }

    public function toShopwareArray(): array
    {
        return array_reduce($this->paymentMeans, static function(array $payload, PaymentMean $paymentMean): array {
            $payload[$paymentMean->getId()] = $paymentMean->getRaw();

            return $payload;
        }, []);
    }
}