<?php

namespace Chargebee\Cashier\Concerns;

trait Prorates
{
    /**
     * Indicates if the price change should be prorated.
     *
     * @var ?bool
     */
    protected $prorationBehavior = null;

    /**
     * Indicate that the price change should not be prorated.
     *
     * @return $this
     */
    public function noProrate(): self
    {
        $this->prorationBehavior = false;

        return $this;
    }

    /**
     * Indicate that the price change should be prorated.
     *
     * @return $this
     */
    public function prorate(): self
    {
        $this->prorationBehavior = true;

        return $this;
    }

    /**
     * Set the prorating behavior.
     *
     * @param  bool  $prorationBehavior
     * @return $this
     */
    public function setProrationBehavior($prorationBehavior): self
    {
        $this->prorationBehavior = $prorationBehavior;

        return $this;
    }

    /**
     * Determine the prorating behavior when updating the subscription.
     *
     * @return bool
     */
    public function prorateBehavior(): bool|null
    {
        return $this->prorationBehavior;
    }
}
