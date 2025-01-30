<p align="center"><img width="355" height="62" src="/art/logo.svg" alt="Logo Laravel Cashier Chargebee"></p>

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
    - [Billing Portal](#billing-portal)
- [Handling Chargebee Webhooks](#handling-chargebee-webhooks)
    - [Configuring Webhooks in Chargebee](#configuring-webhooks-in-chargebee)
    - [Route Configuration](#route-configuration)
    - [Configuring Basic Authentication](#configuring-basic-authentication)
    - [Handling Webhook Events](#handling-webhook-events)
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

<a name="installation"></a>
## Installation

First, install the Cashier package for Chargebee using the Composer package manager:

```shell
composer require laravel/cashier-chargebee
```

After installing the package, publish Cashier's migrations using the `vendor:publish` Artisan command:

```shell
php artisan vendor:publish --tag="cashier-migrations"
```

Then, migrate your database:

```shell
php artisan migrate
```

Cashier's migrations will add several columns to your `users` table. They will also create a new `subscriptions` table to hold all of your customer's subscriptions and a `subscription_items` table for subscriptions with multiple prices.

If you wish, you can also publish Cashier's configuration file using the `vendor:publish` Artisan command:

```shell
php artisan vendor:publish --tag="cashier-config"
```

<a name="configuration"></a>
## Configuration

<a name="billable-model"></a>
### Billable Model

Before using Cashier, add the `Billable` trait to your billable model definition. Typically, this will be the `App\Models\User` model. This trait provides various methods to allow you to perform common billing tasks, such as creating subscriptions, applying coupons, and updating payment method information:

```php
use Laravel\CashierChargebee\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

Cashier assumes your billable model will be the `App\Models\User` class that ships with Laravel. If you wish to change this you may specify a different model via the `useCustomerModel` method. This method should typically be called in the `boot` method of your `AppServiceProvider` class:

```php
use App\Models\Cashier\User;
use Laravel\CashierChargebee\Cashier;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Cashier::useCustomerModel(User::class);
}
```

> [!WARNING]  
> If you're using a model other than Laravel's supplied `App\Models\User` model, you'll need to publish and alter the [Cashier migrations](#installation) provided to match your alternative model's table name.

<a name="chargebee-api"></a>
### Chargebee API

Next, you should configure your Chargebee API key and domain name in your application's `.env` file:

```ini
CHARGEBEE_SITE=your-chargebee-site
CHARGEBEE_API_KEY=your-api-key
```

- `CHARGEBEE_SITE`: This is the unique name of your Chargebee instance. It is used to construct the base URL for API requests. The base URL for API calls will look like this: 

    ```
        https://<CHARGEBEE_SITE>.chargebee.com/api/v2
    ```

    You can read more about configuring the domain name in the [Chargebee documentation](https://www.chargebee.com/docs/2.0/sites-intro.html#configuring-domain-name).

- `CHARGEBEE_API_KEY`: This is the API key that authenticates your API requests to Chargebee. You can learn how to generate Chargebee API keys [here](https://www.chargebee.com/docs/api_keys.html).

<a name="currency-configuration"></a>
### Currency Configuration

The default Cashier currency is United States Dollars (USD). You can change the default currency by setting the `CASHIER_CURRENCY` environment variable within your application's `.env` file:

```ini
CASHIER_CURRENCY=eur
```

In addition to configuring Cashier's currency, you may also specify a locale to be used when formatting money values for display on invoices. Internally, Cashier utilizes [PHP's `NumberFormatter` class](https://www.php.net/manual/en/class.numberformatter.php) to set the currency locale:

```ini
CASHIER_CURRENCY_LOCALE=nl_BE
```

> [!WARNING]  
> In order to use locales other than `en`, ensure the `ext-intl` PHP extension is installed and configured on your server.

<a name="using-custom-models"></a>
### Using Custom Models

You are free to extend the models used internally by Cashier by defining your own model and extending the corresponding Cashier model:

```php
use Laravel\CashierChargebee\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    // ...
}
```

After defining your model, you may instruct Cashier to use your custom model via the `Laravel\CashierChargebee\Cashier` class. Typically, you should inform Cashier about your custom models in the `boot` method of your application's `App\Providers\AppServiceProvider` class:

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

You can retrieve a customer by their Chargebee ID using the `Cashier::findBillable` method. This method will return an instance of the billable model:

```php
use Laravel\CashierChargebee\Cashier;

$user = Cashier::findBillable($chargebeeId);
```

You may use the `asChargebeeCustomer` method if you want to return the Chargebee customer object for a billable model:

```php
$chargebeeCustomer = $user->asChargebeeCustomer();
```

If `chargebee_id` on your model is missing or invalid, the method will throw a `CustomerNotFound` exception.

<a name="creating-customers"></a>
### Creating Customers

Occasionally, you may wish to create a Chargebee customer without beginning a subscription. You may accomplish this using the `createAsChargebeeCustomer` method:

```php
$chargebeeCustomer = $user->createAsChargebeeCustomer();
```

Once the customer has been created in Chargebee, you may begin a subscription at a later date.

This method uses helper methods like `chargebeeFirstName`, `chargebeeLastName`, `chargebeeEmail`, `chargebeePhone`, `chargebeeBillingAddress`, `chargebeeLocale`, and `chargebeeMetaData` to populate default values for the customer. You can override these methods in your model to customize which fields are used. For example:

```php
/**
 * Get the default first name.
 */
public function chargebeeFirstName(): string|null
{
    return $this->custom_first_name;
}
```

You may provide an optional `$options` array to pass in any additional [customer creation parameters that are supported by the Chargebee API](https://apidocs.eu.chargebee.com/docs/api/customers#create_a_customer):

```php
$chargebeeCustomer = $user->createAsChargebeeCustomer($options);
```

If you attempt to create a Chargebee customer for a model that already has a `chargebee_id` (indicating that the customer already exists in Chargebee), the method will throw a `CustomerAlreadyCreated` exception.

You can also use the `createOrGetChargebeeCustomer` method to retrieve an existing Chargebee customer or create a new one if it does not exist:

```php
$chargebeeCustomer = $user->createOrGetChargebeeCustomer($options);
```

<a name="updating-customers"></a>
### Updating Customers

Occasionally, you may wish to update the Chargebee customer directly with additional information. You may accomplish this using the `updateChargebeeCustomer` method. This method accepts an array of [customer](https://apidocs.chargebee.com/docs/api/customers?lang=php#update_a_customer) and [billing information](https://apidocs.chargebee.com/docs/api/customers#update_billing_info_for_a_customer) update parameters supported by the Chargebee API:

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

> [!NOTE]
> The `billingAddress` key is required for the `updateChargebeeCustomer` method and it must contain a non-empty array of address details (e.g., `line1`, `city`, `zip`, etc.). You can provide it directly in the `options` input array or override the `chargebeeBillingAddress()` method in your model to provide default values. For example:

```php
/**
 * Provide a default billing address.
 */
public function chargebeeBillingAddress(): array
{
    return [
        'line1' => $this->address_line_1,
        'city' => $this->address_city,
        'state' => $this->address_state,
        'zip' => $this->address_zip,
        'country' => $this->address_country,
    ];
}
```

If `chargebee_id` on your model is missing or invalid, the method will throw a `CustomerNotFound` exception.

You can also use the `updateOrCreateChargebeeCustomer` method to update an existing Chargebee customer or create a new one if it does not exist:

```php
$chargebeeCustomer = $user->updateOrCreateChargebeeCustomer($options);
```

<a name="syncing-customers"></a>
### Syncing Customers

To sync the customer's information to Chargebee, you can use the `syncChargebeeCustomerDetails` method. This method will update the Chargebee customer with the latest information from your model:

```php
$customer = $user->syncChargebeeCustomerDetails();
```

This method uses helper methods like `chargebeeFirstName`, `chargebeeLastName`, `chargebeeEmail`, `chargebeePhone`, `chargebeeBillingAddress`, `chargebeeLocale`, and `chargebeeMetaData` to determine the values to sync. You can override these methods in your model to customize the sync process.

If you want to sync the customer's information or create a new Chargebee customer if one does not exist, you can use the `syncOrCreateChargebeeCustomer` method:

```php
$customer = $user->syncOrCreateChargebeeCustomer($options);
```

<a name="billing-portal"></a>
### Billing Portal

Chargebee offers [an easy way to set up a billing portal](https://www.chargebee.com/docs/2.0/self-serve-portal.html) so that your customer can manage their subscription, payment methods, and view their billing history. You can redirect your users to the billing portal by invoking the `redirectToBillingPortal` method on the billable model from a controller or route:

```php
use Illuminate\Http\Request;

Route::get('/billing-portal', function (Request $request) {
    return $request->user()->redirectToBillingPortal();
});
```

By default, when the user is finished managing their subscription, they will return to the `home` route of your application upon logout from the portal UI. You may provide a custom URL that the user should return to by passing the URL as an argument to the `redirectToBillingPortal` method:

```php
use Illuminate\Http\Request;

Route::get('/billing-portal', function (Request $request) {
    return $request->user()->redirectToBillingPortal(route('billing'));
});
```

If you would like to generate the URL to the billing portal without generating an HTTP redirect response, you may invoke the `billingPortalUrl` method:

```php
$url = $request->user()->billingPortalUrl(route('billing'));
```

<a name="handling-chargebee-webhooks"></a>
## Handling Chargebee Webhooks

Any change that happens in Chargebee is captured as an event. Webhook configuration allows Chargebee to send event notifications to your system.

<a name="configuring-webhooks-in-chargebee"></a>
### Configuring Webhooks in Chargebee

To configure webhooks in Chargebee, follow the [Chargebee webhook documentation](https://www.chargebee.com/docs/2.0/webhook_settings.html). You should set up your webhook URL to point to your application's webhook endpoint, typically:

```
https://your-application.com/chargebee/webhook
```

<a name="route-configuration"></a>
### Route Configuration

Webhook route is registered automatically when Cashier is loaded. The `chargebee` prefix is derived from the `CASHIER_PATH` configuration variable. If you want to customize this prefix, you can update the `CASHIER_PATH` variable in your `.env` file:

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

Cashier will automatically verify these credentials for incoming webhook requests.

<a name="handling-webhook-events"></a>
### Handling Webhook Events

Cashier emits a `WebhookReceived` event for every incoming webhook, allowing you to handle these events in your application. To handle webhook events, you can create a dedicated event listener class:

```php
namespace App\Listeners;

use Laravel\CashierChargebee\Events\WebhookReceived;

class HandleWebhookReceived
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;

        // Handle the webhook payload
    }
}
```

Here's the rephrased version while maintaining the Markdown format:

---

<a name="manage-payment-methods"></a>
## Managing Payment Methods

**Ensure that 3D Secure (3DS) is enabled in your Chargebee account settings to utilize PaymentIntents.**

<a name="payment-methods-create-setupintent"></a>
### Creating a SetupIntent

The `createSetupIntent` method generates a new `PaymentIntent` with an amount of `0`. This is primarily used to set up payment methods without processing an immediate charge.

#### Method Signature:
```php
public function createSetupIntent(array $options = []): ?PaymentIntent
```

#### Parameters:
- `$options` (*array*, optional) – An associative array of additional parameters for the PaymentIntent.

#### Default Behavior:
- Ensures the customer exists before proceeding.
- The `customer_id` is automatically assigned based on the Chargebee ID of the user.
- The `amount` is fixed at `0`.
- The `currency_code` is determined from the provided `$options` array or falls back to the default configured in `config('cashier.currency')`.

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
- If the `chargebee_id` is missing or invalid, a `CustomerNotFound` exception will be thrown.
- If the request to Chargebee is invalid, an `InvalidRequestException` may be thrown.

<a name="payment-methods-find-setupintent"></a>
### Retrieving a SetupIntent

The `findSetupIntent` method retrieves an existing `PaymentIntent` from Chargebee using its unique identifier.

#### Method Signature:
```php
public function findSetupIntent(string $id): ?PaymentIntent
```

#### Parameters:
- `$id` (*string*) – The unique identifier of the PaymentIntent in Chargebee.

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
- If the provided PaymentIntent ID is invalid or does not exist, Chargebee may throw an `InvalidRequestException`.

For more details, refer to the [Chargebee PaymentIntent API documentation](https://apidocs.eu.chargebee.com/docs/api/payment_intents#create_a_payment_intent?target=_blank).

<a name="payment-methods-has"></a>
### Checking for Available Payment Methods

The `hasPaymentMethod` method checks whether a customer has at least one saved payment method. Optionally, you can specify a payment method type to check for a specific kind.

#### Method Signature:
```php
public function hasPaymentMethod(?string $type = null): bool
```

#### Parameters:
- `$type` (*string*, optional) – Specifies the type of payment method to check (e.g., `'card'`). If `null`, it checks for any payment method.

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
- If the customer does not exist in Chargebee, it may result in an exception when calling `paymentMethods()`.

<a name="payment-methods-list"></a>
### Retrieving a Customer's Payment Methods

The `paymentMethods` method returns a collection of the customer's saved payment methods. Optionally, you can filter the results by payment method type.

#### Method Signature:
```php
public function paymentMethods(?string $type = null, array $parameters = []): ?Collection
```

#### Parameters:
- `$type` (*string*, optional) – Specifies the type of payment methods to retrieve (e.g., `'card'`). If `null`, all available payment methods are returned.
- `$parameters` (*array*, optional) – Additional filters for retrieving payment sources (e.g., custom limits or pagination options).

#### Default Behavior:
- If the customer does not have a Chargebee ID, an empty `Collection` is returned.
- The default limit for retrieved payment sources is set to `24`.
- The method queries Chargebee for payment methods matching the provided parameters.

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
- Retrieves payment methods from Chargebee, filtering by customer ID and optionally by type.
- Returns a Laravel `Collection` of `PaymentSource` objects.
- Uses array filtering to remove null parameters before sending the request.

#### Error Handling:
- If the customer does not exist in Chargebee, the method returns an empty `Collection`.
- If the Chargebee API request fails or encounters an issue, an exception may be thrown.

<a name="payment-methods-add"></a>
### Adding a Payment Method

The `addPaymentMethod` method associates a new payment method with a customer in Chargebee. Optionally, the method can also set the newly added payment method as the default.

#### Method Signature:
```php
public function addPaymentMethod(PaymentSource $paymentSource, bool $setAsDefault = false): PaymentMethod
```

#### Parameters:
- `$paymentSource` (*PaymentSource*) – The payment method to be added to the customer's account.
- `$setAsDefault` (*bool*, optional) – If set to `true`, the newly added payment method will be assigned as the default. Default value: `false`.

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
- If `$setAsDefault` is `true`, the method calls `setDefaultPaymentMethod` to assign the newly added payment method as the default.
- Returns a `PaymentMethod` instance linked to the added `PaymentSource`.

#### Error Handling:
- If the customer does not exist in Chargebee, a `CustomerNotFound` exception will be thrown.
- If the provided payment method is invalid, an `InvalidPaymentMethod` exception will be thrown.
- If the request to Chargebee fails due to invalid parameters, an `InvalidRequestException` will be thrown.

<a name="payment-methods-find"></a>
### Retrieving a Specific Payment Method

To find a specific payment method for a customer, use the `findPaymentMethod` method. This method accepts either a Chargebee `$chargeBeePaymentSource` instance or a payment source ID as a string.

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

- If the chargebee_id is missing or invalid, a CustomerNotFound exception will be thrown.
- If the specified payment method is not found, a PaymentMethodNotFound exception will be thrown.
- If the request payload sent to Chargebee is invalid, an InvalidRequestException will be thrown.

<a name="payment-methods-default"></a>
### Retrieving the Default Payment Method

The `defaultPaymentMethod` method returns the primary payment method associated with the customer. If no default payment method is set, the method returns `null`.

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
- If the `chargebee_id` is missing or invalid, a `CustomerNotFound` exception will be thrown.
- If the request to Chargebee is invalid, an `InvalidRequestException` will be thrown.
- If an invalid payment method is encountered, an `InvalidPaymentMethod` exception will be thrown.

<a name="payment-methods-set-default"></a>
### Setting the Default Payment Method

The `setDefaultPaymentMethod` method allows you to designate a specific payment method as the primary payment source for a customer in Chargebee.

#### Method Signature:
```php
public function setDefaultPaymentMethod(PaymentSource|string $paymentSource): ?Customer
```

#### Parameters:
- `$paymentSource` (*PaymentSource|string*) – The payment method to be set as the default. This can be either a `PaymentSource` instance or a payment source ID as a string.

#### Example Usage:
```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentSourceId = 'pm_123456789';

try {
    $user->setDefaultPaymentMethod($paymentSourceId);
    echo 'Default payment method updated successfully.';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

#### Behavior:
- The method first verifies that the customer exists in Chargebee.
- It resolves the payment method using the provided `PaymentSource` instance or payment source ID.
- If a valid payment source is found, it assigns the `PRIMARY` role to the specified payment source.
- The payment method details are updated and saved for the customer.

#### Error Handling:
- If an invalid payment method is provided, an `InvalidPaymentMethod` exception is thrown.
- If the request to Chargebee is invalid, an `InvalidRequestException` is thrown.
- If the `chargebee_id` is missing or invalid, a `CustomerNotFound` exception is thrown.



<a name="payment-methods-sync-default"></a>
### Synchronizing the Default Payment Method from Chargebee

The `updateDefaultPaymentMethodFromChargebee` method updates the customer's default payment method in the local database by fetching the latest details from Chargebee.

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
- Retrieves the customer's default payment method using the `defaultPaymentMethod` method.
- If a valid `PaymentMethod` is found, updates the stored payment method details in the database.
- If no default payment method exists, resets the stored payment method details (`pm_type` and `pm_last_four`) to `null`.

#### Error Handling:
- If the customer does not exist in Chargebee, a `CustomerNotFound` exception will be thrown.
- If the retrieved payment method is invalid, an `InvalidPaymentMethod` exception will be thrown.
- If the request to Chargebee fails due to invalid parameters, an `InvalidRequestException` will be thrown.

<a name="payment-methods-delete"></a>
### Deleting a Payment Method

The `deletePaymentMethod` method removes a specified payment method from the customer's Chargebee account.

#### Method Signature:
```php
public function deletePaymentMethod(PaymentSource $paymentSource): void
```

#### Parameters:
- `$paymentSource` (*PaymentSource*) – The payment method to be deleted.

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
- Validates that the provided `PaymentSource` belongs to the authenticated customer.
- If the payment method being deleted is the customer's default payment method, it clears the stored payment method details.
- Calls `PaymentSource::delete($paymentSource->id)` to remove the payment method from Chargebee.

#### Error Handling:
- If the customer does not exist in Chargebee, a `CustomerNotFound` exception will be thrown.
- If the provided payment method does not belong to the customer, an `InvalidPaymentMethod` exception will be thrown.
- If the request to Chargebee fails due to invalid parameters, an `InvalidRequestException` will be thrown.


<a name="payment-methods-delete-multiple"></a>
### Deleting Payment Methods of a Specific Type

The `deletePaymentMethods` method removes all payment methods of a specified type for the customer.

#### Method Signature:
```php
public function deletePaymentMethods(string $type): void
```

#### Parameters:
- `$type` (*string*) – The type of payment methods to be deleted (e.g., `'card'`).

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
- Retrieves all payment methods of the specified type using the `paymentMethods($type)` method.
- Iterates through the retrieved payment methods and deletes each one using the `deletePaymentMethod` method.

#### Error Handling:
- If the customer does not exist in Chargebee, a `CustomerNotFound` exception will be thrown.
- If the request to Chargebee fails due to invalid parameters, an `InvalidRequestException` will be thrown.


