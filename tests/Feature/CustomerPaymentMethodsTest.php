<?php

namespace Chargebee\CashierChargebee\Tests\Feature;

use Chargebee\CashierChargebee\Exceptions\InvalidPaymentMethod;
use Chargebee\CashierChargebee\PaymentMethod;
use Chargebee\CashierChargebee\Tests\Fixtures\User;
use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\PaymentIntent;
use ChargeBee\ChargeBee\Models\PaymentSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

class CustomerPaymentMethodsTest extends FeatureTestCase
{
    public function test_can_create_setup_intent(): void
    {
        $currency = config('cashier.currency');
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $paymentIntent = $this->createSetupIntent($user, $currency);

        $this->assertNotNull($paymentIntent);
        $this->assertSame($user->chargebee_id, $paymentIntent->customerId);
        $this->assertSame(0, $paymentIntent->amount);
        $this->assertSame($currency, $paymentIntent->currencyCode);
    }

    public function test_cannot_create_setup_intent(): void
    {
        $currency = Str::random(3);
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $this->expectException(InvalidRequestException::class);
        $paymentIntent = $this->createSetupIntent($user, $currency);

        $this->assertNull($paymentIntent);
    }

    public function test_find_setup_intent(): void
    {
        $currency = config('cashier.currency');
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $paymentIntent = $this->createSetupIntent($user, $currency);

        $this->assertNotNull($paymentIntent);
        $this->assertSame($user->chargebee_id, $paymentIntent->customerId);
        $this->assertSame(0, $paymentIntent->amount);
        $this->assertSame($currency, $paymentIntent->currencyCode);

        $findPaymentIntent = $user->findSetupIntent($paymentIntent->id);

        $this->assertNotNull($findPaymentIntent);
        $this->assertSame($user->chargebee_id, $findPaymentIntent->customerId);
        $this->assertSame(0, $findPaymentIntent->amount);
        $this->assertSame($currency, $paymentIntent->currencyCode);
    }

    public function test_cannot_find_setup_intent(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $this->expectException(InvalidRequestException::class);
        $findPaymentIntent = $user->findSetupIntent(Str::random());

        $this->assertNull($findPaymentIntent);
    }

    private function createSetupIntent(User $user, string $currencyCode): ?PaymentIntent
    {
        return $user->createSetupIntent(['currency_code' => $currencyCode]);
    }

    public function test_get_customer_payment_methods(): void
    {
        $user = $this->createCustomer();
        $paymentMethods = $user->paymentMethods();
        $this->assertInstanceOf(Collection::class, $paymentMethods);
        $this->assertTrue($paymentMethods->isEmpty());

        $user->createAsChargebeeCustomer();
        $paymentMethods = $user->paymentMethods();

        $this->assertNotNull($paymentMethods);
        $this->assertInstanceOf(Collection::class, $paymentMethods);
    }

    public function test_can_add_payment_method(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $paymentMethod = $this->createCard($user);
        $addedPaymentMethod = $user->addPaymentMethod($paymentMethod);

        $this->assertNotNull($paymentMethod);
        $this->assertInstanceOf(PaymentSource::class, $paymentMethod);
        $this->assertInstanceOf(PaymentSource::class, $addedPaymentMethod->asChargebeePaymentMethod());
        $this->assertSame($user->chargebeeId(), $paymentMethod->customerId);
        $this->assertInstanceOf(PaymentSource::class, $paymentMethod);

        $this->assertSame($user->chargebeeId(), $addedPaymentMethod->owner()->chargebeeId());
        $this->assertSame($paymentMethod->customerId, $addedPaymentMethod->owner()->chargebeeId());
    }

    public function test_can_add_payment_method_and_make_it_default(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $paymentMethod = $this->createCard($user);
        $addedPaymentMethod = $user->addPaymentMethod($paymentMethod, true);

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'pm_type' => $addedPaymentMethod->card->brand,
                'pm_last_four' => $addedPaymentMethod->card->last4,
            ]
        );
    }

    public function test_non_chargebee_customer_cannot_add_payment_method(): void
    {
        $user = $this->createCustomer();
        $this->expectException(InvalidRequestException::class);
        $paymentMethod = $this->createCard($user);
        $user->addPaymentMethod($paymentMethod);
    }

    public function test_chargebee_customer_cannot_add_payment_method_wrong_customer_id(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $user2 = $this->createCustomer(Str::random());
        $user2->createAsChargebeeCustomer();

        $paymentMethod = $this->createCard($user);
        $this->expectException(InvalidPaymentMethod::class);
        $user2->addPaymentMethod($paymentMethod);
    }

    public function test_chargebee_customer_cannot_add_payment_method_empty_customer_id(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $paymentMethod = $this->createCard($user);
        $this->expectException(LogicException::class);
        $paymentMethod->customerId = null;
        $user->addPaymentMethod($paymentMethod);
    }

    public function test_chargebee_customer_can_delete_payment_method(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);
        $user->deletePaymentMethod($paymentSource);
        $this->assertNull($user->paymentMethods()->filter(fn (PaymentSource $listPaymentMethod) => $listPaymentMethod->id === $paymentSource->id)->first());

        $paymentSource = $this->createCard($user);
        $addedPaymentMethod = $user->addPaymentMethod($paymentSource, true);

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'pm_type' => $addedPaymentMethod->card->brand,
                'pm_last_four' => $addedPaymentMethod->card->last4,
            ]
        );

        $addedPaymentMethod->delete();
        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'pm_type' => null,
                'pm_last_four' => null,
            ]
        );

        $this->assertNull($user->paymentMethods()->filter(fn (PaymentSource $listPaymentMethod) => $listPaymentMethod->id === $addedPaymentMethod->id)->first());
    }

    public function test_chargebee_customer_can_delete_payment_methods_of_specific_type(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);
        $user->deletePaymentMethods($paymentSource->type);
        $this->assertNull($user->paymentMethods()->filter(fn (PaymentSource $listPaymentMethod) => $listPaymentMethod->type === $paymentSource->type)->first());
    }

    public function test_chargebee_customer_cannot_delete_payment_method(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $paymentMethod = $this->createCard($user);

        $user2 = $this->createCustomer(Str::random());
        $user2->createAsChargebeeCustomer();

        $this->expectException(InvalidPaymentMethod::class);
        $user2->deletePaymentMethod($paymentMethod);
    }

    public function test_chargebee_customer_has_payment_method(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $this->createCard($user);
        $this->assertTrue($user->hasPaymentMethod('card'));
    }

    public function test_find_payment_method(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $resolvedPaymentSource = $user->findPaymentMethod($paymentSource);

        $this->assertInstanceOf(PaymentMethod::class, $resolvedPaymentSource);
        $this->assertSame($paymentSource, $resolvedPaymentSource->asChargebeePaymentMethod());
    }

    public function test_resolve_chargebee_payment_method(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $reflectedMethod = new \ReflectionMethod(
            User::class,
            'resolveChargebeePaymentMethod'
        );

        $resolvedPaymentSource = $reflectedMethod->invokeArgs($user, [$paymentSource]);

        $this->assertInstanceOf(PaymentSource::class, $resolvedPaymentSource);
        $this->assertSame($paymentSource, $resolvedPaymentSource);

        $resolvedPaymentSource = $reflectedMethod->invokeArgs($user, [$paymentSource->id]);
        $this->assertInstanceOf(PaymentSource::class, $resolvedPaymentSource);
        $this->assertSame($paymentSource->id, $resolvedPaymentSource->id);
    }

    public function test_update_default_payment_method_from_chargebee(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);
        $addedPaymentMethod = $user->addPaymentMethod($paymentSource, true);
        $user->updateDefaultPaymentMethodFromChargebee();

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'pm_type' => $addedPaymentMethod->card->brand,
                'pm_last_four' => $addedPaymentMethod->card->last4,
            ]
        );

        PaymentSource::delete($paymentSource->id);
        $user->updateDefaultPaymentMethodFromChargebee();

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'pm_type' => null,
                'pm_last_four' => null,
            ]
        );
    }

    private function createCard(Model $user): ?PaymentSource
    {
        return PaymentSource::createCard([
            'customer_id' => $user->chargebeeId(),
            'card' => [
                'number' => '4111 1111 1111 1111',
                'cvv' => '123',
                'expiry_year' => date('Y', strtotime('+ 1 year')),
                'expiry_month' => date('m', strtotime('+ 1 year')),
            ],
        ]
        )->paymentSource();
    }
}
