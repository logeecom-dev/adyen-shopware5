<?php

declare(strict_types=1);

namespace AdyenPayment\Models\Payment;

use AdyenPayment\AdyenPayment;
use AdyenPayment\Models\Enum\PaymentMethod\SourceType;
use Shopware\Bundle\StoreFrontBundle\Struct\Attribute;

final class PaymentMean
{
    private int $id;
    private SourceType $source;
    private array $raw;
    private bool $enriched;
    private ?PaymentType $adyenType;

    public static function createFromShopwareArray(array $paymentMean): self
    {
        $new = new self();
        $new->id = (int) ($paymentMean['id'] ?? 0);
        $new->source = SourceType::load((int) $paymentMean['source']);
        $new->raw = $paymentMean;
        $new->enriched = (bool) ($paymentMean['enriched'] ?? false);
        $new->adyenType = true === $new->enriched ? PaymentType::load((string) $paymentMean['adyenType']) : null;

        return $new;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSource(): SourceType
    {
        return $this->source;
    }

    public function isHidden(): bool
    {
        return (bool) ($this->raw['hide'] ?? false);
    }

    public function getAttribute(): Attribute
    {
        return $this->raw['attribute'] ?? new Attribute();
    }

    public function isEnriched(): bool
    {
        return $this->enriched;
    }

    public function getAdyenCode(): string
    {
        if ($this->getAttribute()->exists(AdyenPayment::ADYEN_CODE)) {
            return (string) $this->getAttribute()->get(AdyenPayment::ADYEN_CODE);
        }

        return '';
    }

    public function getAdyenStoredMethodId(): string
    {
        return (string) $this->getValue('stored_method_id', '');
    }

    public function adyenType(): ?PaymentType
    {
        return $this->adyenType;
    }

    /**
     * @param mixed|null $fallback
     *
     * @return mixed|null
     */
    public function getValue(string $key, $fallback = null)
    {
        return $this->raw[$key] ?? $fallback;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    public function isAdyenSourceType(): bool
    {
        return $this->source->equals(SourceType::adyen());
    }
}