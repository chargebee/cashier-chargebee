# Laravel Cashier (Chargebee)

- [Installation](#installation)
- [Configuration](#configuration)
  - [Billable Model](#billable-model)
  - [Chargebee API](#chargebee-api)
  - [Currency Configuration](#currency-configuration)
  - [Using Custom Models](#using-custom-models)
- [Customers](#customers)
  - [Retrieving Customers](#retrieving-customers)
  - [Creating Customers](#creating-customers)
  - [Updating Customers](#updating-customers)
  - [Syncing Customers](#syncing-customers)
  - [Tax exemption](#tax-exemption)
  - [Billing Portal](#billing-portal)
  - [Balances](#balances)
- [Handling Chargebee Webhooks](#handling-chargebee-webhooks)
  - [Configuring Webhooks in Chargebee](#configuring-webhooks-in-chargebee)
  - [Route Configuration](#route-configuration)
  - [Configuring Basic Authentication](#configuring-basic-authentication)
  - [Handling Webhook Events](#handling-webhook-events)
- [Checkout](#checkout)
  - [Product Checkouts](#product-checkouts)
  - [Checkout Modes](#checkout-modes)
  - [Single Charge Checkouts](#single-charge-checkouts)
  - [Subscription Checkouts](#subscription-checkouts)
  - [Capturing a Card in Checkout](#capturing-a-card-in-checkout)
  - [Guest Checkouts](#guest-checkouts)
- [Manage Payment Methods](#manage-payment-methods)
  - [Creating a SetupIntent](#payment-methods-create-setupintent)
  - [Retrieving a SetupIntent](#payment-methods-find-setupintent)
  - [Checking for Available Payment Methods](#payment-methods-has)
  - [Retrieving a Customer's Payment Methods](#payment-methods-list)
  - [Adding a Payment Method](#payment-methods-add)
  - [Retrieving a Specific Payment Method](#payment-methods-find)
  - [Retrieving the Default Payment Method](#payment-methods-default)
  - [Setting the Default Payment Method](#payment-methods-set-default)
  - [Synchronizing the Default Payment Method from Chargebee](#payment-methods-sync-default)
  - [Deleting a Payment Method](#payment-methods-delete)
  - [Deleting Payment Methods of a Specific Type](#payment-methods-delete-multiple)
- [Subscriptions](#subscriptions)
  - [Setting Up Subscriptions in Chargebeee](#setting-up-subscriptions-in-chargebee)
  - [Subscription Lifecycle & Statuses](#subscription-lifecycle--statuses)
  - [Creating Subscriptions](#creating-subscriptions)
  - [Checking Subscription Status](#checking-subscription-status)
  - [Subscription Items](#subscription-items)
  - [Updating a Subscription in Chargebee](#updating-a-subscription-in-chargebee)
  - [Changing Prices](#changing-prices)
  - [Subscription Quantity](#subscription-quantity)
  - [Usage Based Billing](#usage-based-billing)
  - [Cancelling Subscriptions](#cancelling-subscriptions)
  - [Resuming Subscriptions](#resuming-subscriptions)
  - [Multiple Subscriptions](#multiple-subscriptions)
- [Subscription Trials](#subscription-trials)
  - [With Payment Method Up Front](#with-payment-method-up-front)
  - [Generic Trials (Without Payment Method Up Front)](#generic-trials-without-payment-method-up-front)
  - [Checking And Ending Trials](#checking-and-ending-trials)
  - [Extending a Trial](#extending-a-trial)
- [Single Charges](#single-charges)
  - [Creating Payment Intents](#creating-payment-intents)
  - [Payment Status Helpers](#payment-status-helpers)
  - [Finding Payment Intents](#finding-payment-intents)
  - [Charge With Invoice](#charge-with-invoice)
- [Invoices](#invoices)
  - [Retrieving Invoices](#retrieving-invoices)
  - [Upcoming Invoices](#upcoming-invoices)
  - [Previewing Subscription Invoices](#previewing-subscription-invoices)
  - [Generating Invoice PDFs](#generating-invoice-pdfs)

<a name="installation"></a>

## Installation

First, install the Cashier package for Chargebee using the Composer package
manager:

```shell
composer require chargebee/cashier
```

After installing the package, publish Cashier's migrations using the
`vendor:publish` Artisan command:

```shell
php artisan vendor:publish --tag="cashier-migrations"
```

Then, migrate your database:

```shell
php artisan migrate
```

Cashier's migrations will add several columns to your `users` table. They will
also create a new `subscriptions` table to hold all of your customer's
subscriptions and a `subscription_items` table for subscriptions with multiple
prices.

If you wish, you can also publish Cashier's configuration file using the
`vendor:publish` Artisan command:

```shell
php artisan vendor:publish --tag="cashier-config"
```

<a name="configuration"></a>

## Configuration

<a name="billable-model"></a>

### Billable Model

Before using Cashier, add the `Billable` trait to your billable model
definition. Typically, this will be the `App\Models\User` model. This trait
provides various methods to allow you to perform common billing tasks, such as
creating subscriptions, applying coupons, and updating payment method
information:

```php
use Chargebee\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

Cashier assumes your billable model will be the `App\Models\User` class that
ships with Laravel. If you wish to change this you may specify a different model
via the `useCustomerModel` method. This method should typically be called in the
`boot` method of your `AppServiceProvider` class:

```php
use App\Models\Cashier\User;
use Chargebee\Cashier\Cashier;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Cashier::useCustomerModel(User::class);
}
```

> [!WARNING]  
> If you're using a model other than Laravel's supplied `App\Models\User` model,
> you'll need to publish and alter the [Cashier migrations](#installation)
> provided to match your alternative model's table name.

<a name="chargebee-api"></a>

### Chargebee API

Next, you should configure your Chargebee API key and domain name in your
application's `.env` file:

```ini
CHARGEBEE_SITE=your-chargebee-site
CHARGEBEE_API_KEY=your-api-key
```

- `CHARGEBEE_SITE`: This is the unique name of your Chargebee instance. It is
  used to construct the base URL for API requests. The base URL for API calls
  will look like this:

  ```
      https://<CHARGEBEE_SITE>.chargebee.com/api/v2
  ```

  You can read more about configuring the domain name in the
  [Chargebee documentation](https://www.chargebee.com/docs/2.0/sites-intro.html#configuring-domain-name).

- `CHARGEBEE_API_KEY`: This is the API key that authenticates your API requests
  to Chargebee. You can learn how to generate Chargebee API keys
  [here](https://www.chargebee.com/docs/api_keys.html).

<a name="currency-configuration"></a>

### Currency Configuration

The default Cashier currency is United States Dollars (USD). You can change the
default currency by setting the `CASHIER_CURRENCY` environment variable within
your application's `.env` file:

```ini
CASHIER_CURRENCY=eur
```

In addition to configuring Cashier's currency, you may also specify a locale to
be used when formatting money values for display on invoices. Internally,
Cashier utilizes
[PHP's `NumberFormatter` class](https://www.php.net/manual/en/class.numberformatter.php)
to set the currency locale:

```ini
CASHIER_CURRENCY_LOCALE=nl_BE
```

> [!WARNING]  
> In order to use locales other than `en`, ensure the `ext-intl` PHP extension
> is installed and configured on your server.

<a name="using-custom-models"></a>

### Using Custom Models

You are free to extend the models used internally by Cashier by defining your
own model and extending the corresponding Cashier model:

```php
use Chargebee\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    // ...
}
```

After defining your model, you may instruct Cashier to use your custom model via
the `Chargebee\Cashier\Cashier` class. Typically, you should inform Cashier
about your custom models in the `boot` method of your application's
`App\Providers\AppServiceProvider` class:

```php
use App\Models\Cashier\Subscription;
use App\Models\Cashier\SubscriptionItem;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Cashier::useSubscriptionModel(Subscription::class);
    Cashier::useSubscriptionItemModel(SubscriptionItem::class);
}
```

<a name="customers"></a>

## Customers

<a name="retrieving-customers"></a>

### Retrieving Customers

You can retrieve a customer by their Chargebee ID using the
`Cashier::findBillable` method. This method will return an instance of the
billable model:

```php
use Chargebee\Cashier\Cashier;

$user = Cashier::findBillable($chargebeeId);
```

You may use the `asChargebeeCustomer` method if you want to return the Chargebee
customer object for a billable model:

```php
$chargebeeCustomer = $user->asChargebeeCustomer();
```

If `chargebee_id` on your model is missing or invalid, the method will throw a
`CustomerNotFound` exception.

<a name="creating-customers"></a>

### Creating Customers

Occasionally, you may wish to create a Chargebee customer without beginning a
subscription. You may accomplish this using the `createAsChargebeeCustomer`
method:

```php
$chargebeeCustomer = $user->createAsChargebeeCustomer();
```

Once the customer has been created in Chargebee, you may begin a subscription at
a later date.

This method uses helper methods like `chargebeeFirstName`, `chargebeeLastName`,
`chargebeeEmail`, `chargebeePhone`, `chargebeeBillingAddress`,
`chargebeeLocale`, and `chargebeeMetaData` to populate default values for the
customer. You can override these methods in your model to customize which fields
are used. For example:

```php
/**
 * Get the default first name.
 */
public function chargebeeFirstName(): string|null
{
    return $this->custom_first_name;
}
```

You may provide an optional `$options` array to pass in any additional
[customer creation parameters that are supported by the Chargebee API](https://apidocs.eu.chargebee.com/docs/api/customers#create_a_customer):

```php
$chargebeeCustomer = $user->createAsChargebeeCustomer($options);
```

If you attempt to create a Chargebee customer for a model that already has a
`chargebee_id` (indicating that the customer already exists in Chargebee), the
method will throw a `CustomerAlreadyCreated` exception.

You can also use the `createOrGetChargebeeCustomer` method to retrieve an
existing Chargebee customer or create a new one if it does not exist:

```php
$chargebeeCustomer = $user->createOrGetChargebeeCustomer($options);
```

<a name="updating-customers"></a>

### Updating Customers

Occasionally, you may wish to update the Chargebee customer directly with
additional information. You may accomplish this using the
`updateChargebeeCustomer` method. This method accepts an array of
[customer](https://apidocs.chargebee.com/docs/api/customers?lang=php#update_a_customer)
and
[billing information](https://apidocs.chargebee.com/docs/api/customers#update_billing_info_for_a_customer)
update parameters supported by the Chargebee API:

```php
$options = [
    'firstName' => 'John',
    'lastName' => 'Doe',
    'phone' => '123456789',
    'billingAddress' => [
        'firstName' => 'John',
        'lastName' => 'Doe',
        'line1' => '221B Baker Street',
        'city' => 'London',
        'state' => 'England',
        'zip' => 'NW1 6XE',
        'country' => 'GB',
    ],
];

$customer = $user->updateChargebeeCustomer($options);
```

> [!NOTE] If you want to update the customer's billing information, you must
> provide a valid `billingAddress` in the options array. The `billingAddress`
> must be a non-empty array that contains at least one non-null, non-empty value
> (e.g., `line1`, `city`, `zip`, etc.). If `billingAddress` is missing or
> contains only `null` or empty strings, the billing update request will not be
> sent to Chargebee.

If `chargebee_id` on your model is missing or invalid, the method will throw a
`CustomerNotFound` exception.

You can also use the `updateOrCreateChargebeeCustomer` method to update an
existing Chargebee customer or create a new one if it does not exist:

```php
$chargebeeCustomer = $user->updateOrCreateChargebeeCustomer($options);
```

<a name="syncing-customers"></a>

### Syncing Customers

To sync the customer's information to Chargebee, you can use the
`syncChargebeeCustomerDetails` method. This method will update the Chargebee
customer with the latest information from your model:

```php
$customer = $user->syncChargebeeCustomerDetails();
```

This method uses helper methods like `chargebeeFirstName`, `chargebeeLastName`,
`chargebeeEmail`, `chargebeePhone`, `chargebeeBillingAddress`,
`chargebeeLocale`, and `chargebeeMetaData` to determine the values to sync. You
can override these methods in your model to customize the sync process.

If you want to sync the customer's information or create a new Chargebee
customer if one does not exist, you can use the `syncOrCreateChargebeeCustomer`
method:

```php
$customer = $user->syncOrCreateChargebeeCustomer($options);
```

<a name="tax-exemption"></a>

### Tax Exemption

Cashier offers the `isNotTaxExempt` and `isTaxExempt` methods to determine if
the customer is tax exempt. These methods will call the Chargebee API to
determine a customer's taxability status:

```php
use App\Models\User;

$user = User::find(1);

$user->isTaxExempt();
$user->isNotTaxExempt();
```

<a name="billing-portal"></a>

### Billing Portal

Chargebee offers
[an easy way to set up a billing portal](https://www.chargebee.com/docs/2.0/self-serve-portal.html)
so that your customer can manage their subscription, payment methods, and view
their billing history. You can redirect your users to the billing portal by
invoking the `redirectToBillingPortal` method on the billable model from a
controller or route:

```php
use Illuminate\Http\Request;

Route::get('/billing-portal', function (Request $request) {
    return $request->user()->redirectToBillingPortal();
});
```

By default, when the user is finished managing their subscription, they will
return to the `home` route of your application upon logout from the portal UI.
You may provide a custom URL that the user should return to by passing the URL
as an argument to the `redirectToBillingPortal` method:

```php
use Illuminate\Http\Request;

Route::get('/billing-portal', function (Request $request) {
    return $request->user()->redirectToBillingPortal(route('billing'));
});
```

If you would like to generate the URL to the billing portal without generating
an HTTP redirect response, you may invoke the `billingPortalUrl` method:

```php
$url = $request->user()->billingPortalUrl(route('billing'));
```

<a name="balances"></a>

### Balances

Chargebee allows you to credit or debit a customer's "balance". Later, this
balance will be credited or debited on new invoices. To check the customer's
total balance in a formatted string representation of their currency, you may
use the `balance` method:

```php
$balance = $user->balance();
```

If you need the raw, unformatted total balance (e.g., for calculations), you can
use the `rawBalance` method:

```php
$rawBalance = $user->rawBalance();
```

To credit a customer's balance, you may use the `creditBalance method`. You can
provide the amount to be credited and an optional description:

```php
$user->creditBalance(500, 'Add promotional credits.');
```

Similarly, to debit a customer's balance, use the `debitBalance` method. You can
specify the amount to be debited and an optional description:

```php
$user->debitBalance(300, 'Deduct promotional credits.');
```

Both `creditBalance` and `debitBalance` methods accept an optional `options`
array. This array allows you to include additional parameters supported by
Chargebee's
[Promotional Credits API](https://apidocs.eu.chargebee.com/docs/api/promotional_credits).
For example, you can describe why promotional credits were provided in a
`reference` parameter:

```php
$user->creditBalance(500, 'Add promotional credits.', [
    'reference' => 'referral_bonus_2023',
]);
```

The `applyBalance` method will automatically determine whether to credit or
debit the customer's balance based on the sign of the amount:

```php
$user->applyBalance(500, 'Add credits.'); // Credits 500
$user->applyBalance(-300, 'Deduct credits.'); // Debits 300
```

Like the `creditBalance` and `debitBalance` methods, `applyBalance` also accepts
an `options` array for additional API parameters:

```php
$user->applyBalance(1000, 'Promotional credits applied.', [
    'reference' => 'promo123',
]);
```

> [!NOTE] The `amount` parameter in `creditBalance`, `debitBalance`, and
> `applyBalance` is specified in the smallest currency unit (e.g., cents for USD
> or euro cents for EUR). For example, passing `500` with a currency of EUR will
> represent 5 euros.

To retrieve the transaction history for a customer's balance, use the
balanceTransactions method:

```php
$transactions = $user->balanceTransactions();
```

This method allows you to specify a `limit` (default is 10) and an `options`
array for additional filtering. For example, to retrieve 20 transactions
filtered by a specific type:

```php
$transactions = $user->balanceTransactions(20, [
    'type[is]' => 'increment',
]);
```

The returned transactions are a collection of promotional credit objects, which
can be iterated over or manipulated as needed.

<a name="handling-chargebee-webhooks"></a>

## Handling Chargebee Webhooks

Any change that happens in Chargebee is captured as an event. Webhook
configuration allows Chargebee to send event notifications to your system.

<a name="configuring-webhooks-in-chargebee"></a>

### Configuring Webhooks in Chargebee

To configure webhooks in Chargebee, follow the
[Chargebee webhook documentation](https://www.chargebee.com/docs/2.0/webhook_settings.html).
You should set up your webhook URL to point to your application's webhook
endpoint, typically:

```
https://your-application.com/chargebee/webhook
```

<a name="route-configuration"></a>

### Route Configuration

Webhook route is registered automatically when Cashier is loaded. The
`chargebee` prefix is derived from the `CASHIER_PATH` configuration variable. If
you want to customize this prefix, you can update the `CASHIER_PATH` variable in
your `.env` file:

```ini
CASHIER_PATH=custom-path
```

For example, setting `CASHIER_PATH=custom-path` would change the webhook URL to:

```
https://your-application.com/custom-path/webhook
```

<a name="configuring-basic-authentication"></a>

### Configuring Basic Authentication

Set up Basic Authentication by adding the following variables to your .env file:

```ini
CASHIER_WEBHOOK_USERNAME=your_webhook_username
CASHIER_WEBHOOK_PASSWORD=your_webhook_password
```

Cashier will automatically verify these credentials for incoming webhook
requests.

<a name="handling-webhook-events"></a>

### Handling Webhook Events

Cashier emits a `WebhookReceived` event for every incoming webhook, allowing you
to handle these events in your application. By default, Cashier includes a
`HandleWebhookReceived` listener that processes customer-related events like
`customer_deleted` and `customer_updated`, as well as subscription-related
events such as `subscription_created`, `subscription_changed`, and
`subscription_renewed`.

#### customer_deleted

The `customer_deleted` event ensures that when a customer is deleted in
Chargebee, their corresponding record in your application is updated accordingly
by removing their Chargebee ID.

#### customer_changed

The `customer_changed` event uses the `updateCustomerFromChargebee` method on
your billable model to synchronize customer information. By default, this method
maps Chargebee data to common fields such as `email` and `phone`. However, to
ensure the correct mapping for your application, **you should override this
method in your billable model**. This allows you to define how Chargebee
customer attributes should be mapped to your model's columns. Additionally, you
can include more data beyond the default fields, as Chargebee provides a wide
range of customer attributes. For a full list, see the
[Chargebee API documentation](https://apidocs.eu.chargebee.com/docs/api/customers?prod_cat_ver=2&lang=php#customer_attributes).
Here’s an example implementation:

```php
/**
 * Update customer with data from Chargebee.
 */
public function updateCustomerFromChargebee(): void
{
    $customer = $this->asChargebeeCustomer();

    $chargebeeData = [
        'name' => $customer->firstName.' '.$customer->lastName,
        'email' => $customer->email,
        'phone' => $customer->phone,
        'billing_first_name' => $customer->billingAddress->firstName ?? null,
        'billing_last_name' => $customer->billingAddress->lastName ?? null,
        'billing_line1' => $customer->billingAddress->line1 ?? null,
        'billing_city' => $customer->billingAddress->city ?? null,
        'billing_address_state' => $customer->billingAddress->state ?? null,
        'billing_address_country' => $customer->billingAddress->country ?? null,
    ];

    $this->update($chargebeeData);
}
```

Additionally, the `customer_changed` event handler calls the
`updateDefaultPaymentMethodFromChargebee` method to synchronize the default
payment method from Chargebee to your application.

#### subscription_created

The `subscription_created` event is triggered when a new subscription is created
in Chargebee. Cashier listens for this event and automatically ensures that the
corresponding subscription is created in your application's database.

#### subscription_changed

The `subscription_changed` event is emitted whenever an existing subscription is
updated in Chargebee. This includes modifications such as:

- Price changes
- Quantity updates
- Status updates (e.g., from trial to active)

Cashier listens for this event and synchronizes the subscription data in your
database accordingly.

For example, if a user upgrades their plan from basic to premium, the system
will update the subscription details and maintain consistency with Chargebee.

#### subscription_renewed

The `subscription_renewed` event occurs when a subscription is successfully
renewed at the end of its billing cycle. Cashier updates the subscription
details in the database, ensuring consistency with Chargebee.

#### Customizing Webhook Handling

If you need to modify the default behavior for these webhook events, or handle
additional Chargebee events, you can provide your own listener. The listener
class is configurable via the `cashier` configuration file:

```init
'webhook_listener' => \Chargebee\Cashier\Listeners\HandleWebhookReceived::class,
```

<a name="checkout"></a>

## Checkout

Cashier Chargebee also provides support for
[Chargebee Hosted Pages](https://apidocs.chargebee.com/docs/api/hosted_pages).
Chargebee Hosted Pages takes the pain out of implementing custom pages to accept
payments by providing a pre-built, hosted payment page.

The following documentation contains information on how to get started using
Chargebee Checkout with Cashier.

### Checkout Modes

Cashier Chargebee supports different checkout modes depending on the type of
transaction you want to perform:

- [`Session::MODE_PAYMENT`](https://apidocs.chargebee.com/docs/api/hosted_pages?prod_cat_ver=2&lang=php#checkout_charge-items_and_one-time_charges)
  (default): Used for processing charge-items and one-time charges.

- [`Session::MODE_SUBSCRIPTION`](https://apidocs.chargebee.com/docs/api/hosted_pages?prod_cat_ver=2&lang=php#create_checkout_for_a_new_subscription):
  Used to initiate a new subscription checkout session.

- [`Session::MODE_SETUP`](https://apidocs.chargebee.com/docs/api/hosted_pages?prod_cat_ver=2&lang=php#manage_payment_sources):
  Used to add new or update existing payment sources for the customer.

<a name="product-checkouts"></a>

### Product Checkouts

You may perform a checkout for an existing product that has been created within
your Chargebee dashboard using the `checkout` method on a billable model. The
`checkout` method will initiate a new Chargebee `checkout_one_time` Hosted Page.
By default, you're required to pass a Chargebee ItemPrices ID:

    use Illuminate\Http\Request;

    Route::get('/product-checkout', function (Request $request) {
        return $request->user()->checkout('price_tshirt');
    });

If needed, you may also specify a product quantity:

    use Illuminate\Http\Request;

    Route::get('/product-checkout', function (Request $request) {
        return $request->user()->checkout(['price_tshirt' => 15]);
    });

When a customer visits this route they will be redirected to Chargebee's
Checkout page. By default, when a user successfully completes or cancels a
purchase they will be redirected to your `home` route location, but you may
specify custom callback URLs using the `success_url` and `cancel_url` options:

    use Illuminate\Http\Request;

    Route::get('/product-checkout', function (Request $request) {
        return $request->user()->checkout(['price_tshirt' => 1], [
            'success_url' => route('your-success-route'),
            'cancel_url' => route('your-cancel-route'),
        ]);
    });

<a name="single-charge-checkouts"></a>

### Single Charge Checkouts

You can also perform a simple charge for an ad-hoc product that has not been
created in your Chargebee dashboard. To do so you may use the `checkoutCharge`
method on a billable model and pass it a chargeable amount and a product name.
When a customer visits this route they will be redirected to Chargebee's
Checkout page:

    use Illuminate\Http\Request;

    Route::get('/charge-checkout', function (Request $request) {
        return $request->user()->checkoutCharge(1200, 'T-Shirt');
    });

<a name="subscription-checkouts"></a>

### Subscription Checkouts

> [!WARNING]  
> Using Chargebee Checkout for subscriptions requires you to enable the
> `subscription_created` webhook in your Chargebee dashboard. This webhook will
> create the subscription record in your database and store all of the relevant
> subscription items.

You may also use Chargebee Checkout to initiate subscriptions. After defining
your subscription with Cashier's subscription builder methods, you may call the
`checkout `method. When a customer visits this route they will be redirected to
Chargebee's Checkout page:

    use Illuminate\Http\Request;

    Route::get('/subscription-checkout', function (Request $request) {
        return $request->user()
            ->newSubscription('default', 'price_monthly')
            ->checkout();
    });

Just as with product checkouts, you may customize the success and cancellation
URLs:

    use Illuminate\Http\Request;

    Route::get('/subscription-checkout', function (Request $request) {
        return $request->user()
            ->newSubscription('default', 'price_monthly')
            ->checkout([
                'success_url' => route('your-success-route'),
                'cancel_url' => route('your-cancel-route'),
            ]);
    });

### Capturing a Card in Checkout

You can capture a customer's card details using a Chargebee Checkout session. To
do this, you need to create a checkout session with `Session::MODE_SETUP`:

    use Illuminate\Http\Request;

    public function captureCard(Request $request) {
        return $request->user()->checkout([], [
            'mode' => Session::MODE_SETUP,
        ]);
    }

When a customer visits this route, they will be redirected to a Chargebee
Checkout page where they can enter their card details.

You can also specify success and cancellation URLs:

    use Illuminate\Http\Request;

    public function captureCard(Request $request) {
        return $request->user()->checkout([], [
            'mode' => Session::MODE_SETUP,
            'success_url' => route('your-success-route'),
            'cancel_url' => route('your-cancel-route'),
        ]);
    }

<a name="guest-checkouts"></a>

### Guest Checkouts

Using the `Checkout::guest` method, you may initiate checkout sessions for
guests of your application that do not have an "account":

    use Illuminate\Http\Request;
    use Chargebee\Cashier\Checkout;

    Route::get('/product-checkout', function (Request $request) {
        return Checkout::guest()->create('price_tshirt', [
            'success_url' => route('your-success-route'),
            'cancel_url' => route('your-cancel-route'),
        ]);
    });

Similarly to when creating checkout sessions for existing users, you may utilize
additional methods available on the `Chargebee\Cashier\CheckoutBuilder` instance
to customize the guest checkout session:

    use Illuminate\Http\Request;
    use Chargebee\Cashier\Checkout;

    Route::get('/product-checkout', function (Request $request) {
        return Checkout::guest()
            ->withPromotionCode('promo-code')
            ->create('price_tshirt', [
                'success_url' => route('your-success-route'),
                'cancel_url' => route('your-cancel-route'),
            ]);
    });

<a name="manage-payment-methods"></a>

## Managing Payment Methods

**Ensure that 3D Secure (3DS) is enabled in your Chargebee account settings to
utilize PaymentIntents.**

<a name="payment-methods-create-setupintent"></a>

### Creating a SetupIntent

The `createSetupIntent` method generates a new `PaymentIntent` with an amount of
`0`. This is primarily used to set up payment methods without processing an
immediate charge.

#### Method Signature:

```php
public function createSetupIntent(array $options = []): ?PaymentIntent
```

#### Parameters:

- `$options` (_array_, optional) – An associative array of additional parameters
  for the PaymentIntent.

#### Default Behavior:

- Ensures the customer exists before proceeding.
- The `customer_id` is automatically assigned based on the Chargebee ID of the
  user.
- The `amount` is fixed at `0`.
- The `currency_code` is determined from the provided `$options` array or falls
  back to the default configured in `config('cashier.currency')`.

#### Example Usage:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$setupIntent = $user->createSetupIntent([
    'currency_code' => 'USD',
]);

if ($setupIntent) {
    echo 'SetupIntent created successfully: ' . $setupIntent->id;
} else {
    echo 'Failed to create SetupIntent.';
}
```

#### Error Handling:

- If the `chargebee_id` is missing or invalid, a `CustomerNotFound` exception
  will be thrown.
- If the request to Chargebee is invalid, an `InvalidRequestException` may be
  thrown.

<a name="payment-methods-find-setupintent"></a>

### Retrieving a SetupIntent

The `findSetupIntent` method retrieves an existing `PaymentIntent` from
Chargebee using its unique identifier.

#### Method Signature:

```php
public function findSetupIntent(string $id): ?PaymentIntent
```

#### Parameters:

- `$id` (_string_) – The unique identifier of the PaymentIntent in Chargebee.

#### Example Usage:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$setupIntent = $user->findSetupIntent('pi_123456789');

if ($setupIntent) {
    echo 'SetupIntent retrieved successfully: ' . $setupIntent->id;
} else {
    echo 'SetupIntent not found.';
}
```

#### Error Handling:

- If the provided PaymentIntent ID is invalid or does not exist, Chargebee may
  throw an `InvalidRequestException`.

For more details, refer to the
[Chargebee PaymentIntent API documentation](https://apidocs.eu.chargebee.com/docs/api/payment_intents#create_a_payment_intent?target=_blank).

<a name="payment-methods-has"></a>

### Checking for Available Payment Methods

The `hasPaymentMethod` method checks whether a customer has at least one saved
payment method. Optionally, you can specify a payment method type to check for a
specific kind.

#### Method Signature:

```php
public function hasPaymentMethod(?string $type = null): bool
```

#### Parameters:

- `$type` (_string_, optional) – Specifies the type of payment method to check
  (e.g., `'card'`). If `null`, it checks for any payment method.

#### Example Usage:

##### Check if the Customer Has Any Payment Method:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

if ($user->hasPaymentMethod()) {
    echo 'The customer has at least one payment method.';
} else {
    echo 'No payment method found for the customer.';
}
```

##### Check if the Customer Has a Specific Payment Method Type (e.g., Card):

```php
if ($user->hasPaymentMethod('card')) {
    echo 'The customer has a card payment method.';
} else {
    echo 'No card payment method found for the customer.';
}
```

#### Behavior:

- Calls the `paymentMethods` method to retrieve a list of payment sources.
- Returns `true` if at least one payment method is found.
- Returns `false` if no payment methods exist.

#### Error Handling:

- If the customer does not exist in Chargebee, it may result in an exception
  when calling `paymentMethods()`.

<a name="payment-methods-list"></a>

### Retrieving a Customer's Payment Methods

The `paymentMethods` method returns a collection of the customer's saved payment
methods. Optionally, you can filter the results by payment method type.

#### Method Signature:

```php
public function paymentMethods(?string $type = null, array $parameters = []): ?Collection
```

#### Parameters:

- `$type` (_string_, optional) – Specifies the type of payment methods to
  retrieve (e.g., `'card'`). If `null`, all available payment methods are
  returned.
- `$parameters` (_array_, optional) – Additional filters for retrieving payment
  sources (e.g., custom limits or pagination options).

#### Default Behavior:

- If the customer does not have a Chargebee ID, an empty `Collection` is
  returned.
- The default limit for retrieved payment sources is set to `24`.
- The method queries Chargebee for payment methods matching the provided
  parameters.

#### Example Usage:

##### Retrieve All Available Payment Methods:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentMethods = $user->paymentMethods();

if ($paymentMethods->isNotEmpty()) {
    echo 'The customer has the following payment methods:';
    foreach ($paymentMethods as $method) {
        echo $method->id;
    }
} else {
    echo 'No payment methods found for the customer.';
}
```

##### Retrieve Only Card-Based Payment Methods:

```php
$cardPaymentMethods = $user->paymentMethods('card');

if ($cardPaymentMethods->isNotEmpty()) {
    echo 'The customer has card payment methods.';
} else {
    echo 'No card payment methods found for the customer.';
}
```

##### Retrieve Payment Methods with Additional Parameters (e.g., Custom Limit):

```php
$paymentMethods = $user->paymentMethods(null, ['limit' => 10]);
```

#### Behavior:

- Retrieves payment methods from Chargebee, filtering by customer ID and
  optionally by type.
- Returns a Laravel `Collection` of `PaymentSource` objects.
- Uses array filtering to remove null parameters before sending the request.

#### Error Handling:

- If the customer does not exist in Chargebee, the method returns an empty
  `Collection`.
- If the Chargebee API request fails or encounters an issue, an exception may be
  thrown.

<a name="payment-methods-add"></a>

### Adding a Payment Method

The `addPaymentMethod` method associates a new payment method with a customer in
Chargebee. Optionally, the method can also set the newly added payment method as
the default.

#### Method Signature:

```php
public function addPaymentMethod(PaymentSource $paymentSource, bool $setAsDefault = false): PaymentMethod
```

#### Parameters:

- `$paymentSource` (_PaymentSource_) – The payment method to be added to the
  customer's account.
- `$setAsDefault` (_bool_, optional) – If set to `true`, the newly added payment
  method will be assigned as the default. Default value: `false`.

#### Example Usage:

##### Add a Payment Method Without Setting It as Default:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentSource = new PaymentSource(/* Payment source details */);

$paymentMethod = $user->addPaymentMethod($paymentSource);

echo 'Payment method added successfully: ' . $paymentMethod->id;
```

##### Add a Payment Method and Set It as Default:

```php
$paymentMethod = $user->addPaymentMethod($paymentSource, true);

echo 'Payment method added and set as default: ' . $paymentMethod->id;
```

#### Behavior:

- Ensures that the customer exists in Chargebee before proceeding.
- If `$setAsDefault` is `true`, the method calls `updateDefaultPaymentMethod` to
  assign the newly added payment method as the default.
- Returns a `PaymentMethod` instance linked to the added `PaymentSource`.

#### Error Handling:

- If the customer does not exist in Chargebee, a `CustomerNotFound` exception
  will be thrown.
- If the provided payment method is invalid, an `InvalidPaymentMethod` exception
  will be thrown.
- If the request to Chargebee fails due to invalid parameters, an
  `InvalidRequestException` will be thrown.

<a name="payment-methods-find"></a>

### Retrieving a Specific Payment Method

To find a specific payment method for a customer, use the `findPaymentMethod`
method. This method accepts either a Chargebee `$chargeBeePaymentSource`
instance or a payment source ID as a string.

#### Examples:

##### Using a Chargebee Payment Source Instance:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentMethod = $user->findPaymentMethod($chargeBeePaymentSource);
```

##### Using a Payment Source ID:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentMethod = $user->findPaymentMethod('payment_source_id');
```

**Error Handling:**

- If the chargebee_id is missing or invalid, a CustomerNotFound exception will
  be thrown.
- If the specified payment method is not found, a PaymentMethodNotFound
  exception will be thrown.
- If the request payload sent to Chargebee is invalid, an
  InvalidRequestException will be thrown.

<a name="payment-methods-default"></a>

### Retrieving the Default Payment Method

The `defaultPaymentMethod` method returns the primary payment method associated
with the customer. If no default payment method is set, the method returns
`null`.

#### Method Signature:

```php
public function defaultPaymentMethod(): ?PaymentMethod
```

#### Example Usage:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$defaultPaymentMethod = $user->defaultPaymentMethod();

if ($defaultPaymentMethod) {
    // The customer has a default payment method
    echo 'Default Payment Method: ' . $defaultPaymentMethod->id;
} else {
    echo 'No default payment method found.';
}
```

#### Error Handling:

- If the `chargebee_id` is missing or invalid, a `CustomerNotFound` exception
  will be thrown.
- If the request to Chargebee is invalid, an `InvalidRequestException` will be
  thrown.
- If an invalid payment method is encountered, an `InvalidPaymentMethod`
  exception will be thrown.

<a name="payment-methods-set-default"></a>

### Setting the Default Payment Method

The `updateDefaultPaymentMethod` method allows you to designate a specific
payment method as the primary payment source for a customer in Chargebee.

#### Method Signature:

```php
public function updateDefaultPaymentMethod(PaymentSource|string $paymentSource): ?Customer
```

#### Parameters:

- `$paymentSource` (_PaymentSource|string_) – The payment method to be set as
  the default. This can be either a `PaymentSource` instance or a payment source
  ID as a string.

#### Example Usage:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentSourceId = 'pm_123456789';

try {
    $user->updateDefaultPaymentMethod($paymentSourceId);
    echo 'Default payment method updated successfully.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

#### Behavior:

- The method first verifies that the customer exists in Chargebee.
- It resolves the payment method using the provided `PaymentSource` instance or
  payment source ID.
- If a valid payment source is found, it assigns the `PRIMARY` role to the
  specified payment source.
- The payment method details are updated and saved for the customer.

#### Error Handling:

- If an invalid payment method is provided, an `InvalidPaymentMethod` exception
  is thrown.
- If the request to Chargebee is invalid, an `InvalidRequestException` is
  thrown.
- If the `chargebee_id` is missing or invalid, a `CustomerNotFound` exception is
  thrown.

<a name="payment-methods-sync-default"></a>

### Synchronizing the Default Payment Method from Chargebee

The `updateDefaultPaymentMethodFromChargebee` method updates the customer's
default payment method in the local database by fetching the latest details from
Chargebee.

#### Method Signature:

```php
public function updateDefaultPaymentMethodFromChargebee(): self
```

#### Example Usage:

##### Sync the Default Payment Method:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

try {
    $user->updateDefaultPaymentMethodFromChargebee();
    echo 'Default payment method synchronized successfully.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

#### Behavior:

- Retrieves the customer's default payment method using the
  `defaultPaymentMethod` method.
- If a valid `PaymentMethod` is found, updates the stored payment method details
  in the database.
- If no default payment method exists, resets the stored payment method details
  (`pm_type` and `pm_last_four`) to `null`.

#### Error Handling:

- If the customer does not exist in Chargebee, a `CustomerNotFound` exception
  will be thrown.
- If the retrieved payment method is invalid, an `InvalidPaymentMethod`
  exception will be thrown.
- If the request to Chargebee fails due to invalid parameters, an
  `InvalidRequestException` will be thrown.

<a name="payment-methods-delete"></a>

### Deleting a Payment Method

The `deletePaymentMethod` method removes a specified payment method from the
customer's Chargebee account.

#### Method Signature:

```php
public function deletePaymentMethod(PaymentSource $paymentSource): void
```

#### Parameters:

- `$paymentSource` (_PaymentSource_) – The payment method to be deleted.

#### Example Usage:

##### Delete a Specific Payment Method:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentSource = new PaymentSource(/* Payment source details */);

try {
    $user->deletePaymentMethod($paymentSource);
    echo 'Payment method deleted successfully.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

#### Behavior:

- Ensures the customer exists in Chargebee before proceeding.
- Validates that the provided `PaymentSource` belongs to the authenticated
  customer.
- If the payment method being deleted is the customer's default payment method,
  it clears the stored payment method details.
- Calls `PaymentSource::delete($paymentSource->id)` to remove the payment method
  from Chargebee.

#### Error Handling:

- If the customer does not exist in Chargebee, a `CustomerNotFound` exception
  will be thrown.
- If the provided payment method does not belong to the customer, an
  `InvalidPaymentMethod` exception will be thrown.
- If the request to Chargebee fails due to invalid parameters, an
  `InvalidRequestException` will be thrown.

<a name="payment-methods-delete-multiple"></a>

### Deleting Payment Methods of a Specific Type

The `deletePaymentMethods` method removes all payment methods of a specified
type for the customer.

#### Method Signature:

```php
public function deletePaymentMethods(string $type): void
```

#### Parameters:

- `$type` (_string_) – The type of payment methods to be deleted (e.g.,
  `'card'`).

#### Example Usage:

##### Delete All Card Payment Methods:

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

try {
    $user->deletePaymentMethods('card');
    echo 'All card payment methods have been deleted successfully.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

#### Behavior:

- Retrieves all payment methods of the specified type using the
  `paymentMethods($type)` method.
- Iterates through the retrieved payment methods and deletes each one using the
  `deletePaymentMethod` method.

#### Error Handling:

- If the customer does not exist in Chargebee, a `CustomerNotFound` exception
  will be thrown.
- If the request to Chargebee fails due to invalid parameters, an
  `InvalidRequestException` will be thrown.

<a name="subscriptions"></a>

## Subscriptions

Subscriptions enable businesses to offer their products or services on a
recurring basis, allowing customers to be billed at regular intervals. In
Chargebee, each subscription is linked to exactly one plan, which defines the
pricing, billing frequency, and renewal terms. Additionally, subscriptions can
include addons, charge and coupons.

<a name="setting-up-subscriptions-in-chargebee"></a>

### Setting Up Subscriptions in Chargebee

Before you can start managing subscriptions with this package, you must first
configure your
[Product Catalog](https://www.chargebee.com/docs/2.0/product-catalog.html) in
Chargebee. This involves creating:

- **Product Families** – Groupings of related plans, addons, and charges.
- **Plans** – Core subscription offerings that define pricing and billing
  cycles.
- **Addons & Charges** – Additional recurring (addons) or one-time (charges)
  fees that can be attached to a subscription.
- **Price Points** (Item Prices) – Variations of plans and addons based on
  currency and billing frequency.
- **Coupons** – Discounts and promotional offers applied to subscriptions.

<a name="subscription-lifecycle-statuses"></a>

### Subscription Lifecycle & Statuses

Every subscription in Chargebee progresses through multiple statuses:

- **Future** – The subscription is scheduled to start at a later date.
- **In Trial** – The subscription is active but in a trial period, during which
  the customer is not billed.
- **Active** – The subscription is fully operational, and recurring charges
  apply.
- **Non Renewing** – The subscription remains active but is scheduled to be
  canceled at the end of the current billing cycle.
- **Paused** – The subscription is temporarily suspended but can be resumed
  later.
- **Cancelled** – The subscription is no longer active and will not renew.

For a more detailed overview of how subscriptions work in Chargebee, refer to
the
[Chargebee Subscriptions Documentation](https://www.chargebee.com/docs/2.0/subscriptions.html).

<a name="creating-subscriptions"></a>

### Creating Subscriptions

To create a subscription, first retrieve an instance of your billable model,
which typically will be an instance of `App\Models\User`. Once you have
retrieved the model instance, you may use the `newSubscription` method to
initiate the subscription builder:

```php
use Illuminate\Http\Request;

Route::post('/user/subscribe', function (Request $request) {
    $request->user()->newSubscription(
        'default', 'price_monthly'
    )->create($request->paymentMethodId);

    // ...
});
```

The first argument passed to the `newSubscription` method should be the internal
type of the subscription. If your application only offers a single subscription,
you might call this `default` or `primary`. This subscription type is only for
internal application usage and is not meant to be shown to users. In addition,
it should not contain spaces and it should never be changed after creating the
subscription. The second argument is the specific price the user is subscribing
to. This value should correspond to the price's identifier in Chargebee.

The `create` method on the subscription builder accepts a Chargebee payment
source identifier or Chargebee `PaymentSource` object. It will begin the
subscription as well as create `subscriptions` and `subscription_items` records
your database. If you need to pass additional options supported by Chargebee for
the customer or subscription, you may provide them as the second and third
arguments to the `create` method:

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->create($paymentMethod, [
        'email' => $email,
    ], [
        'metaData' => json_encode(['note' => 'Some extra information.']),
    ]);
```

> [!WARNING]  
> Passing a payment source identifier directly to the `create` subscription
> method will also automatically add it to the user's stored payment methods.

If you would like to add a subscription to a customer who already has a default
payment method you may invoke the `add` method on the subscription builder:

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->add();
```

You can add an item to the subscription and specify its quantity using the
`price` method. This method requires the identifier of the Chargebee price,
either as a string or an array containing an `itemPriceId` key. An optional
second argument allows specifying the quantity of the item:

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->price('price_annual', 2)
    ->create($paymentMethod);
```

For metered billing, use the `meteredPrice` method. This method requires a
single argument, which is the identifier of the metered price in Chargebee:

```php
$subscription = $user->newSubscription('default', 'price_base')
    ->meteredPrice('price_usage')
    ->create($paymentMethod);
```

To set the quantity of a subscription item, use the `quantity` method. This
method takes a quantity as the first argument. If the subscription includes
multiple items, a second argument specifying the price identifier is required:

```php
$subscription = $user->newSubscription('default', 'price_standard')
    ->quantity(3)
    ->create($paymentMethod);
```

To define a trial period in days, use `trialDays`. This method requires an
integer specifying the number of trial days:

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->trialDays(14)
    ->create($paymentMethod);
```

To specify an exact trial end date, use `trialUntil`. This method accepts a
`Carbon` instance or any object implementing `CarbonInterface` representing the
trial's end date:

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->trialUntil(now()->addMonth())
    ->create($paymentMethod);
```

If you want to skip the trial period, use `skipTrial`:

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->skipTrial()
    ->create($paymentMethod);
```

To attach metadata to the subscription, use `withMetadata`. This method accepts
an associative array of metadata to store alongside the subscription:

```php
$subscription = $user->newSubscription('default', 'price_monthly')
    ->withMetadata(['source' => 'campaign_x'])
    ->create($paymentMethod);
```

<a name="checking-subscription-status"></a>

### Checking Subscription Status

Once a customer is subscribed, you can check their subscription status using
various methods on the billable model and the `Subscription` model.
Additionally, query scopes allow filtering subscriptions based on their status.

#### Methods on the Billable Model ($user)

You can check if a user has an active subscription, including during the trial
period, using the `subscribed` method. This method requires the subscription
type as an argument:

```php
if ($user->subscribed('default')) {
    // The user is subscribed
}
```

The `subscribed` method also makes a great candidate for a route middleware,
allowing you to filter access to routes and controllers based on the user's
subscription status:

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSubscribed
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->subscribed('default')) {
            // This user is not a paying customer...
            return redirect('/billing');
        }

        return $next($request);
    }
}
```

To check if a user is still within their trial period, use the `onTrial` method.
This method accepts the subscription type as the first argument and an optional
price identifier as the second argument:

```php
if ($user->onTrial('default', 'price_monthly')) {
    // The user is on a trial period
}
```

To determine if a user’s trial has expired, use the `hasExpiredTrial` method.
This method accepts the subscription type as the first argument:

```php
if ($user->hasExpiredTrial('default')) {
    // The user's trial has ended
}
```

To check if a user is on a generic trial at the model level, use the
`onGenericTrial` method:

```php
if ($user->onGenericTrial()) {
    // The user is on a generic trial
}
```

To determine if a user's generic trial has expired, use the
`hasExpiredGenericTrial` method:

```php
if ($user->hasExpiredGenericTrial()) {
    // The user's generic trial has ended
}
```

To check when a user's trial ends, use the `trialEndsAt` method:

```php
$trialEndsAt = $user->trialEndsAt('default');
```

To check if a user is subscribed to a specific product, use the
`subscribedToProduct` method. This method requires a product identifier as the
first argument and an optional subscription type as the second argument:

```php
if ($user->subscribedToProduct('prod_premium', 'default')) {
    // The user is subscribed to the premium product
}
```

By passing an array to the `subscribedToProduct` method, you may determine if
the user's `default` subscription is actively subscribed to the application's
"basic" or "premium" product:

```php
if ($user->subscribedToProduct(['prod_basic', 'prod_premium'], 'default')) {
    // The user is subscribed to the basic or premium product
}
```

Similarly, to check if a user is subscribed to a specific price, use the
`subscribedToPrice` method:

```php
if ($user->subscribedToPrice('price_basic_monthly', 'default')) {
    // The user is subscribed to the basic monthly price
}
```

To retrieve a user's subscription instance, use the `subscription` method. This
method accepts a subscription type and returns a `Subscription` model instance:

```php
$subscription = $user->subscription('default');
```

> [!WARNING]  
> If a user has two subscriptions with the same type, the most recent
> subscription will always be returned by the `subscription` method. For
> example, a user might have two subscription records with the type of
> `default`; however, one of the subscriptions may be an old, expired
> subscription, while the other is the current, active subscription. The most
> recent subscription will always be returned while older subscriptions are kept
> in the database for historical review.

To retrieve all subscriptions belonging to a user, use the `subscriptions`
relationship:

```php
$subscriptions = $user->subscriptions;
```

#### Methods on the Subscription Model ($subscription)

The Subscription model provides several methods to check its status. The `valid`
method can be used to check if a subscription is active, in trial, or in a grace
period:

```php
if ($subscription->valid()) {
    // The subscription is valid
}
```

To check if a subscription is active, use the `active` method:

```php
if ($subscription->active()) {
    // The subscription is currently active
}
```

To check if a user's subscription is not cancelled and not in a trial, use the
`recurring` method:

```php
if ($subscription->recurring()) {
    // The user has a recurring subscription
}
```

To determine if a subscription has ended and is no longer in its grace period,
use the `ended` method:

```php
if ($subscription->ended()) {
    // The subscription has ended
}
```

To check if a subscription is in a grace period after cancellation, use the
`onGracePeriod` method:

```php
if ($subscription->onGracePeriod()) {
    // The subscription is in a grace period
}
```

To check if a subscription is currently in a trial period, use the `onTrial`
method:

```php
if ($subscription->onTrial()) {
    // The subscription is currently in trial
}
```

To determine if a subscription's trial has expired, use the `hasExpiredTrial`
method:

```php
if ($subscription->hasExpiredTrial()) {
    // The subscription's trial period has ended
}
```

##### Retrieving the Chargebee Subscription Object

If you need to access the raw Chargebee subscription data, use the
`asChargebeeSubscription` method. This method fetches the subscription object
directly from Chargebee:

```php
$chargebeeSubscription = $subscription->asChargebeeSubscription();
```

You can then access Chargebee’s full subscription details:

```php
$status = $chargebeeSubscription->status;
$currentTermEnd = $chargebeeSubscription->currentTermEnd;
```

##### Syncing Subscription Status with Chargebee

To ensure that your application's database reflects the latest status from
Chargebee, you can use the `syncChargebeeStatus` method. This method fetches the
latest subscription status from Chargebee and updates it in your database:

```php
$subscription->syncChargebeeStatus();
```

##### Retrieving the Subscription Owner

You can retrieve the user associated with a subscription using the `user` or
`owner` methods. Both methods return an instance of your billable model,
typically `App\Models\User`:

```php
$user = $subscription->user;
$owner = $subscription->owner;
```

#### Query Scopes

Cashier also provides query scopes to filter subscriptions based on their
status:

```php
$activeSubscriptions = Subscription::active()->get();
$recurringSubscriptions = Subscription::recurring()->get();
$trialSubscriptions = Subscription::onTrial()->get();
$notOnTrialSubscriptions = Subscription::notOnTrial()->get();
$expiredTrialSubscriptions = Subscription::expiredTrial()->get();
$endedSubscriptions = Subscription::ended()->get();
$gracePeriodSubscriptions = Subscription::onGracePeriod()->get();
$gracePeriodSubscriptions = Subscription::notOnGracePeriod()->get();
$canceledSubscriptions = Subscription::canceled()->get();
$notCanceledSubscriptions = Subscription::notCanceled()->get();
```

<a name="subscription-items"></a>

### Subscription Items

A subscription can have one or more items, each representing a specific price
and quantity, stored in your database's `subscription_items` table. You may
access these via the `items` relationship on the subscription:

```php
$subscriptionItems = $subscription->items;
```

If you need to retrieve a specific subscription item by price, use the
`findItemOrFail` method. This method will throw an exception if the requested
price is not found:

```php
$subscriptionItem = $subscription->findItemOrFail('price_chat');
$chargebeePrice = $subscriptionItem->chargebee_price;
$quantity = $subscriptionItem->quantity;
```

If you need to check whether a subscription has multiple prices, use the
`hasMultiplePrices` method:

```php
if ($subscription->hasMultiplePrices()) {
    // This subscription contains multiple items.
}
```

Conversely, if you want to check whether a subscription has a single price, use
`hasSinglePrice`:

```php
if ($subscription->hasSinglePrice()) {
    // This subscription contains only one item.
}
```

To determine whether a subscription contains a specific product, use the
`hasProduct` method. This method accepts a product identifier as its argument:

```php
if ($subscription->hasProduct('prod_chat')) {
    // This subscription includes the chat product.
}
```

Similarly, to check if a subscription contains a specific price, use `hasPrice`:

```php
if ($subscription->hasPrice('price_chat')) {
    // This subscription includes the chat price.
}
```

#### Managing Subscription Items

Each subscription item is linked to a subscription through the `subscription`
relationship:

```php
$subscriptionItem = $subscription->items->first();
$parentSubscription = $subscriptionItem->subscription;
```

To update a subscription item in Chargebee, use the
`updateChargebeeSubscriptionItem` method. This method accepts an array of item
options and an optional array of subscription-wide options:

```php
$subscriptionItem->updateChargebeeSubscriptionItem([
    'quantity' => 5,
], [
    'prorate' => true,
]);
```

If you need to retrieve the subscription item as a Chargebee object, use
`asChargebeeSubscriptionItem`:

```php
$chargebeeSubscriptionItem = $subscriptionItem->asChargebeeSubscriptionItem();
```

<a name="updating-a-subscription-in-chargebee"></a>

### Updating a Subscription in Chargebee

The `updateChargebeeSubscription` method allows you to update a subscription
directly in Chargebee by passing an array of options:

```php
$subscription->updateChargebeeSubscription([
    'metaData' => json_encode(['note' => 'Updated subscription with coupons']),
    'couponIds' => $couponIds,
]);
```

This method provides flexibility when modifying subscription details without
using predefined helper methods. You can find the list of available options
[here](https://apidocs.eu.chargebee.com/docs/api/subscriptions#update_subscription_for_items).

<a name="changing-prices"></a>

### Changing Prices

After a customer is subscribed to your application, they may occasionally want
to change to a new subscription price. The `Subscription` model provides various
methods to manage price changes, including swapping prices, adding prices, and
removing them.

#### Swapping prices

To swap a customer to a new price, use the `swap` method. This will replace the
existing prices with the new ones provided:

```php
$subscription->swap('new_price_id');
$subscription->swap(['price_id_one', 'price_id_two']);
```

If the customer is on trial, the trial period will be maintained. Additionally,
if a "quantity" exists for the subscription, that quantity will also be
maintained.

If you want to cancel the customer's trial period when swapping prices, use
`skipTrial`:

```php
$subscription->skipTrial()->swap('new_price_id');
```

To swap prices and immediately invoice the customer regardless of Chargebee's
global settings, use `swapAndInvoice`:

```php
$subscription->swapAndInvoice('new_price_id');
```

You can also swap an individual subscription item to a new price instead of
swapping the entire subscription. The `swap` and `swapAndInvoice` methods are
available on `SubscriptionItem`:

```php
$subscriptionItem->swap('new_price_id');
$subscriptionItem->swapAndInvoice('new_price_id');
```

#### Adding prices to a subscription

If you need to add an additional price to a subscription without removing the
existing ones, use the `addPrice` method:

```php
$subscription->addPrice('additional_price_id');
$subscription->addPrice(['price_id_one', 'price_id_two']);
```

To bill the customer immediately for the new price regardless of Chargebee's
global settings, use `addPriceAndInvoice`:

```php
$subscription->addPriceAndInvoice('additional_price_id');
```

If adding a price that is metered, use the `addMeteredPrice` method:

```php
$subscription->addMeteredPrice('metered_price_id');
```

To immediately invoice a metered price upon adding it, use
`addMeteredPriceAndInvoice`:

```php
$subscription->addMeteredPriceAndInvoice('metered_price_id');
```

#### Removing Prices from a Subscription

To remove a specific price from a subscription, use the `removePrice` method:

```php
$subscription->removePrice('price_id_to_remove');
```

> [!WARNING]  
> You cannot remove the last price on a subscription. Instead, cancel the
> subscription entirely.

#### Applying Coupons to a Subscription

You can apply a discount coupon to an active subscription using the
`applyCoupon` method. This method accepts either a list of coupon IDs or
[coupon codes](https://apidocs.chargebee.com/docs/api/coupon_codes):

```php
$subscription->applyCoupon('DISCOUNT_10');
```

If you want to apply multiple coupons at once:

```php
$subscription->applyCoupon(['DISCOUNT_10', 'SUMMER_SALE']);
```

#### Proration

By default, Chargebee applies proration based on the settings configured in the
Chargebee dashboard. If you want to override this behavior for a specific
change, you can use the `prorate` method to enable proration or `noProrate` to
disable it:

```php
$subscription->prorate()->swap('new_price_id');
$subscription->noProrate()->removePrice('price_id_to_remove');
```

<a name="subscription-quantity"></a>

### Subscription Quantity

Some subscriptions may depend on a "quantity" factor. For example, a SaaS
platform might charge per user, per project, or per seat.

#### Adjusting Subscription Quantity

To increase or decrease the quantity of a subscription, you can use the
`incrementQuantity`, `incrementAndInvoice`, and `decrementQuantity` methods:

```php
$subscription->incrementQuantity();  // Increase by 1
$subscription->incrementQuantity(5); // Increase by 5

$subscription->incrementAndInvoice(5); // Increase by 5 and invoice immediately

$subscription->decrementQuantity();  // Decrease by 1
$subscription->decrementQuantity(3); // Decrease by 3
```

Alternatively, you may set a specific quantity using `updateQuantity`:

```php
$subscription->updateQuantity(10);
```

For subscriptions with multiple prices, you should specify the price ID when
updating quantity:

```php
$subscription->incrementQuantity(2, 'price_chat');
$subscription->decrementQuantity(1, 'price_chat');
$subscription->updateQuantity(10, 'price_chat');
```

If your subscription contains multiple prices, the `chargebee_price` and
`quantity` attributes on the `Subscription` model will be null. To access
individual price attributes, use the `items` relationship:

```php
foreach ($subscription->items as $item) {
    echo "Price: {$item->chargebee_price}, Quantity: {$item->quantity}";
}
```

#### Adjusting Quantity on Subscription Items

You can also modify the quantity of a specific `SubscriptionItem` directly:

```php
$subscriptionItem->incrementQuantity(2);
$subscriptionItem->incrementAndInvoice(2);
$subscriptionItem->decrementQuantity(1);
$subscriptionItem->updateQuantity(10);
```

#### Proration

By default, Chargebee applies proration based on the settings configured in the
Chargebee dashboard. If you want to override this behavior for a specific
change, you can use the `prorate` method to enable proration or `noProrate` to
disable it:

```php
$subscription->noProrate()->updateQuantity(3);
$subscription->prorate()->decrementQuantity(2);
```

<a name="usage-based-billing"></a>

### Usage Based Billing

Usage-based billing allows you to charge customers based on their actual usage
of a product or service during a billing cycle. This model is commonly used for
services like internet data, API requests, or SMS usage.

In Chargebee, usage-based billing must first be enabled in your Chargebee
settings. You also need to create metered items to track usage correctly. A
subscription can contain both metered and non-metered items. For more details,
refer to the Chargebee documentation:
[Chargebee Usages API](https://apidocs.chargebee.com/docs/api/usages?prod_cat_ver=2).

To subscribe a customer to a metered price, use the `meteredPrice` method when
creating a subscription:

```php
$subscription = $user->newSubscription('default')
    ->meteredPrice('price_metered')
    ->create($paymentMethod);
```

#### Reporting Usage

To report customer usage of a metered product, use the `reportUsage` method on a
subscription. This method allows Chargebee to track usage and bill customers
accordingly. By default, the method increments usage by 1, but you can specify a
different quantity if needed. If subscription has multiple prices, specify also
the price ID:

```php
$subscription->reportUsage(2, now(), 'price_metered');
```

Alternatively, you can use `reportUsageFor` to report usage for a specific price
directly:

```php
$subscription->reportUsageFor('price_metered', 10, now());
```

The parameters for these methods are:

- quantity (optional, default: 1) – The number of units to report.
- timestamp (optional, default: current time) – The time at which the usage
  occurred.
- price (required for `reportUsageFor` or for subscriptions with multiple
  prices) – The price ID of the metered product.

#### Retrieving Usage Records

To retrieve all recorded usage for a metered product, use the `usageRecords`
method. If the subscription has multiple metered prices, specify the price ID:

```php
$usageRecords = $subscription->usageRecords(['limit' => 10], 'price_metered');
```

You may also retrieve usage records for a specific price using
`usageRecordsFor`:

```php
$usageRecords = $subscription->usageRecordsFor('price_metered');
```

The parameters for these methods are:

- options (optional) – An array of filters for retrieving usage records.
- price (required for `reportUsageFor` or for subscriptions with multiple
  prices) – The price ID for which usage records should be retrieved.

#### Managing Usage on Subscription Items

The `reportUsage` and `usageRecords` methods are also available on
`SubscriptionItem`, allowing you to track usage for specific items within a
subscription:

```php
$subscriptionItem->reportUsage(int $quantity = 1, DateTimeInterface|int|null $timestamp = null);
$subscriptionItem->usageRecords(array $options = []): Collection;
```

For example:

```php
$subscriptionItem->reportUsage(5);
$usageRecords = $subscriptionItem->usageRecords();
```

<a name="cancelling-subscriptions"></a>

### Cancelling Subscriptions

To cancel a subscription, call the `cancel` method on the user's subscription.
This schedules the subscription for cancellation at the end of the current
billing period, allowing the customer to continue using the service until then:

```php
$user->subscription('default')->cancel();
```

When a subscription is canceled, Cashier updates the `ends_at` column in the
`subscriptions` database table. This column determines when the `subscribed`
method should begin returning `false`. For example, if a subscription is
canceled on March 1st but is set to end on March 5th, `subscribed` will continue
to return `true` until March 5th.

To check if a user is still within their grace period after canceling a
subscription, use `onGracePeriod`:

```php
if ($user->subscription('default')->onGracePeriod()) {
    // The user is still in their grace period.
}
```

If you want to cancel a subscription immediately, without allowing the customer
to continue using the service until the end of the billing period, use
`cancelNow`:

```php
$user->subscription('default')->cancelNow();
```

To cancel the subscription immediately and also generate an invoice for any
pending metered usage or proration adjustments, use `cancelNowAndInvoice`:

```php
$user->subscription('default')->cancelNowAndInvoice();
```

If you need to schedule a subscription to be canceled at a specific date in the
future, use `cancelAt`:

```php
$user->subscription('default')->cancelAt(now()->addDays(10));
```

Finally, you should always cancel user subscriptions before deleting the
associated user model:

```php
$user->subscription('default')->cancelNow();
$user->delete();
```

<a name="resuming-subscriptions"></a>

### Resuming subscriptions

If a subscription has been paused, you can resume it using the `resume` method.
This will reactivate the subscription and allow the customer to continue using
the service:

```php
$user->subscription('default')->resume();
```

Before resuming a subscription, you may want to check whether it is currently
paused using the `paused` method:

```php
if ($user->subscription('default')->paused()) {
    // The subscription is currently paused.
    $user->subscription('default')->resume();
}
```

<a name="multiple-subscriptions"></a>

### Multiple Subscriptions

Chargebee allows your customers to have multiple subscriptions simultaneously.
For example, you may run a gym that offers a swimming subscription and a
weight-lifting subscription, and each subscription may have different pricing.
Of course, customers should be able to subscribe to either or both plans.

When your application creates subscriptions, you may provide the type of the
subscription to the `newSubscription` method. The type may be any string that
represents the type of subscription the user is initiating:

```php
use Illuminate\Http\Request;

Route::post('/swimming/subscribe', function (Request $request) {
    $request->user()->newSubscription('swimming')
        ->price('price_swimming_monthly')
        ->create($request->paymentMethodId);

    // ...
});
```

In this example, we initiated a monthly swimming subscription for the customer.
However, they may want to swap to a yearly subscription at a later time. When
adjusting the customer's subscription, we can simply swap the price on the
`swimming` subscription:

```php
$user->subscription('swimming')->swap('price_swimming_yearly');
```

Of course, you may also cancel the subscription entirely:

```php
$user->subscription('swimming')->cancel();
```

<a name="subscription-trials"></a>

## Subscription Trials

<a name="with-payment-method-up-front"></a>

### With Payment Method Up Front

If you want to offer trial periods while still collecting payment method
information upfront, use the `trialDays` method when creating a subscription:

```php
$user->newSubscription('default', 'price_monthly')
    ->trialDays(10)
    ->create($paymentMethod);
```

This method sets the trial period end date in the database and instructs
Chargebee to delay billing until after this period. If you need to specify a
precise end date instead of a number of days, use `trialUntil`:

```php
use Carbon\Carbon;

$user->newSubscription('default', 'price_monthly')
    ->trialUntil(Carbon::now()->addDays(10))
    ->create($paymentMethod);
```

<a name="generic-trials-without-payment-method-up-front"></a>

### Generic Trials (Without Payment Method Up Front)

If you want to offer trial periods without requiring payment information
upfront, set the `trial_ends_at` column when creating a user:

```php
$user = User::create([
    // ...
    'trial_ends_at' => now()->addDays(10),
]);
```

Cashier refers to this as a "generic trial", since it's not attached to an
actual subscription. You can check if a user is in a generic trial using:

```php
if ($user->onGenericTrial()) {
    // User is on a generic trial.
}
```

When the user is ready to subscribe, proceed as usual:

```php
$user->newSubscription('default', 'price_monthly')->create($paymentMethod);
```

To get the trial end date, use `trialEndsAt`:

```php
$trialEndsAt = $user->trialEndsAt('default');
```

<a name="checking-and-ending-trials"></a>

### Checking and Ending Trials

To check if a user is currently on a trial, use the `onTrial` method on either
the user or subscription model:

```php
if ($user->onTrial('default')) {
    // User is on a trial.
}

if ($user->subscription('default')->onTrial()) {
    // User's subscription is on a trial.
}
```

To immediately end a trial period, call `endTrial`:

```php
$user->subscription('default')->endTrial();
```

To check if a trial has expired, use `hasExpiredTrial`:

```php
if ($user->hasExpiredTrial('default')) {
    // The user's trial has ended.
}

if ($user->subscription('default')->hasExpiredTrial()) {
    // The subscription trial has ended.
}
```

<a name="extending-a-trial"></a>

### Extending a Trial

You can extend a subscription's trial period using `extendTrial`:

```php
$subscription = $user->subscription('default');

$subscription->extendTrial(now()->addDays(7)); // Extend trial by 7 days.
$subscription->extendTrial($subscription->trial_ends_at->addDays(5)); // Add 5 extra days.
```

> [!WARNING] The `extendTrial` method will throw an exception if the
> subscription is already active. Ensure that the status is `future`,
> `in_trial`, or `cancelled` before calling this method.

<a name="single-charges"></a>

## Single Charges

<a name="creating-payment-intents"></a>

### Creating Payment Intents

You can create a new Chargebee payment intent by invoking `pay` or
`createPayment` methods on a billable model instance. These methods initialize a
Chargebee `PaymentIntent` with a given amount and optional parameters. For
example, you may create a payment intent for 50 euros (note that you should use
the lowest denomination of your currency, such as euro cents for euros):

```php
$payment = $user->pay(5000, [
    'currencyCode' => 'EUR',
]);
```

For more details on the available options, refer to the
[Chargebee Payment Intent API documentation](https://apidocs.chargebee.com/docs/api/payment_intents#create_a_payment_intent).

The resulting payment intent is wrapped in a `Chargebee\Cashier\Payment`
instance. It provides methods for retrieving related customer information and
interacting with the Chargebee payment object.

The `customer` method fetches the associated Billable model if one exists:

```php
$payment = $user->createPayment(1000);
$customer = $payment->customer();
```

The `asChargebeePaymentIntent` method returns the underlying `PaymentIntent`
instance:

```php
$paymentIntent = $payment->asChargebeePaymentIntent();
```

The `amount` and `rawAmount` methods provide details about the total amount
associated with a payment intent. The `amount` method returns the total payment
amount in a formatted string, considering the currency of the payment:

```php
$formattedAmount = $payment->amount();
```

The `rawAmount` method returns the raw numeric value of the payment amount:

```php
$rawAmount = $payment->rawAmount();
```

The `Payment` class also implements Laravel's `Arrayable`, `Jsonable`, and
`JsonSerializable` interfaces.

<a name="payment-status-helpers"></a>

### Payment Status Helpers

The `Payment` instance provides a set of helper methods to determine the current
status of a Chargebee `PaymentIntent`. The `requiresAction` method determines
whether the payment requires additional steps, such as 3D Secure authentication:

```php
if ($payment->requiresAction()) {
    // Prompt the user to complete additional authentication (e.g., 3D Secure)
}
```

The `requiresCapture` method checks whether the payment has been successfully
authorized but still requires capture. This is useful for workflows where
payment authorization and finalization happen in separate steps:

```php
if ($payment->requiresCapture()) {
    // Proceed with capturing the payment
}
```

The `isCanceled` method determines whether the `PaymentIntent` has expired due
to inaction:

```php
if ($payment->isCanceled()) {
    // Handle expired or canceled payment scenario
}
```

The `isSucceeded` method returns true if the payment has been finalized and the
intent is marked as `consumed`:

```php
if ($payment->isSucceeded()) {
    // Proceed with post-payment actions
}
```

The `isProcessing` method determines if the payment is still in progress,
meaning that additional authentication or user interaction may be required:

```php
if ($payment->isProcessing()) {
    // Wait for the payment to complete
}
```

You can use the `validate` method to check if additional user action, such as 3D
Secure authentication, is needed. If so, an `IncompletePayment` exception is
thrown:

```php
try {
    $payment->validate();
    // Proceed with payment handling
} catch (\Chargebee\Cashier\Exceptions\IncompletePayment $exception) {
    // Handle cases where additional action is required (e.g., 3D Secure)
}
```

<a name="finding-payment-intents"></a>

### Finding Payment Intents

The `findPayment` method allows you to retrieve an existing Chargebee
`PaymentIntent` by its ID. This is useful when you need to check the status of a
previously created payment or retrieve details for further processing.

To find a payment intent, call the `findPayment` method with the payment ID:

```php
$payment = $user->findPayment('id_123456789');
```

If the payment intent exists, it is returned as a `Chargebee\Cashier\Payment`
instance. If the payment intent is not found, a `PaymentNotFound` exception is
thrown.

<a name="charge-with-invoice"></a>

### Charge With Invoice

Sometimes you may need to make a one-time charge and offer a PDF invoice to your
customer. The `invoicePrice` method lets you do just that. For example, let's
invoice a customer for five new shirts:

    $user->invoicePrice('price_tshirt', 5);

The invoice will be immediately charged against the user's default payment
method. The `invoicePrice` method also accepts an array as its third argument.
This array contains the billing options for the invoice item. The fourth
argument accepted by the method is also an array which should contain the
billing options for the invoice itself:

    $user->invoicePrice('price_tshirt', 5, [
    ], [
        'couponIds' => ['SUMMER21SALE']
    ]);

To create a one-time charge for multiple items, you may use the `newInvoice`
method and then adding them to the customer's "tab" and then invoicing the
customer. For example, we may invoice a customer for five shirts and two mugs:

    $user->newInvoice()
        ->tabPrice('price_tshirt', 5);
        ->tabPrice('price_mug', 2);
        ->invoice();

Alternatively, you may use the `tabFor` method to make a "one-off" charge
against the customer's default payment method:

    $user->newInvoice()
        ->tabFor('One Time Fee', 500)
        ->invoice();

<a name="invoices"></a>

## Invoices

<a name="retrieving-invoices"></a>

### Retrieving Invoices

You may easily retrieve an array of a billable model's invoices using the
`invoices` method. The `invoices` method returns a collection of
`Chargebee\Cashier\Invoice` instances:

    $invoices = $user->invoices();

If you would like to include pending invoices in the results, you may use the
`invoicesIncludingPending` method:

    $invoices = $user->invoicesIncludingPending();

You may use the `findInvoice` method to retrieve a specific invoice by its ID:

    $invoice = $user->findInvoice($invoiceId);

<a name="displaying-invoice-information"></a>

#### Displaying Invoice Information

When listing the invoices for the customer, you may use the invoice's methods to
display the relevant invoice information. For example, you may wish to list
every invoice in a table, allowing the user to easily download any of them:

    <table>
        @foreach ($invoices as $invoice)
            <tr>
                <td>{{ $invoice->date()->toFormattedDateString() }}</td>
                <td>{{ $invoice->total() }}</td>
                <td><a href="/user/invoice/{{ $invoice->id }}">Download</a></td>
            </tr>
        @endforeach
    </table>

<a name="upcoming-invoices"></a>

### Upcoming Invoices

To retrieve the upcoming invoice for a customer, you may use the
`upcomingInvoice` method:

    $invoice = $user->upcomingInvoice();

Similarly, if the customer has multiple subscriptions, you can also retrieve the
upcoming invoice for a specific subscription:

    $invoice = $user->subscription('default')->upcomingInvoice();

<a name="previewing-subscription-invoices"></a>

### Previewing Subscription Invoices

Using the `previewInvoice` method, you can preview an invoice before making
price changes. This will allow you to determine what your customer's invoice
will look like when a given price change is made:

    $invoice = $user->subscription('default')->previewInvoice('price_yearly');

You may pass an array of prices to the `previewInvoice` method in order to
preview invoices with multiple new prices:

    $invoice = $user->subscription('default')->previewInvoice(['price_yearly', 'price_metered']);

<a name="generating-invoice-pdfs"></a>

### Generating Invoice PDFs

Before generating invoice PDFs, you should use Composer to install the Dompdf
library, which is the default invoice renderer for Cashier:

```php
composer require dompdf/dompdf
```

From within a route or controller, you may use the `downloadInvoice` method to
generate a PDF download of a given invoice. This method will automatically
generate the proper HTTP response needed to download the invoice:

    use Illuminate\Http\Request;

    Route::get('/user/invoice/{invoice}', function (Request $request, string $invoiceId) {
        return $request->user()->downloadInvoice($invoiceId);
    });

By default, all data on the invoice is derived from the customer and invoice
data stored in Chargebee. The filename is based on your `app.name` config value.
However, you can customize some of this data by providing an array as the second
argument to the `downloadInvoice` method. This array allows you to customize
information such as your company and product details:

    return $request->user()->downloadInvoice($invoiceId, [
        'vendor' => 'Your Company',
        'product' => 'Your Product',
        'street' => 'Main Str. 1',
        'location' => '2000 Antwerp, Belgium',
        'phone' => '+32 499 00 00 00',
        'email' => 'info@example.com',
        'url' => 'https://example.com',
        'vendorVat' => 'BE123456789',
    ]);

The `downloadInvoice` method also allows for a custom filename via its third
argument. This filename will automatically be suffixed with `.pdf`:

    return $request->user()->downloadInvoice($invoiceId, [], 'my-invoice');

<a name="custom-invoice-render"></a>

#### Custom Invoice Renderer

Cashier also makes it possible to use a custom invoice renderer. By default,
Cashier uses the `DompdfInvoiceRenderer` implementation, which utilizes the
[dompdf](https://github.com/dompdf/dompdf) PHP library to generate Cashier's
invoices. However, you may use any renderer you wish by implementing the
`Chargebee\Cashier\Contracts\InvoiceRenderer` interface. For example, you may
wish to render an invoice PDF using an API call to a third-party PDF rendering
service:

    use Illuminate\Support\Facades\Http;
    use Chargebee\Cashier\Contracts\InvoiceRenderer;
    use Chargebee\Cashier\Invoice;

    class ApiInvoiceRenderer implements InvoiceRenderer
    {
        /**
         * Render the given invoice and return the raw PDF bytes.
         */
        public function render(Invoice $invoice, array $data = [], array $options = []): string
        {
            $html = $invoice->view($data)->render();

            return Http::get('https://example.com/html-to-pdf', ['html' => $html])->get()->body();
        }
    }

Once you have implemented the invoice renderer contract, you should update the
`cashier.invoices.renderer` configuration value in your application's
`config/cashier.php` configuration file. This configuration value should be set
to the class name of your custom renderer implementation.
