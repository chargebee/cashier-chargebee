<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Environment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;

final class Cashier
{
    /**
     * The Cashier for Chargebee library version.
     *
     * @var string
     */
    public const VERSION = '0.1.0';

    /**
     * The Chargebee API version.
     *
     * @var string
     */
    public const CHARGEBEE_API_VERSION = '2024-12-19';

    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected static $formatCurrencyUsing;

    /**
     * Indicates if Cashier routes will be registered.
     */
    public static bool $registersRoutes = true;

    /**
     * Indicates if Cashier will mark past due subscriptions as inactive.
     */
    public static bool $deactivatePastDue = true;

    /**
     * Indicates if Cashier will mark incomplete subscriptions as inactive.
     */
    public static bool $deactivateIncomplete = true;

    /**
     * The default customer model class name.
     */
    public static string $customerModel = 'App\\Models\\User';

    /**
     * The subscription model class name.
     */
    public static string $subscriptionModel = Subscription::class;

    /**
     * The subscription item model class name.
     */
    public static string $subscriptionItemModel = SubscriptionItem::class;

    /**
     * Get the customer instance by its Chargebee ID.
     */
    public static function findBillable(\Chargebee\ChargeBee\Models\Customer|string|null $chargebeeId): ?Model
    {
        $chargebeeId = $chargebeeId instanceof Customer ? $chargebeeId->id : $chargebeeId;

        $model = static::$customerModel;

        $builder = in_array(SoftDeletes::class, class_uses_recursive($model))
            ? $model::withTrashed()
            : new $model;

        return $chargebeeId ? $builder->where('chargebee_id', $chargebeeId)->first() : null;
    }

    /**
     * Configure the Chargebee environment with the site and API key.
     */
    public static function configureEnvironment(): void
    {
        $site = config('cashier.site');
        $apiKey = config('cashier.api_key');

        Environment::configure($site, $apiKey);
    }

    /**
     * Set the custom currency formatter.
     *
     * @param callable $callback
     */
    public static function formatCurrencyUsing(callable $callback): void
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     */
    public static function formatAmount(int $amount, ?string $currency = null, ?string $locale = null, array $options = []): string
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount, $currency, $locale, $options);
        }

        $money = new Money($amount, new Currency(strtoupper($currency ?? config('cashier.currency'))));

        $locale = $locale ?? config('cashier.currency_locale');

        $numberFormatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        if (isset($options['min_fraction_digits'])) {
            $numberFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $options['min_fraction_digits']);
        }

        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());

        return $moneyFormatter->format($money);
    }

    /**
     * Configure Cashier to not register its routes.
     */
    public static function ignoreRoutes(): static
    {
        static::$registersRoutes = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain past due subscriptions as active.
     */
    public static function keepPastDueSubscriptionsActive(): static
    {
        static::$deactivatePastDue = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain incomplete subscriptions as active.
     */
    public static function keepIncompleteSubscriptionsActive(): static
    {
        static::$deactivateIncomplete = false;

        return new static;
    }

    /**
     * Set the customer model class name.
     */
    public static function useCustomerModel(string $customerModel): void
    {
        static::$customerModel = $customerModel;
    }

    /**
     * Set the subscription model class name.
     */
    public static function useSubscriptionModel(string $subscriptionModel): void
    {
        static::$subscriptionModel = $subscriptionModel;
    }

    /**
     * Set the subscription item model class name.
     */
    public static function useSubscriptionItemModel(string $subscriptionItemModel): void
    {
        static::$subscriptionItemModel = $subscriptionItemModel;
    }
}