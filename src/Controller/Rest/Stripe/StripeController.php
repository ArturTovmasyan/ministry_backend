<?php

namespace App\Controller\Rest\Stripe;

use App\Controller\Exception\Exception;
use App\Entity\User;
use App\Stripe\StripeService;
use App\Stripe\Subscription\SubscriptionHelper;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SubscriptionController
 * @package App\Controller\Rest\Stripe
 */
class StripeController extends AbstractController
{
    /**
     * @Route("/api/private/v1/get/plans", methods={"GET"}, name="plan_list")
     * @param StripeService $stripeService
     * @return JsonResponse
     * @throws ApiErrorException
     */
    public function plansAction(StripeService $stripeService): JsonResponse
    {
        $plans = $stripeService->getPlans();
        return $this->json($plans);
    }

    /**
     * @Route("/api/private/v1/plan/{id}", methods={"DELETE"}, name="plan_delete")
     * @param string $id
     * @return JsonResponse
     * @throws ApiErrorException
     */
    public function deletePlanAction(string $id): JsonResponse
    {
        $stripe = new StripeClient(
            getenv('STRIPE_PRIVATE_KEY')
        );

        $response = $stripe->plans->delete($id, []);

        return $this->json($response);
    }

    /**
     * @Route("/api/private/v1/product/{id}", methods={"DELETE"}, name="product_delete")
     * @param string $id
     * @return JsonResponse
     * @throws ApiErrorException
     */
    public function deleteProductAction(string $id): JsonResponse
    {
        $stripe = new StripeClient(
            getenv('STRIPE_PRIVATE_KEY')
        );

        $response = $stripe->products->delete($id, []);

        return $this->json($response);
    }

    /**
     * @Route("/api/private/v1/get/subscribe/status/{id}", methods={"GET"}, name="subscribe_status")
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
    public function getSubscribeStatusAction(int $id): JsonResponse
    {
        $em = $this->get('doctrine')->getManager();

        /** @var User $user */
        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            throw new Exception('User not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $subscribeStatus = $user->isActive();

        return $this->json(['subscribe_status' => $subscribeStatus], JsonResponse::HTTP_OK);
    }

    /**
     * @Route("/api/private/v1/subscribe", methods={"POST"}, name="subscribe_user")
     * @param Request $request
     * @param StripeService $stripeService
     * @param SubscriptionHelper $subscriptionHelper
     * @return JsonResponse
     * @throws ApiErrorException
     * @throws Exception
     */
    public function subscribeAction(
        Request $request,
        StripeService $stripeService,
        SubscriptionHelper $subscriptionHelper): JsonResponse
    {
        try {
            // get stripe token and planId from request
            $token = $request->request->get('stripeToken');
//            $token = $stripeService->generateStripeToken();
            $planId = $request->request->get('planId');
            $userId = $request->request->get('userId');

            if (!$token || !$planId || !$userId) {
                throw new Exception('Invalid post data', JsonResponse::HTTP_BAD_REQUEST);
            }

            $this->chargeCustomer($userId, $token, $planId, $stripeService, $subscriptionHelper);
        } catch (CardException $e) {
            $error = 'There was a problem charging your card: ' . $e->getMessage();
            throw new Exception($error, $e->getCode());
        }

        return $this->json(['message' => 'success']);
    }

    /**
     * @Route("/api/private/v1/subscription/cancel/{userId}", methods={"GET"}, name="subscription_cancel")
     * @param int $userId
     * @param StripeService $stripeService
     * @return JsonResponse
     * @throws ApiErrorException
     * @throws Exception
     */
    public function cancelSubscriptionAction(int $userId, StripeService $stripeService): JsonResponse
    {
        try {
            $em = $this->getDoctrine()->getManager();

            /** @var User $user */
            $user = $em->getRepository(User::class)->find($userId);

            if (!$user) {
                throw new Exception('User not found', JsonResponse::HTTP_NOT_FOUND);
            }

            $stripeSubscription = $stripeService->cancelSubscription($user);
            $subscription = $user->getSubscription();

            if ($stripeSubscription->status === 'canceled') {
                // the subscription was cancelled immediately
                $subscription ? $subscription->cancel() : null;
            } else {
                $subscription ? $subscription->deactivateSubscription() : null;
            }

            $em->persist($subscription);
            $em->flush();

        } catch (CardException $e) {
            $error = 'Can\'t cancel or deactivate subscription: ' . $e->getMessage();
            throw new Exception($error, $e->getCode());
        }

        return $this->json(['message' => 'success']);
    }

    /**
     * @Route("/api/private/v1/subscription/reactivate/{userId}", methods={"GET"}, name="subscription_reactivate")
     * @param int $userId
     * @param StripeService $stripeService
     * @param SubscriptionHelper $subscriptionHelper
     * @return JsonResponse
     * @throws ApiErrorException
     * @throws Exception
     */
    public function reactivateSubscriptionAction(
        int $userId,
        StripeService $stripeService,
        SubscriptionHelper $subscriptionHelper
    ): JsonResponse
    {
        $em = $this->getDoctrine()->getManager();

        /** @var User $user */
        $user = $em->getRepository(User::class)->find($userId);

        if (!$user) {
            throw new Exception('User not found', JsonResponse::HTTP_NOT_FOUND);
        }

        $stripeSubscription = $stripeService->reactivateSubscription($user);
        $subscriptionHelper->addSubscriptionToUser($stripeSubscription, $user);

        return $this->json(['message' => 'success']);
    }

    /**
     * @Route("/api/private/v1/card/update", methods={"POST"}, name="update_credit_card")
     * @param Request $request
     * @param StripeService $stripeService
     * @param SubscriptionHelper $subscriptionHelper
     * @return JsonResponse
     * @throws ApiErrorException
     * @throws Exception
     */
    public function updateCreditCardAction(
        Request $request,
        StripeService $stripeService,
        SubscriptionHelper $subscriptionHelper
    ): JsonResponse
    {
        try {
            $token = $request->request->get('stripeToken');
//            $token = $stripeService->generateStripeToken();
            $userId = $request->request->get('userId');

            if (!$token || !$userId) {
                throw new Exception('Invalid post data', JsonResponse::HTTP_BAD_REQUEST);
            }

            $em = $this->getDoctrine()->getManager();

            /** @var User $user */
            $user = $em->getRepository(User::class)->find($userId);

            if (!$user) {
                throw new Exception('User not found', JsonResponse::HTTP_NOT_FOUND);
            }

            $stripeCustomer = $stripeService->updateCustomerCard($user, $token);
            $subscriptionHelper->updateCardDetails($user, $stripeCustomer);
        } catch (CardException $e) {
            $error = 'There was a problem updating card: ' . $e->getMessage();
            throw new Exception($error, $e->getCode());
        }

        return $this->json(['message' => 'success']);
    }

    /**
     * @param string $token
     * @param string $planId
     * @param int $userId
     * @param StripeService $stripeService
     * @param SubscriptionHelper $subscriptionHelper
     * @throws ApiErrorException
     * @throws Exception
     */
    private function chargeCustomer(
        int $userId,
        string $token,
        string $planId,
        StripeService $stripeService,
        SubscriptionHelper $subscriptionHelper): void
    {
        $em = $this->getDoctrine()->getManager();

        /** @var User $user */
        $user = $em->getRepository(User::class)->find($userId);

        if (!$user) {
            throw new Exception('User not found', JsonResponse::HTTP_NOT_FOUND);
        }

        // update user card details
        $stripeService->updateUserCard($token, $user, $subscriptionHelper);

        // get subscription plan for pay
        $subscriptionPlan = $stripeService->getSubscriptionPlan($planId);

        if (!$subscriptionPlan) {
            throw new Exception("Plan by id=$planId not found", JsonResponse::HTTP_NOT_FOUND);
        }

        $userSubscription = $user->getSubscription();
        $payed = $stripeService->payInvoiceForInactiveSubscription($userSubscription);

        if ($payed) {
            return;
        }

        if ($userSubscription && $userSubscription->isActive()) {
            // update exist subscription
            $stripeSubscription = $stripeService->updateSubscription($userSubscription->getStripeSubscriptionId());
        } else {
            // create subscription
            $stripeSubscription = $stripeService->createSubscription(
                $user,
                $subscriptionPlan
            );
        }

        // add subscription to related user
        $subscriptionHelper->addSubscriptionToUser(
            $stripeSubscription,
            $user
        );
    }
}