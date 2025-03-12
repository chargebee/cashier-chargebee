<?php

namespace Chargebee\CashierChargebee\Tests\Feature;

use Chargebee\CashierChargebee\Cashier;
use Chargebee\CashierChargebee\Tests\Fixtures\User;
use ChargeBee\ChargeBee\Models\PromotionalCredit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerTest extends FeatureTestCase
{
    public function test_create_as_chargebee_customer_creates_a_new_customer(): void
    {
        $user = $this->createCustomer('testuser');
        $customer = $user->createAsChargebeeCustomer();

        $this->assertTrue($user->hasChargebeeId());
        $this->assertSame($customer->id, $user->chargebeeId());
        $this->assertSame($customer->email, 'testuser@cashier-chargebee.com');
    }

    public function test_create_as_chargebee_customer_with_options(): void
    {
        $user = $this->createCustomer();

        $options = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'phone' => '123456789',
            'billingAddress' => [
                'firstName' => 'Test',
                'lastName' => 'User',
                'line1' => 'PO Box 9999',
                'city' => 'Walnut',
                'state' => 'California',
                'zip' => '91789',
                'country' => 'US',
            ],
            'locale' => 'fr-FR',
            'metaData' => json_encode([
                'info' => 'This is a test customer.',
            ]),
        ];

        $customer = $user->createAsChargebeeCustomer($options);

        $this->assertTrue($user->hasChargebeeId());
        $this->assertSame($customer->id, $user->chargebeeId());

        $this->assertSame($customer->firstName, 'Test');
        $this->assertSame($customer->lastName, 'User');
        $this->assertSame($customer->phone, '123456789');

        $this->assertSame($customer->billingAddress->firstName, 'Test');
        $this->assertSame($customer->billingAddress->lastName, 'User');
        $this->assertSame($customer->billingAddress->line1, 'PO Box 9999');
        $this->assertSame($customer->billingAddress->city, 'Walnut');
        $this->assertSame($customer->billingAddress->state, 'California');
        $this->assertSame($customer->billingAddress->zip, '91789');
        $this->assertSame($customer->billingAddress->country, 'US');

        $this->assertSame($customer->locale, 'fr-FR');
        $this->assertSame($customer->metaData['info'], 'This is a test customer.');
    }

    public function test_retrieving_chargebee_customer_with_valid_chargebee_id(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $customer = $user->asChargebeeCustomer();

        $this->assertSame($user->chargebeeId(), $customer->id);
    }

    public function test_update_chargebee_customer_with_billing_address(): void
    {
        $user = $this->createCustomer();

        $createOptions = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'billingAddress' => [
                'firstName' => 'Test',
                'lastName' => 'User',
                'line1' => '221B Baker Street',
                'city' => 'London',
                'state' => 'England',
                'country' => 'GB',
            ],
            'metaData' => json_encode([
                'info' => 'This is a test customer.',
            ]),
        ];

        $user->createAsChargebeeCustomer($createOptions);

        $updateOptions = [
            'firstName' => 'UpdateTest',
            'phone' => '123456789',
            'billingAddress' => [
                'firstName' => 'UpdateTest',
                'lastName' => 'User',
                'line1' => '221B Baker Street',
                'city' => 'London',
                'state' => 'England',
                'zip' => 'NW1 6XE',
                'country' => null,
            ],
        ];

        $customer = $user->updateChargebeeCustomer($updateOptions);

        $this->assertSame($customer->firstName, 'UpdateTest');
        $this->assertSame($customer->lastName, 'User');
        $this->assertSame($customer->phone, '123456789');

        $this->assertSame($customer->billingAddress->firstName, 'UpdateTest');
        $this->assertSame($customer->billingAddress->lastName, 'User');
        $this->assertSame($customer->billingAddress->line1, '221B Baker Street');
        $this->assertSame($customer->billingAddress->city, 'London');
        $this->assertSame($customer->billingAddress->state, 'England');
        $this->assertSame($customer->billingAddress->zip, 'NW1 6XE');
        $this->assertNull($customer->billingAddress->country);

        $this->assertSame($customer->metaData['info'], 'This is a test customer.');
    }

    public function test_update_chargebee_customer_without_modifying_billing_address(): void
    {
        $user = $this->createCustomer();

        $createOptions = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'metaData' => json_encode([
                'info' => 'This is a test customer.',
            ]),
        ];

        $user->createAsChargebeeCustomer($createOptions);

        $updateOptions = [
            'firstName' => 'UpdateTest',
            'phone' => '123456789',
        ];

        $customer = $user->updateChargebeeCustomer($updateOptions);

        $this->assertSame($customer->firstName, 'UpdateTest');
        $this->assertSame($customer->lastName, 'User');
        $this->assertSame($customer->phone, '123456789');

        $this->assertSame($customer->billingAddress->country, 'US');
        $this->assertSame($customer->billingAddress->firstName, 'Test');
        $this->assertSame($customer->billingAddress->lastName, 'User');
        $this->assertSame($customer->billingAddress->line1, 'PO Box 9999');
        $this->assertSame($customer->billingAddress->city, 'Walnut');
        $this->assertSame($customer->billingAddress->state, 'California');
        $this->assertSame($customer->billingAddress->zip, '91789');
        $this->assertSame($customer->billingAddress->country, 'US');

        $this->assertSame($customer->metaData['info'], 'This is a test customer.');
    }

    public function test_update_chargebee_customer_with_empty_billing_address(): void
    {
        $user = $this->createCustomer();

        $createOptions = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'metaData' => json_encode([
                'info' => 'This is a test customer.',
            ]),
        ];

        $user->createAsChargebeeCustomer($createOptions);

        $updateOptions = [
            'firstName' => 'UpdateTest',
            'phone' => '123456789',
            'billingAddress' => [],
        ];

        $customer = $user->updateChargebeeCustomer($updateOptions);

        $this->assertSame($customer->firstName, 'UpdateTest');
        $this->assertSame($customer->lastName, 'User');
        $this->assertSame($customer->phone, '123456789');

        $this->assertSame($customer->billingAddress->country, 'US');
        $this->assertSame($customer->billingAddress->firstName, 'Test');
        $this->assertSame($customer->billingAddress->lastName, 'User');
        $this->assertSame($customer->billingAddress->line1, 'PO Box 9999');
        $this->assertSame($customer->billingAddress->city, 'Walnut');
        $this->assertSame($customer->billingAddress->state, 'California');
        $this->assertSame($customer->billingAddress->zip, '91789');
        $this->assertSame($customer->billingAddress->country, 'US');
    }

    public function test_update_chargebee_customer_with_null_or_empty_billing_address_values(): void
    {
        $user = $this->createCustomer();

        $createOptions = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'metaData' => json_encode([
                'info' => 'This is a test customer.',
            ]),
        ];

        $user->createAsChargebeeCustomer($createOptions);

        $updateOptions = [
            'firstName' => 'UpdateTest',
            'phone' => '123456789',
            'billingAddress' => [
                'firstName' => null,
                'lastName' => null,
                'line1' => null,
                'city' => '',
                'state' => '',
                'zip' => null,
                'country' => null,
            ],
        ];

        $customer = $user->updateChargebeeCustomer($updateOptions);

        $this->assertSame($customer->firstName, 'UpdateTest');
        $this->assertSame($customer->lastName, 'User');
        $this->assertSame($customer->phone, '123456789');

        $this->assertSame($customer->billingAddress->country, 'US');
        $this->assertSame($customer->billingAddress->firstName, 'Test');
        $this->assertSame($customer->billingAddress->lastName, 'User');
        $this->assertSame($customer->billingAddress->line1, 'PO Box 9999');
        $this->assertSame($customer->billingAddress->city, 'Walnut');
        $this->assertSame($customer->billingAddress->state, 'California');
        $this->assertSame($customer->billingAddress->zip, '91789');
        $this->assertSame($customer->billingAddress->country, 'US');
    }

    public function test_create_or_get_chargebee_customer(): void
    {
        $user = $this->createCustomer('testuser');
        $customer = $user->createOrGetChargebeeCustomer();

        $this->assertTrue($user->hasChargebeeId());
        $this->assertSame($customer->id, $user->chargebeeId());

        $customer = $user->createOrGetChargebeeCustomer();

        $this->assertSame($customer->id, $user->chargebeeId());
    }

    public function test_sync_chargebee_customer_details(): void
    {
        $user = $this->createCustomer('testuser');
        $user->createAsChargebeeCustomer();
        $user->first_name = 'TestSync';
        $user->last_name = 'User';
        $user->email = 'testsyncuser@cashier-chargebee.com';
        $user->phone = '123456789';
        $user->locale = 'fr-FR';
        $user->chargebee_metadata = json_encode([
            'info' => 'This is a test customer.',
        ]);

        $customer = $user->syncChargebeeCustomerDetails();

        $this->assertSame($customer->firstName, 'TestSync');
        $this->assertSame($customer->lastName, 'User');
        $this->assertSame($customer->email, 'testsyncuser@cashier-chargebee.com');
        $this->assertSame($customer->phone, '123456789');
        $this->assertSame($customer->locale, 'fr-FR');
        $this->assertSame($customer->metaData['info'], 'This is a test customer.');
    }

    public function test_update_or_create_chargebee_customer(): void
    {
        $user = $this->createCustomer('testuser');

        $customer = $user->updateOrCreateChargebeeCustomer([
            'firstName' => 'Test',
            'lastName' => 'User',
        ]);

        $this->assertTrue($user->hasChargebeeId());
        $this->assertSame($customer->id, $user->chargebeeId());
        $this->assertSame($customer->firstName, 'Test');
        $this->assertSame($customer->lastName, 'User');

        $customer = $user->updateOrCreateChargebeeCustomer([
            'firstName' => 'Updated',
            'lastName' => 'User',
        ]);

        $this->assertSame($customer->id, $user->chargebeeId());
        $this->assertSame($customer->firstName, 'Updated');
        $this->assertSame($customer->lastName, 'User');
    }

    public function test_sync_or_create_chargebee_customer(): void
    {
        $user = $this->createCustomer('testuser');

        $customer = $user->syncOrCreateChargebeeCustomer([
            'firstName' => 'Test',
            'lastName' => 'User',
        ]);

        $this->assertTrue($user->hasChargebeeId());
        $this->assertSame($customer->id, $user->chargebeeId());
        $this->assertSame($customer->firstName, 'Test');
        $this->assertSame($customer->lastName, 'User');

        $user->first_name = 'TestSynced';
        $user->last_name = 'User';

        $customer = $user->syncOrCreateChargebeeCustomer();

        $this->assertSame($customer->id, $user->chargebeeId());
        $this->assertSame($customer->firstName, 'TestSynced');
        $this->assertSame($customer->lastName, 'User');
    }

    public function test_billing_portal_url(): void
    {
        $user = $this->createCustomer('testuser');
        $user->createAsChargebeeCustomer();

        $url = $user->billingPortalUrl('https://example.com');

        $this->assertMatchesRegularExpression(
            '/^https:\/\/[a-z0-9\-]+\.chargebee\.com\/portal\/v2\/authenticate\?token=[a-zA-Z0-9\-_]+$/',
            $url
        );
    }

    public function test_redirect_to_billing_portal(): void
    {
        $user = $this->createCustomer('testuser');
        $user->createAsChargebeeCustomer();

        $response = $user->redirectToBillingPortal('https://example.com');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertMatchesRegularExpression(
            '/^https:\/\/[a-z0-9\-]+\.chargebee\.com\/portal\/v2\/authenticate\?token=[a-zA-Z0-9\-_]+$/',
            $response->getTargetUrl()
        );
    }

    public function test_with_tax_ip_address(): void
    {
        $testIp = '10.10.10.1';
        $user = $this->createCustomer();
        $user->withTaxIpAddress($testIp);

        $this->assertSame($testIp, $user->customerIpAddress);
    }

    public function test_is_automatic_tax_enabled(): void
    {
        $user = $this->createCustomer();
        $reflectedMethod = new \ReflectionMethod(
            User::class,
            'isAutomaticTaxEnabled'
        );

        $this->assertSame(false, $reflectedMethod->invoke($user));
    }

    public function test_with_tax_address(): void
    {
        $country = $postalCode = $state = Str::random();

        $testAddress = [
            'country' => $country,
        ];

        $user = $this->createCustomer();
        $user->withTaxAddress($country);

        $this->assertNotEmpty($user->estimationBillingAddress['country']);
        $this->assertArrayNotHasKey('postal_code', $user->estimationBillingAddress);
        $this->assertArrayNotHasKey('state', $user->estimationBillingAddress);
        $this->assertArrayHasKey('country', $user->estimationBillingAddress);
        $this->assertEquals($user->estimationBillingAddress, $testAddress);

        $testAddress = [
            'country' => $country,
            'postal_code' => $postalCode,
        ];
        $user->withTaxAddress($country, $postalCode);

        $this->assertNotEmpty($user->estimationBillingAddress['country']);
        $this->assertNotEmpty($user->estimationBillingAddress['postal_code']);
        $this->assertArrayNotHasKey('state', $user->estimationBillingAddress);
        $this->assertArrayHasKey('country', $user->estimationBillingAddress);
        $this->assertArrayHasKey('postal_code', $user->estimationBillingAddress);
        $this->assertEquals($user->estimationBillingAddress, $testAddress);

        $testAddress = [
            'country' => $country,
            'postal_code' => $postalCode,
            'state' => $state,
        ];

        $user->withTaxAddress($country, $postalCode, $state);

        $this->assertNotEmpty($user->estimationBillingAddress['country']);
        $this->assertNotEmpty($user->estimationBillingAddress['postal_code']);
        $this->assertNotEmpty($user->estimationBillingAddress['state']);
        $this->assertArrayHasKey('country', $user->estimationBillingAddress);
        $this->assertArrayHasKey('postal_code', $user->estimationBillingAddress);
        $this->assertArrayHasKey('state', $user->estimationBillingAddress);
        $this->assertEquals($user->estimationBillingAddress, $testAddress);
    }

    public function test_automatic_tax_payload(): void
    {
        $user = $this->createCustomer();
        $user->withTaxIpAddress('10.10.10.1');
        $user->withTaxAddress(Str::random());

        $reflectedMethod = new \ReflectionMethod(
            User::class,
            'automaticTaxPayload'
        );

        $result = $reflectedMethod->invoke($user);

        $this->assertArrayHasKey('customer_ip_address', $result);
        $this->assertArrayHasKey('estimation_billing_address', $result);
        $this->assertArrayHasKey('country', $result['estimation_billing_address']);
        $this->assertArrayNotHasKey('enabled', $result);

        Cashier::$calculatesTaxes = true;

        $reflectedMethod = new \ReflectionMethod(
            User::class,
            'automaticTaxPayload'
        );

        $result = $reflectedMethod->invoke($user);
        $this->assertArrayHasKey('enabled', $result);
        $this->assertTrue($result['enabled']);
    }

    public function test_collect_tax_ids(): void
    {
        $user = $this->createCustomer();
        $user->collectTaxIds();

        $this->assertSame(true, $user->collectTaxIds);
    }

    public function test_is_not_tax_exempt(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $this->assertTrue($user->isNotTaxExempt());
        $this->assertFalse($user->isTaxExempt());
    }

    public function test_is_tax_exempt(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer([
            'taxability' => 'exempt',
        ]);

        $this->assertTrue($user->isTaxExempt());
        $this->assertFalse($user->isNotTaxExempt());
    }

    public function test_customer_balance(): void
    {
        config(['cashier.currency' => 'EUR']);

        $user = $this->createCustomer();

        $this->assertSame(0, $user->rawBalance());

        $transactions = $user->balanceTransactions();
        $this->assertInstanceOf(Collection::class, $transactions);
        $this->assertCount(0, $transactions);

        $user->createAsChargebeeCustomer();

        $transactions = $user->balanceTransactions();
        $this->assertInstanceOf(Collection::class, $transactions);
        $this->assertCount(0, $transactions);

        $this->assertSame(0, $user->rawBalance());
        $this->assertSame('€0.00', $user->balance());

        $user->applyBalance(500, 'Add credits.');

        $this->assertSame(500, $user->rawBalance());
        $this->assertSame('€5.00', $user->balance());

        $user->applyBalance(-200);

        $this->assertSame(300, $user->rawBalance());
        $this->assertSame('€3.00', $user->balance());

        $transaction = $user->balanceTransactions()->first();

        $this->assertInstanceOf(PromotionalCredit::class, $transaction);
        $this->assertSame(200, $transaction->amount);
    }

    public function test_update_customer_from_chargebee(): void
    {
        $user = $this->createCustomer('test_update_customer_from_chargebee');
        $user->createAsChargebeeCustomer();

        $updateOptions = [
            'email' => 'testupdatecustomer@cashier-chargebee.com',
        ];

        $customer = $user->updateChargebeeCustomer($updateOptions);
        $this->assertSame('testupdatecustomer@cashier-chargebee.com', $customer->email);

        $user->updateCustomerFromChargebee();
        $this->assertSame('testupdatecustomer@cashier-chargebee.com', $user->email);
    }
}
