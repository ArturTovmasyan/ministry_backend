<?php

namespace App\Controller\Rest\Stripe;

use App\Controller\Exception\Exception;
use App\Services\EmailService;
use App\Stripe\Entity\StripeEventLog;
use App\Stripe\Entity\Subscription;
use App\Stripe\StripeService;
use App\Stripe\Subscription\SubscriptionHelper;
use Stripe\Invoice;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class WebhookController
 * @package App\Controller\Rest\Stripe
 */
class WebhookController extends AbstractController
{
    /**
     * @Route("/webhooks/stripe", name="webhook_stripe", methods={"POST"})
     * @param Request $request
     * @param StripeService $stripeService
     * @param SubscriptionHelper $subscriptionHelper
     * @param EmailService $emailService
     * @return JsonResponse
     * @throws ApiErrorException
     * @throws Exception
     * @throws \Exception
     */
    public function stripeWebhookAction(
        Request $request,
        StripeService $stripeService,
        SubscriptionHelper $subscriptionHelper,
        EmailService $emailService
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            throw new Exception('Bad JSON body from Stripe!', JsonResponse::HTTP_BAD_REQUEST);
        }

        $eventId = $data['id'];
        $em = $this->getDoctrine()->getManager();
        $existingLog = $em->getRepository(StripeEventLog::class)->findOneBy(['stripeEventId' => $eventId]);

        if ($existingLog) {
            return $this->json(['message' => 'Event previously handled']);
        }

        $log = new StripeEventLog($eventId);
        $em->persist($log);
        $em->flush();

        $stripeEvent = $stripeService->findEvent($eventId);

        switch ($stripeEvent->type) {
            case 'customer.subscription.deleted':
                $stripeSubscriptionId = $stripeEvent->data->object->id;
                $subscription = $this->findSubscription($stripeSubscriptionId);
                $subscriptionHelper->fullyCancelSubscription($subscription, $em);
                break;
            case 'invoice.payment_succeeded':
                $stripeSubscriptionId = $stripeEvent->data->object->subscription;

                if ($stripeSubscriptionId) {
                    $subscription = $this->findSubscription($stripeSubscriptionId);
                    $stripeSubscription = $stripeService->findSubscription($stripeSubscriptionId);
                    $subscriptionHelper->handleSubscriptionPaid($subscription, $stripeSubscription);
                }
                break;
            case 'invoice.payment_failed':
                $stripeSubscriptionId = $stripeEvent->data->object->subscription;

                if ($stripeSubscriptionId) {
                    $subscription = $this->findSubscription($stripeSubscriptionId);

                    if ($stripeEvent->data->object->attempt_count === 1) {
                        $user = $subscription->getUser();
                        $email = $user->getEmail();

                        $invoice = Invoice::retrieve($subscription->getLastInvoice());
                        $invoiceSum = $invoice->total / 100;
                        $invoiceSum .= '$';

                        // generate failed payment email
                        $emailData = [
                            'subject' => 'Stripe failed payment',
                            'toEmail' => $email,
                            'fullName' => $user->getFullName(),
                            'type' => 'payment-failure',
                            'invoice_sum' => $invoiceSum,
                            'backend_host' => getenv('BACKEND_HOST'),
                            'card_brand' => $user->getCardBrand(),
                            'card_end' => $user->getCardLast4()
                        ];

                        $emailService->sendEmail($emailData);
                    }
                }
                break;
            default:
                break;
        }

        return $this->json(['message' => 'Event Handled: ' . $stripeEvent->type]);
    }

    /**
     * @param $stripeSubscriptionId
     * @return Subscription|object|null
     * @throws Exception
     */
    private function findSubscription($stripeSubscriptionId)
    {
        $subscription = $this->getDoctrine()
            ->getRepository(Subscription::class)
            ->findOneBy([
                'stripeSubscriptionId' => $stripeSubscriptionId
            ]);

        if (!$subscription) {
            throw new Exception(
                'Somehow we have no subscription id ' . $stripeSubscriptionId,
                JsonResponse::HTTP_BAD_REQUEST);
        }

        return $subscription;
    }
}
