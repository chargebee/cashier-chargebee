<?php

namespace Chargebee\Cashier\Tests\Feature;

use Chargebee\Actions\ItemPriceActions;
use Chargebee\Cashier\Cashier;
use Chargebee\Cashier\Events\WebhookReceived;
use Chargebee\Cashier\Subscription;
use Chargebee\Cashier\Tests\Fixtures\User;
use Chargebee\ChargebeeClient;
use Chargebee\Resources\PaymentSource\PaymentSource;
use Chargebee\Responses\ItemPriceResponse\RetrieveItemPriceResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @runClassInSeparateProcess
 */
class WebhookTest extends FeatureTestCase
{
    protected string $webhookUrl = 'chargebee/webhook';

    public function setUp(): void
    {
        parent::setUp();

        config(['cashier.webhook.username' => 'webhook_username']);
        config(['cashier.webhook.password' => 'webhook_password']);

        $mockItemPriceActions = \Mockery::mock('overload:' . ItemPriceActions::class);
        $mockItemPriceActions->shouldReceive('retrieve')
            ->with(\Mockery::type('string'))
            ->andReturn(RetrieveItemPriceResponse::from([
                'item_price' => [
                    "id" =>  'abc',
                    'item_id' => 'product_abc',
                    'name' => 'Basic Plan',
                    'currency_code' => 'USD',
                    'free_quantity' => 0,
                    'created_at' => time(),
                    'deleted' => false,
                    'pricing_model' => 'flat_fee'
                ]
            ]));

        $mockChargebeeClient = \Mockery::mock(ChargebeeClient::class)->makePartial();
        $mockChargebeeClient->shouldReceive('itemPrice')
            ->andReturn($mockItemPriceActions);
    }

    public function test_valid_webhooks_are_authenticated_successfully(): void
    {
        $this->withValidCredentials();

        $response = $this->postJson($this->webhookUrl, ['event' => 'valid_event']);

        $response->assertStatus(Response::HTTP_OK)
            ->assertSee('Webhook Received');
    }

    public function test_invalid_credentials_result_in_http_401_response(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('invalid_username:invalid_password'),
        ])->postJson($this->webhookUrl, ['event' => 'invalid_event']);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee('Unauthorized');
    }

    public function test_no_credentials_result_in_http_401_response(): void
    {
        $response = $this->postJson($this->webhookUrl, ['event' => 'invalid_event']);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee('Unauthorized');
    }

    public function test_valid_webhook_events_trigger_appropriate_handlers(): void
    {
        $this->withValidCredentials();
        Event::fake();

        $payload = ['event' => 'valid_event'];

        $response = $this->postJson($this->webhookUrl, $payload);

        $response->assertStatus(Response::HTTP_OK);
        Event::assertDispatched(WebhookReceived::class, function ($event) use ($payload) {
            return $event->payload === $payload;
        });
    }

    public function test_handle_customer_deleted(): void
    {
        $this->withValidCredentials();

        $user = $this->createCustomer('customer_deleted', [
            'chargebee_id' => 'customer_id_123',
            'trial_ends_at' => now(),
            'pm_type' => 'visa',
            'pm_last_four' => '1234',
        ]);

        $payload = [
            'event_type' => 'customer_deleted',
            'content' => [
                'customer' => ['id' => 'customer_id_123'],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);

        $user->refresh();

        $this->assertNull($user->chargebee_id);
        $this->assertNull($user->trial_ends_at);
        $this->assertNull($user->pm_type);
        $this->assertNull($user->pm_last_four);
    }

    public function test_no_handler_found_logs_info_message(): void
    {
        $this->withValidCredentials();

        Log::swap(\Mockery::mock(\Illuminate\Log\LogManager::class)->shouldIgnoreMissing());

        Log::shouldReceive('info')
            ->once()
            ->with('WebhookReceived: No handler found for event_type: unknown_event', \Mockery::any());

        $payload = [
            'event_type' => 'unknown_event',
            'content' => [],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);
    }

    public function test_customer_deletion_logs_no_matching_user_found(): void
    {
        $this->withValidCredentials();

        Log::swap(\Mockery::mock(\Illuminate\Log\LogManager::class)->shouldIgnoreMissing());

        Log::shouldReceive('info')
            ->once()
            ->with('Customer deletion attempted, but no matching user found.', [
                'customer_id' => 'non_existent_customer_id',
            ]);

        $payload = [
            'event_type' => 'customer_deleted',
            'content' => [
                'customer' => ['id' => 'non_existent_customer_id'],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);
    }

    public function test_handle_customer_changed(): void
    {
        $this->withValidCredentials();
        $user = $this->createCustomer('test_handle_customer_changed');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $updateOptions = [
            'email' => 'testcustomerchanged@cashier-chargebee.com',
        ];

        $customer = $user->updateChargebeeCustomer($updateOptions);
        $this->assertSame('testcustomerchanged@cashier-chargebee.com', $customer->email);

        $payload = [
            'event_type' => 'customer_changed',
            'content' => [
                'customer' => ['id' => $user->chargebeeId()],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);

        $user->refresh();

        $this->assertSame('testcustomerchanged@cashier-chargebee.com', $user->email);
        $this->assertSame($paymentSource->card->brand, $user->pm_type);
        $this->assertSame($paymentSource->card->last4, $user->pm_last_four);
    }

    public function test_customer_change_logs_no_matching_user_found(): void
    {
        $this->withValidCredentials();

        Log::swap(\Mockery::mock(\Illuminate\Log\LogManager::class)->shouldIgnoreMissing());

        Log::shouldReceive('info')
            ->once()
            ->with('Customer update attempted, but no matching user found.', [
                'customer_id' => 'non_existent_customer_id',
            ]);

        $payload = [
            'event_type' => 'customer_changed',
            'content' => [
                'customer' => ['id' => 'non_existent_customer_id'],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);
    }

    public function test_subscription_creation_logs_no_matching_user_found(): void
    {
        $this->withValidCredentials();

        Log::swap(\Mockery::mock(\Illuminate\Log\LogManager::class)->shouldIgnoreMissing());

        Log::shouldReceive('info')
            ->once()
            ->with('Subscription creation for a customer attempted, but no matching user found.', [
                'customer_id' => 'non_existent_customer_id',
            ]);

        $payload = [
            'event_type' => 'subscription_created',
            'content' => [
                'subscription' => [
                    'id' => 'subscription_123',
                    'customer_id' => 'non_existent_customer_id',
                    'status' => 'active',
                    'subscription_items' => [
                        ['item_price_id' => 'price_123', 'quantity' => 1],
                    ],
                ],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);
    }

    public function test_subscription_creation_logs_subscription_already_exists(): void
    {
        $this->withValidCredentials();

        $user = User::factory()->create(['chargebee_id' => 'customer_chargebee_id']);
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        Log::swap(\Mockery::mock(\Illuminate\Log\LogManager::class)->shouldIgnoreMissing());

        Log::shouldReceive('info')
            ->once()
            ->with('Subscription creation attempted, but subscription already exists.', [
                'subscription_id' => $subscription->id,
                'chargebee_subscription_id' => $subscription->chargebee_id,
            ]);

        $payload = [
            'event_type' => 'subscription_created',
            'content' => [
                'subscription' => [
                    'id' => $subscription->chargebee_id,
                    'customer_id' => $user->chargebeeId(),
                    'status' => 'active',
                    'subscription_items' => [
                        ['item_price_id' => 'price_123', 'quantity' => 1],
                    ],
                ],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);
    }

    public function test_handle_subscription_created(): void
    {
        $this->withValidCredentials();

        $user = User::factory()->create(['chargebee_id' => 'customer_chargebee_id']);

        $trialEndsAt = now()->addDays(7);
        $subscriptionId = 'subscription_123';
        $itemPriceId = 'price_123';

        $payload = [
            'event_type' => 'subscription_created',
            'content' => [
                'subscription' => [
                    'id' => $subscriptionId,
                    'customer_id' => $user->chargebeeId(),
                    'status' => 'active',
                    'trial_end' => $trialEndsAt->getTimestamp(),
                    'subscription_items' => [
                        ['item_price_id' => $itemPriceId, 'quantity' => 2],
                    ],
                    'meta_data' => ['type' => 'main'],
                ],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'type' => 'main',
            'chargebee_id' => $subscriptionId,
            'chargebee_status' => 'active',
            'chargebee_price' => $itemPriceId,
            'quantity' => 2,
            'trial_ends_at' => $trialEndsAt->toDateTimeString(),
            'ends_at' => null,
        ]);

        $this->assertDatabaseHas('subscription_items', [
            'chargebee_product' => 'product_abc',
            'chargebee_price' => $itemPriceId,
            'quantity' => 2,
        ]);
    }

    public function test_subscription_change_logs_when_user_not_found(): void
    {
        $this->withValidCredentials();

        Log::swap(\Mockery::mock(\Illuminate\Log\LogManager::class)->shouldIgnoreMissing());

        Log::shouldReceive('info')
            ->once()
            ->with('Subscription update attempted, but no matching user found.', [
                'customer_id' => 'non_existent_customer_id',
            ]);

        $payload = [
            'event_type' => 'subscription_changed',
            'content' => [
                'subscription' => [
                    'id' => 'subscription_123',
                    'customer_id' => 'non_existent_customer_id',
                    'status' => 'active',
                    'trial_end' => now()->addDays(7)->getTimestamp(),
                    'subscription_items' => [
                        ['item_price_id' => 'price_123', 'quantity' => 2],
                    ],
                    'meta_data' => ['type' => 'main'],
                ],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);
    }

    public function test_handle_subscription_changed(): void
    {
        $this->withValidCredentials();

        $user = User::factory()->create(['chargebee_id' => 'customer_chargebee_id']);

        $subscription = $user->subscriptions()->create([
            'chargebee_id' => 'subscription_123',
            'chargebee_status' => 'in_trial',
            'chargebee_price' => 'old_price',
            'quantity' => 1,
            'trial_ends_at' => now()->addDays(5),
            'ends_at' => null,
            'type' => 'main',
        ]);

        $trialEndsAt = now();
        $cancelledAt = now()->addDays(30);
        $subscriptionId = 'subscription_123';
        $newItemPriceId = 'price_456';
        $newQuantity = 3;
        $newStatus = 'active';
        $newType = 'main';

        $payload = [
            'event_type' => 'subscription_changed',
            'content' => [
                'subscription' => [
                    'id' => $subscriptionId,
                    'customer_id' => $user->chargebeeId(),
                    'status' => $newStatus,
                    'trial_end' => $trialEndsAt->getTimestamp(),
                    'cancelled_at' => $cancelledAt->getTimestamp(),
                    'subscription_items' => [
                        ['item_price_id' => $newItemPriceId, 'quantity' => $newQuantity],
                    ],
                    'meta_data' => ['type' => $newType],
                ],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'chargebee_id' => $subscriptionId,
            'chargebee_status' => $newStatus,
            'chargebee_price' => $newItemPriceId,
            'quantity' => $newQuantity,
            'trial_ends_at' => $trialEndsAt->toDateTimeString(),
            'ends_at' => $cancelledAt->toDateTimeString(),
            'type' => $newType,
        ]);

        $this->assertDatabaseHas('subscription_items', [
            'chargebee_product' => 'product_abc',
            'chargebee_price' => $newItemPriceId,
            'quantity' => $newQuantity,
        ]);
    }

    public function test_subscription_renewal_logs_when_user_not_found(): void
    {
        $this->withValidCredentials();

        Log::swap(\Mockery::mock(\Illuminate\Log\LogManager::class)->shouldIgnoreMissing());

        Log::shouldReceive('info')
            ->once()
            ->with('Subscription renewal attempted, but no matching user found.', [
                'customer_id' => 'non_existent_customer_id',
            ]);

        $payload = [
            'event_type' => 'subscription_renewed',
            'content' => [
                'subscription' => [
                    'id' => 'subscription_123',
                    'customer_id' => 'non_existent_customer_id',
                    'status' => 'active',
                    'trial_end' => now()->addDays(7)->getTimestamp(),
                    'subscription_items' => [
                        ['item_price_id' => 'price_123', 'quantity' => 2],
                    ],
                    'meta_data' => ['type' => 'main'],
                ],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);
    }

    public function test_handle_subscription_renewed(): void
    {
        $this->withValidCredentials();

        $user = User::factory()->create(['chargebee_id' => 'customer_chargebee_id']);

        $subscriptionId = 'subscription_123';
        $itemPriceId = 'price_123';
        $quantity = 2;

        $payload = [
            'event_type' => 'subscription_renewed',
            'content' => [
                'subscription' => [
                    'id' => $subscriptionId,
                    'customer_id' => $user->chargebeeId(),
                    'status' => 'active',
                    'subscription_items' => [
                        ['item_price_id' => $itemPriceId, 'quantity' => $quantity],
                    ],
                    'meta_data' => ['type' => 'main'],
                ],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);

        $this->assertDatabaseHas('subscriptions', [
            'type' => 'main',
            'chargebee_id' => $subscriptionId,
            'chargebee_status' => 'active',
            'chargebee_price' => $itemPriceId,
            'quantity' => $quantity,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        $this->assertDatabaseHas('subscription_items', [
            'chargebee_product' => 'product_abc',
            'chargebee_price' => $itemPriceId,
            'quantity' => $quantity,
        ]);
    }

    protected function withValidCredentials(): void
    {
        $username = config('cashier.webhook.username');
        $password = config('cashier.webhook.password');

        $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode("$username:$password"),
        ]);
    }

    private function createCard(Model $user): ?PaymentSource
    {
        $chargebee = Cashier::chargebee();
        return $chargebee->paymentSource()->createCard([
            'customer_id' => $user->chargebeeId(),
            'card' => [
                'number' => '4111 1111 1111 1111',
                'cvv' => '123',
                'expiry_year' => date('Y', strtotime('+ 1 year')),
                'expiry_month' => date('m', strtotime('+ 1 year')),
            ],
        ]
        )->payment_source;
    }
}
