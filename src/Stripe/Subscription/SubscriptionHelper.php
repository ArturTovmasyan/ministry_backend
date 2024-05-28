<?php

namespace App\Stripe\Subscription;

use App\Stripe\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Stripe\Customer;
use DateTime;

/**
 * Class SubscriptionHelper
 * @package App\Stripe\Subscription
 */
class SubscriptionHelper
{
    /** @var EntityManagerInterface $em */
    private $em;

    /**
     * @var string[]
     */
    private $plans;

    /**
     * SubscriptionHelper constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        $this->plans = [
            'price_1HAvwTC2Zd622GGGvtMxbHiZ',
            'price_1HAvsHC2Zd622GGGAI1AfyJC'
        ];
    }

    /**
     * @param $planId
     * @return string|void
     */
    public function findPlan($planId)
    {
        foreach ($this->plans as $plan) {
            if ($plan === $planId) {
                return $plan;
            }
        }
    }

    /**
     * @param \Stripe\Subscription $stripeSubscription
     * @param User $user
     */
    public function addSubscriptionToUser(\Stripe\Subscription $stripeSubscription, User $user): void
    {
        $subscription = $user->getSubscription();

        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->setUser($user);
        }

        $periodEnd = DateTime::createFromFormat('U', $stripeSubscription->current_period_end);
        $subscription->activateSubscription($stripeSubscription, $periodEnd);

        $this->em->persist($subscription);
        $this->em->flush();
    }

    /**
     * @param User $user
     * @param Customer $stripeCustomer
     */
    public function updateCardDetails(User $user, Customer $stripeCustomer): void
    {
        $cardDetails = $stripeCustomer->sources->data[0];
        $user->setCardBrand($cardDetails->brand);
        $user->setCardLast4($cardDetails->last4);
        $this->em->persist($user);
        $this->em->flush();
    }

    /**
     * @param Subscription $subscription
     * @param EntityManager $em
     * @throws ORMException
     */
    public function fullyCancelSubscription(Subscription $subscription, EntityManager $em): void
    {
//        $subscription->cancel();
        $em->remove($subscription);
        $this->em->flush();
    }

    /**
     * @param Subscription $subscription
     * @param \Stripe\Subscription $stripeSubscription
     */
    public function handleSubscriptionPaid(Subscription $subscription, \Stripe\Subscription $stripeSubscription): void
    {
        $newPeriodEnd = DateTime::createFromFormat('U', $stripeSubscription->current_period_end);
        // you can use this to send emails to new or renewal customers
//        $isRenewal = $newPeriodEnd > $subscription->getBillingPeriodEndsAt();

        $subscription->setBillingPeriodEndsAt($newPeriodEnd);
        $subscription->setStatus(Subscription::ACTIVE);
        $this->em->persist($subscription);
        $this->em->flush();
    }
}