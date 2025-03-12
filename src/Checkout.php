<?php

namespace Chargebee\CashierChargebee;

use ChargeBee\ChargeBee\Models\HostedPage;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use JsonSerializable;

class Checkout implements Arrayable, Jsonable, JsonSerializable, Responsable
{
    /**
     * The Chargebee model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    protected $owner;

    /**
     * The Chargebee checkout session instance.
     *
     * @var Session
     */
    protected $session;

    /**
     * Create a new checkout session instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  Session  $session
     * @return void
     */
    public function __construct($owner, Session $session)
    {
        $this->owner = $owner;
        $this->session = $session;
    }

    /**
     * Begin a new guest checkout session.
     *
     * @return \Chargebee\CashierChargebee\CheckoutBuilder
     */
    public static function guest()
    {
        return new CheckoutBuilder();
    }

    /**
     * Begin a new customer checkout session.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  object|null  $parentInstance
     * @return \Chargebee\CashierChargebee\CheckoutBuilder
     */
    public static function customer($owner, $parentInstance = null): CheckoutBuilder
    {
        return new CheckoutBuilder($owner, $parentInstance);
    }

    /**
     * Begin a new checkout session.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Chargebee\CashierChargebee\Checkout
     */
    public static function create($owner, array $sessionOptions = [], array $customerOptions = []): Checkout
    {
        $data = array_merge([
            'mode' => Session::MODE_PAYMENT,
        ], $sessionOptions);

        if ($owner) {
            $data['customer']['id'] = $owner->createOrGetChargebeeCustomer($customerOptions)->id;
        }

        $data['redirectUrl'] = $sessionOptions['success_url'] ?? route('home').'?checkout=success';
        $data['cancelUrl'] = $sessionOptions['cancel_url'] ?? route('home').'?checkout=cancelled';
        $data['currencyCode'] = $sessionOptions['currency_code'] ?? $owner ? $owner->preferredCurrency() : '';

        if ($data['mode'] == Session::MODE_SUBSCRIPTION) {
            $result = HostedPage::checkoutNewForItems($data);
        } elseif ($data['mode'] == Session::MODE_SETUP) {
            $result = HostedPage::managePaymentSources($data);
        } else {
            $result = HostedPage::checkoutOneTimeForItems($data);
        }

        return new Checkout($owner, new Session(
            $result->hostedPage()->getValues(),
            $data['mode']
        ));
    }

    /**
     * Redirect to the checkout session.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(): RedirectResponse
    {
        return Redirect::to($this->session->url, 303);
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request): RedirectResponse
    {
        return $this->redirect();
    }

    /**
     * Get the Checkout Session as a Chargebee Checkout Session object.
     *
     * @return Session
     */
    public function asChargebeeCheckoutSession(): Session
    {
        return $this->session;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): mixed
    {
        return $this->asChargebeeCheckoutSession()->getValues();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0): bool|string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values from the Chargebee object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key): mixed
    {
        return $this->session->{$key};
    }
}
