<?php

namespace Chargebee\Cashier\Tests\Unit;

use Chargebee\Cashier\Cashier;
use Chargebee\Cashier\Subscription;
use Chargebee\Cashier\SubscriptionItem;
use Chargebee\Cashier\Tests\TestCase;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;

class CashierTest extends TestCase
{
    public function test_it_can_format_an_amount(): void
    {
        $this->assertSame('$10.00', Cashier::formatAmount(1000));
    }

    public function test_it_can_format_an_amount_without_digits(): void
    {
        $this->assertSame('$10', Cashier::formatAmount(1000, null, null, ['min_fraction_digits' => 0]));
    }

    public function test_it_can_format_an_amount_with_locale_and_currency(): void
    {
        $formatted = Cashier::formatAmount(1000, 'EUR', 'fr_FR');
        $this->assertStringContainsString('10,00', $formatted);
        $this->assertStringContainsString('€', $formatted);
    }

    public function test_format_currency_using_callback(): void
    {
        Cashier::formatCurrencyUsing(function () {
            return $this->formatAmount(1000, 'EUR', 'fr_FR');
        });
        $formatted = Cashier::formatAmount(1000, 'EUR', 'fr_FR');
        $this->assertStringContainsString('10,00', $formatted);
    }

    private function formatAmount(int $amount, ?string $currency = null, ?string $locale = null, array $options = []): string
    {
        $money = new Money($amount, new Currency(strtoupper($currency ?? config('cashier.currency'))));

        $locale = $locale ?? config('cashier.currency_locale');

        $numberFormatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        if (isset($options['min_fraction_digits'])) {
            $numberFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $options['min_fraction_digits']);
        }

        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());

        return $moneyFormatter->format($money);
    }

    public function test_ignore_routes(): void
    {
        $cashier = new Cashier;
        Cashier::ignoreRoutes();

        $this->assertSame(false, $cashier::$registersRoutes);
    }

    public function test_keep_past_subscriptions_active(): void
    {
        $cashier = new Cashier;
        Cashier::keepPastDueSubscriptionsActive();

        $this->assertSame(false, $cashier::$deactivatePastDue);
    }

    public function test_keep_incomplete_subscriptions_active(): void
    {
        $cashier = new Cashier;
        Cashier::keepIncompleteSubscriptionsActive();

        $this->assertSame(false, $cashier::$deactivateIncomplete);
    }

    public function test_use_subscription_model(): void
    {
        $model = 'App\Models\Subscription';
        $cashier = new Cashier;
        Cashier::useSubscriptionModel($model);

        $this->assertSame($model, $cashier::$subscriptionModel);
    }

    public function test_use_subscription_item_model(): void
    {
        $model = 'App\Models\SubscriptionItem';
        $cashier = new Cashier;
        Cashier::useSubscriptionItemModel($model);

        $this->assertSame($model, $cashier::$subscriptionItemModel);
    }

    protected function tearDown(): void
    {
        Cashier::formatCurrencyUsing(null);
        Cashier::$registersRoutes = true;
        Cashier::$deactivatePastDue = true;
        Cashier::$deactivateIncomplete = true;
        Cashier::$subscriptionModel = Subscription::class;
        Cashier::$subscriptionItemModel = SubscriptionItem::class;

        parent::tearDown();
    }
}
