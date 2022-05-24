<?php

declare(strict_types=1);

namespace AdyenPayment\Tests\Unit\Components\Adyen\PaymentMethod;

use AdyenPayment\AdyenPayment;
use AdyenPayment\Collection\Payment\PaymentMeanCollection;
use AdyenPayment\Collection\Payment\PaymentMethodCollection;
use AdyenPayment\Components\Adyen\Builder\PaymentMethodOptionsBuilderInterface;
use AdyenPayment\Components\Adyen\PaymentMethod\EnrichedPaymentMeanProvider;
use AdyenPayment\Components\Adyen\PaymentMethod\EnrichedPaymentMeanProviderInterface;
use AdyenPayment\Components\Adyen\PaymentMethodServiceInterface;
use AdyenPayment\Enricher\Payment\PaymentMethodEnricherInterface;
use AdyenPayment\Exceptions\UmbrellaPaymentMeanNotFoundException;
use AdyenPayment\Models\Enum\PaymentMethod\SourceType;
use AdyenPayment\Models\Payment\PaymentMean;
use AdyenPayment\Models\Payment\PaymentMethod;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Shopware\Bundle\StoreFrontBundle\Struct\Attribute;

final class EnrichedPaymentMeanProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy|PaymentMethodServiceInterface
     */
    private $paymentMethodService;

    /**
     * @var ObjectProphecy|PaymentMethodOptionsBuilderInterface
     */
    private $paymentMethodOptionsBuilder;

    /**
     * @var ObjectProphecy|PaymentMethodEnricherInterface
     */
    private $paymentMethodEnricher;
    private EnrichedPaymentMeanProvider $provider;

    protected function setUp(): void
    {
        $this->paymentMethodService = $this->prophesize(PaymentMethodServiceInterface::class);
        $this->paymentMethodOptionsBuilder = $this->prophesize(PaymentMethodOptionsBuilderInterface::class);
        $this->paymentMethodEnricher = $this->prophesize(PaymentMethodEnricherInterface::class);

        $this->provider = new EnrichedPaymentMeanProvider(
            $this->paymentMethodService->reveal(),
            $this->paymentMethodOptionsBuilder->reveal(),
            $this->paymentMethodEnricher->reveal()
        );
    }

    /** @test */
    public function it_is_an_enriched_payment_mean_provider(): void
    {
        $this->assertInstanceOf(EnrichedPaymentMeanProviderInterface::class, $this->provider);
    }

    /** @test */
    public function it_does_not_enrich_on_empty_cart_value_and_excludes_adyen_methods(): void
    {
        $paymentMeans = new PaymentMeanCollection(
            $paymentMeanOne = PaymentMean::createFromShopwareArray([
                'id' => 1,
                'source' => SourceType::shopwareDefault()->getType(),
            ]),
            PaymentMean::createFromShopwareArray([
                'id' => 2,
                'source' => SourceType::adyen()->getType(),
            ]),
        );

        $this->paymentMethodOptionsBuilder->__invoke()->willReturn(['value' => 0.0]);
        $this->paymentMethodService->getPaymentMethods(Argument::cetera())->shouldNotBeCalled();
        $this->paymentMethodEnricher->__invoke(Argument::cetera())->shouldNotBeCalled();

        $result = $this->provider->__invoke($paymentMeans);
        $this->assertInstanceOf(PaymentMeanCollection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame($paymentMeanOne, iterator_to_array($result)[0]);
    }

    /** @test */
    public function it_throws_an_exception_on_missing_umbrella_payment(): void
    {
        $adyenIdentifier = sprintf('%s_%s', $adyenType = 'non', $adyenName = 'adyen');
        $paymentMeans = new PaymentMeanCollection(
            $paymentMeanOne = PaymentMean::createFromShopwareArray([
                'id' => 1,
                'source' => SourceType::shopwareDefault()->getType(),
            ]),
        );

        // filled with a matching identifier, to catch if the early returns fails
        $adyenPaymentMethods = new PaymentMethodCollection(
            PaymentMethod::fromRaw(['type' => $adyenType])->withCode($adyenName),
        );

        $this->paymentMethodOptionsBuilder->__invoke()->willReturn([
            'countryCode' => $countryCode = 'BE',
            'currency' => $currency = 'EUR',
            'value' => $value = 17.7,
        ]);
        $this->paymentMethodService->getPaymentMethods($countryCode, $currency, $value)
            ->willReturn($adyenPaymentMethods);
        $this->paymentMethodEnricher->__invoke(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(UmbrellaPaymentMeanNotFoundException::class);

        $result = $this->provider->__invoke($paymentMeans);
    }

    /** @test */
    public function it_does_not_enrich_non_adyen_methods(): void
    {
        $adyenIdentifier = sprintf('%s_%s', $adyenType = 'non', $adyenName = 'adyen');
        $paymentMeans = new PaymentMeanCollection(
            $paymentMean = PaymentMean::createFromShopwareArray([
                'id' => 17,
                'name' => AdyenPayment::ADYEN_STORED_PAYMENT_UMBRELLA_CODE,
                'source' => SourceType::shopwareDefault()->getType(),
                'attribute' => new Attribute([
                    'adyen_type' => $adyenIdentifier,
                ]),
            ]),
        );

        // filled with a matching identifier, to catch if the early returns fails
        $adyenPaymentMethods = new PaymentMethodCollection(
            PaymentMethod::fromRaw(['type' => $adyenType])->withCode($adyenName),
        );

        $this->paymentMethodOptionsBuilder->__invoke()->willReturn([
            'countryCode' => $countryCode = 'BE',
            'currency' => $currency = 'EUR',
            'value' => $value = 17.7,
        ]);
        $this->paymentMethodService->getPaymentMethods($countryCode, $currency, $value)
            ->willReturn($adyenPaymentMethods);
        $this->paymentMethodEnricher->__invoke(Argument::cetera())->shouldNotBeCalled();

        $result = $this->provider->__invoke($paymentMeans);
        $this->assertInstanceOf(PaymentMeanCollection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame($paymentMean, iterator_to_array($result)[0]);
    }

    /** @test */
    public function it_does_not_enrich_and_removes_payment_means_without_attribute(): void
    {
        $paymentMeans = new PaymentMeanCollection(
            $paymentMeanOne = PaymentMean::createFromShopwareArray([
                'id' => 19,
                'name' => AdyenPayment::ADYEN_STORED_PAYMENT_UMBRELLA_CODE,
                'source' => SourceType::shopwareDefault()->getType(),
            ]),
            $paymentMeanTwo = PaymentMean::createFromShopwareArray([
                'id' => 21,
                'source' => SourceType::adyen()->getType(),
            ]),
        );

        $this->paymentMethodOptionsBuilder->__invoke()->willReturn([
            'countryCode' => $countryCode = 'BE',
            'currency' => $currency = 'EUR',
            'value' => $value = 17.7,
        ]);
        $this->paymentMethodService->getPaymentMethods($countryCode, $currency, $value)
            ->willReturn(new PaymentMethodCollection());
        $this->paymentMethodEnricher->__invoke(Argument::cetera())->shouldNotBeCalled();

        $result = $this->provider->__invoke($paymentMeans);
        $this->assertInstanceOf(PaymentMeanCollection::class, $result);
        $this->assertCount(1, $result);
        $this->assertEquals($paymentMeanOne, iterator_to_array($result)[0]);
    }

    /** @test */
    public function it_does_not_enrich_payment_means_with_attribute_null_values(): void
    {
        $paymentMeans = new PaymentMeanCollection(
            $paymentMean = PaymentMean::createFromShopwareArray([
                'id' => 9,
                'name' => AdyenPayment::ADYEN_STORED_PAYMENT_UMBRELLA_CODE,
                'source' => SourceType::adyen()->getType(),
                'attribute' => new Attribute([
                    'adyen_type' => null,
                ]),
            ]),
        );

        $this->paymentMethodOptionsBuilder->__invoke()->willReturn([
            'countryCode' => $countryCode = 'GB',
            'currency' => $currency = 'GBP',
            'value' => $value = 9.39,
        ]);
        $this->paymentMethodService->getPaymentMethods($countryCode, $currency, $value)
            ->willReturn(new PaymentMethodCollection());
        $this->paymentMethodEnricher->__invoke(Argument::cetera())->shouldNotBeCalled();

        $result = $this->provider->__invoke($paymentMeans);
        $this->assertInstanceOf(PaymentMeanCollection::class, $result);
        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_removes_adyen_payment_means_without_matching_adyen_payment_method(): void
    {
        $paymentMeans = new PaymentMeanCollection(
            $paymentMean = PaymentMean::createFromShopwareArray([
                'id' => 25,
                'name' => AdyenPayment::ADYEN_STORED_PAYMENT_UMBRELLA_CODE,
                'source' => SourceType::adyen()->getType(),
                'attribute' => new Attribute([
                    'adyen_type' => 'non_matching_adyen_identifier',
                ]),
            ]),
        );

        $this->paymentMethodOptionsBuilder->__invoke()->willReturn([
            'countryCode' => $countryCode = 'BE',
            'currency' => $currency = 'EUR',
            'value' => $value = 17.7,
        ]);
        $this->paymentMethodService->getPaymentMethods($countryCode, $currency, $value)
            ->willReturn(new PaymentMethodCollection());
        $this->paymentMethodEnricher->__invoke(Argument::cetera())->shouldNotBeCalled();

        $result = $this->provider->__invoke($paymentMeans);
        $this->assertInstanceOf(PaymentMeanCollection::class, $result);
        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_enriches_adyen_payment_methods(): void
    {
        $adyenIdentifier = sprintf('%s_%s', $adyenType = 'bcmc', $adyenName = 'adyen_name');
        $paymentMeans = new PaymentMeanCollection(
            $paymentMean = PaymentMean::createFromShopwareArray($raw = [
                'id' => $id = 15,
                'source' => $source = SourceType::adyen()->getType(),
                'attribute' => new Attribute([
                    'adyen_type' => $adyenIdentifier,
                ]),
            ]),
            $umbrellaMean = PaymentMean::createFromShopwareArray([
                'id' => 25,
                'source' => SourceType::adyen()->getType(),
                'name' => AdyenPayment::ADYEN_STORED_PAYMENT_UMBRELLA_CODE,
            ]),
        );

        $adyenPaymentMethods = new PaymentMethodCollection(
            $paymentMethod = PaymentMethod::fromRaw([
                'type' => $adyenType,
            ])->withCode($adyenName),
            $storedPaymentMethod = PaymentMethod::fromRaw($storedRaw = [
                'id' => $storedMethodId = 'adyen-stored-payment-method-id',
                'type' => $schemaType = 'scheme',
            ]),
        );

        $this->paymentMethodOptionsBuilder->__invoke()->willReturn([
            'countryCode' => $countryCode = 'DE',
            'currency' => $currency = 'EUR',
            'value' => $value = 15.0,
        ]);
        $this->paymentMethodService->getPaymentMethods($countryCode, $currency, $value)
            ->willReturn($adyenPaymentMethods);

        $this->paymentMethodEnricher->__invoke($raw, $paymentMethod)->willReturn($rawEnriched = [
            'id' => $id,
            'source' => $source,
            'enriched' => true,
            'adyenType' => $adyenType,
        ]);

        $this->paymentMethodEnricher->__invoke($umbrellaMean->getRaw(), $storedPaymentMethod)->willReturn($storedRawEnriched = [
            'id' => $storedMethodId,
            'adyenType' => $schemaType,
            'source' => $source,
        ]);

        $result = $this->provider->__invoke($paymentMeans);

        $this->assertInstanceOf(PaymentMeanCollection::class, $result);
        $this->assertCount(2, $result);
        $this->assertEquals($rawEnriched, iterator_to_array($result)[0]->getRaw());
        $this->assertEquals($storedRawEnriched, iterator_to_array($result)[1]->getRaw());
    }
}