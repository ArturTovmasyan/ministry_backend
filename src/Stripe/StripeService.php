<?php

namespace App\Stripe;

use App\Entity\User;
use App\Stripe\Subscription\SubscriptionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Collection;
use Stripe\Customer;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Plan;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Token;

/**
 * Class StripeService
 * @package App\Stripe
 */
class StripeService
{
    private $subscriptionHelper;
    private $em;

    /**
     * StripeClient constructor.
     * @param EntityManagerInterface $em
     * @param SubscriptionHelper $subscriptionHelper
     */
    public function __construct(EntityManagerInterface $em, SubscriptionHelper $subscriptionHelper)
    {
        $this->em = $em;
        $this->subscriptionHelper = $subscriptionHelper;
        $apiKey = getenv('STRIPE_PRIVATE_KEY');
        Stripe::setApiKey($apiKey);
    }

    /**
     * Generate stripe token for testing
     *
     * @return string
     */
    public function generateStripeToken(): ?string
    {
        try {
            $tokenData = Token::create([
                'card' => [
                    'number' => '5555555555554444', //4000000000000341 valid
                    'exp_month' => 6,
                    'exp_year' => 2022,
                    'cvc' => '444'
                ]
            ]);

            if ($tokenData) {
                return $tokenData->id;
            }

        } catch (ApiErrorException $e) {
            return $e;
        }

        return null;
    }

    /**
     * @return Collection
     * @throws ApiErrorException
     */
    public function getPlans(): Collection
    {
        return Plan::all();
    }

    /**
     * @param User $user
     * @param $paymentToken
     * @return Customer
     * @throws ApiErrorException
     */
    public function createCustomer(User $user, $paymentToken): Customer
    {
        $customer = Customer::create([
            'email' => $user->getEmail(),
            'source' => $paymentToken,
        ]);

        $user->setStripeCustomerId($customer->id);
        $this->em->persist($user);
        $this->em->flush();

        return $customer;
    }

    /**
     * @param User $user
     * @param $paymentToken
     * @return Customer
     * @throws ApiErrorException
     */
    public function updateCustomerCard(User $user, $paymentToken): Customer
    {
        $customer = Customer::retrieve($user->getStripeCustomerId());
        $customer->source = $paymentToken;
        $customer->save();

        return $customer;
    }

    /**
     * @param $amount
     * @param User $user
     * @param $description
     * @return InvoiceItem
     * @throws ApiErrorException
     */
    public function createInvoiceItem($amount, User $user, $description): InvoiceItem
    {
        return InvoiceItem::create([
            'amount' => $amount,
            'currency' => 'usd',
            'customer' => $user->getStripeCustomerId(),
            'description' => $description
        ]);
    }

    /**
     * @param User $user
     * @param bool $payImmediately
     * @return Invoice
     * @throws ApiErrorException
     */
    public function createInvoice(User $user, $payImmediately = true): Invoice
    {
        $invoice = Invoice::create([
            'customer' => $user->getStripeCustomerId()
        ]);

        if ($payImmediately) {
            // guarantee it charges *right* now
            $invoice->pay();
        }

        return $invoice;
    }

    /**
     * @param User $user
     * @param string $plan
     * @return Subscription
     * @throws ApiErrorException
     */
    public function createSubscription(User $user, string $plan): Subscription
    {
        return Subscription::create([
            'customer' => $user->getStripeCustomerId(),
            'plan' => $plan
        ]);
    }

    /**
     * @param int $subId
     * @return Subscription
     * @throws ApiErrorException
     */
    public function updateSubscription($subId): Subscription
    {
        $subscription = Subscription::retrieve($subId);
        Subscription::update(
            $subId,
            [
                'billing_cycle_anchor' => 'now',
                'proration_behavior' => 'create_prorations'
            ]
        );

        $subscription->save();
        return $subscription;
    }

    /**
     * @param User $user
     * @return Subscription
     * @throws ApiErrorException
     * @throws \Exception
     */
    public function cancelSubscription(User $user): Subscription
    {
        $sub = Subscription::retrieve($user->getSubscription()->getStripeSubscriptionId());
        $currentPeriodEnd = new \DateTime('@' . $sub->current_period_end);
        $cancelAtPeriodEnd = true;

        if ($sub->status === 'past_due') {
            // past due? Cancel immediately, don't try charging again
            $cancelAtPeriodEnd = false;
        } elseif ($currentPeriodEnd < new \DateTime('+1 hour')) {
            // within 1 hour of the end? Cancel so the invoice isn't charged
            $cancelAtPeriodEnd = false;
        }

        $sub::update(
            $user->getSubscription()->getStripeSubscriptionId(),
            [
                'cancel_at_period_end' => $cancelAtPeriodEnd
            ]
        );

        return $sub;
    }

    /**
     * @param User $user
     * @return Subscription
     * @throws ApiErrorException
     */
    public function reactivateSubscription(User $user): Subscription
    {
        if (!$user->hasActiveSubscription()) {
            throw new \LogicException('Subscriptions can only be reactivated if the subscription has not actually ended yet');
        }

        $subscription = Subscription::retrieve(
            $user->getSubscription()->getStripeSubscriptionId()
        );

        Subscription::update($user->getSubscription()->getStripeSubscriptionId(), [
            'cancel_at_period_end' => false,
            'items' => [
                [
                    'id' => $subscription->items->data[0]->id,
                    'plan' => $user->getSubscription()->getStripePlanId()
                ]
            ]
        ]);

        $subscription->save();

        return $subscription;
    }

    /**
     * @param $eventId
     * @return Event
     * @throws ApiErrorException
     */
    public function findEvent($eventId): Event
    {
        return Event::retrieve($eventId);
    }

    /**
     * @param $stripeSubscriptionId
     * @return Subscription
     * @throws ApiErrorException
     */
    public function findSubscription($stripeSubscriptionId): Subscription
    {
        return Subscription::retrieve($stripeSubscriptionId);
    }

    /**
     * @param int $planId
     * @return string|void
     */
    public function getSubscriptionPlan($planId)
    {
        return $this->subscriptionHelper->findPlan($planId);
    }

    /**
     * @param Entity\Subscription $subscription
     * @return Invoice
     * @throws ApiErrorException
     */
    public function payOpenInvoice(Entity\Subscription $subscription): Invoice
    {
        $lastInvoice = $subscription->getLastInvoice();
        $invoice = Invoice::retrieve($lastInvoice);
        $invoice->pay();

        return $invoice;
    }

    /**
     * @param string $token
     * @param User $user
     * @param SubscriptionHelper $subscriptionHelper
     * @throws ApiErrorException
     */
    public function updateUserCard(
        $token,
        User $user,
        SubscriptionHelper $subscriptionHelper
    ): void
    {
        // update card details
        if (!$user->getStripeCustomerId()) {
            $stripeCustomer = $this->createCustomer($user, $token);
        } else {
            $stripeCustomer = $this->updateCustomerCard($user, $token);
        }

        $subscriptionHelper->updateCardDetails($user, $stripeCustomer);
    }

    /**
     * @param Entity\Subscription|null $userSubscription
     * @return bool
     * @throws ApiErrorException
     */
    public function payInvoiceForInactiveSubscription($userSubscription): bool
    {
        // pay inactive (open) invoice
        if ($userSubscription && $userSubscription->notActiveYet()) {

            $invoice = $this->payOpenInvoice($userSubscription);

            if ($invoice->status === 'paid') {
                $userSubscription->setStatus(Entity\Subscription::ACTIVE);
                $this->em->persist($userSubscription);
                $this->em->flush();

                return true;
            }
        }

        return false;
    }
}