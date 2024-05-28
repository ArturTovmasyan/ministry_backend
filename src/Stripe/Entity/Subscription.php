<?php

namespace App\Stripe\Entity;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="subscription")
 */
class Subscription
{
    public const INACTIVE = 0;
    public const ACTIVE = 1;
    public const CANCEL = 2;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", inversedBy="subscription")
     * @ORM\JoinColumn(nullable=true)
     */
    private $user;

    /**
     * @ORM\Column(type="string")
     */
    private $stripeSubscriptionId;

    /**
     * @ORM\Column(type="string")
     */
    private $stripePlanId;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $endsAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $billingPeriodEndsAt;

    /**
     * @ORM\Column(type="smallint", name="status", nullable=false)
     */
    private $status = self::INACTIVE;

    /**
     * @ORM\Column(type="string", name="last_invoice", nullable=true)
     */
    private $lastInvoice;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getStripeSubscriptionId()
    {
        return $this->stripeSubscriptionId;
    }

    public function getStripePlanId()
    {
        return $this->stripePlanId;
    }

    /**
     * @return mixed
     */
    public function getEndsAt()
    {
        return $this->endsAt;
    }

    public function setEndsAt(DateTime $endsAt = null): void
    {
        $this->endsAt = $endsAt;
    }

    /**
     * @return DateTime
     */
    public function getBillingPeriodEndsAt(): DateTime
    {
        return $this->billingPeriodEndsAt;
    }

    /**
     * @param \Stripe\Subscription $stripeSubscription
     * @param DateTime $periodEnd
     */
    public function activateSubscription(\Stripe\Subscription $stripeSubscription, DateTime $periodEnd): void
    {
        $this->stripePlanId = $stripeSubscription->plan->id;
        $this->stripeSubscriptionId = $stripeSubscription->id;
        $this->status = $stripeSubscription->status === 'active' ? self::ACTIVE : self::INACTIVE;
        $this->lastInvoice = $stripeSubscription->latest_invoice;
        $this->billingPeriodEndsAt = $periodEnd;
        $this->endsAt = null;
    }

    /**
     * Deactivate subscription after period date end
     */
    public function deactivateSubscription(): void
    {
        // paid through end of period
        $this->endsAt = $this->billingPeriodEndsAt;
        $this->billingPeriodEndsAt = null;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return ($this->endsAt === null || $this->endsAt > new DateTime()) && $this->status === self::ACTIVE;
    }

    /**
     * @return bool
     */
    public function notActiveYet(): bool
    {
        return ($this->endsAt === null || $this->endsAt > new DateTime()) && $this->status === self::INACTIVE;
    }

    /**
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->endsAt !== null || $this->status === self::CANCEL;
    }

    /**
     * Set stripeSubscriptionId.
     *
     * @param string $stripeSubscriptionId
     *
     * @return Subscription
     */
    public function setStripeSubscriptionId($stripeSubscriptionId): Subscription
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;

        return $this;
    }

    /**
     * Set stripePlanId.
     *
     * @param string $stripePlanId
     *
     * @return Subscription
     */
    public function setStripePlanId($stripePlanId): Subscription
    {
        $this->stripePlanId = $stripePlanId;

        return $this;
    }

    /**
     * Set billingPeriodEndsAt.
     *
     * @param DateTime|null $billingPeriodEndsAt
     *
     * @return Subscription
     */
    public function setBillingPeriodEndsAt($billingPeriodEndsAt = null): Subscription
    {
        $this->billingPeriodEndsAt = $billingPeriodEndsAt;
        return $this;
    }

    /**
     * Cancel subscription immediately
     */
    public function cancel(): void
    {
        $this->endsAt = new DateTime();
        $this->billingPeriodEndsAt = null;
        $this->status = self::CANCEL;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param $status
     * @return $this
     */
    public function setStatus($status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getLastInvoice(): ?string
    {
        return $this->lastInvoice;
    }

    public function setLastInvoice(?string $lastInvoice): self
    {
        $this->lastInvoice = $lastInvoice;

        return $this;
    }
}
