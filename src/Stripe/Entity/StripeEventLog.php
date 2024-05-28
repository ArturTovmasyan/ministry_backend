<?php

namespace App\Stripe\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="stripe_event_log")
 */
class StripeEventLog
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $stripeEventId;

    /**
     * @ORM\Column(type="datetime")
     */
    private $handledAt;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * StripeEventLog constructor.
     * @param $stripeEventId
     */
    public function __construct($stripeEventId)
    {
        $this->stripeEventId = $stripeEventId;
        $this->handledAt = new DateTime();
    }

    /**
     * @return mixed
     */
    public function getStripeEventId()
    {
        return $this->stripeEventId;
    }

    /**
     * @param mixed $stripeEventId
     */
    public function setStripeEventId($stripeEventId): void
    {
        $this->stripeEventId = $stripeEventId;
    }

    /**
     * @return mixed
     */
    public function getHandledAt()
    {
        return $this->handledAt;
    }

    /**
     * @param mixed $handledAt
     */
    public function setHandledAt($handledAt): void
    {
        $this->handledAt = $handledAt;
    }
}