<?php

namespace App\Controller\Rest;

use App\Entity\Notification;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class NotificationController
 * @package App\Controller\Rest
 */
class NotificationController extends AbstractController
{
    /**
     * This function is used to get user new notifications
     *
     * @Route("/api/private/v1/user/{id}/notifications", methods={"GET"}, requirements={"id" : "\d+"}, name="ministry_get_user_note")
     * @param int $id
     * @return JsonResponse
     * @throws
     */
    public function getNotificationAction($id): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $notifications */
        $notifications = $entityManager->getRepository(Notification::class)->findByUserId($id);

        return $this->json($notifications, JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to get user new notifications
     *
     * @Route("/api/private/v1/notification/mark-read/{id}", methods={"GET"}, requirements={"id" : "\d+"}, name="ministry_mark_as_read_note")
     * @ParamConverter("notification", options={"id" = "id"})
     *
     * @param Notification $notification
     * @return JsonResponse
     * @throws
     */
    public function markAsReadNotificationAction(Notification $notification): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        // change note as read
        $notification->setIsRead(1);
        $entityManager->persist($notification);
        $entityManager->flush();

        return $this->json(['status' => JsonResponse::HTTP_OK], JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to remove notification by id
     *
     * @Route("/api/private/v1/delete/notification/{id}", methods={"DELETE"}, requirements={"id" : "\d+"}, name="ministry_delete_notification")
     * @ParamConverter("notification", options={"id" = "id"})
     *
     * @param Notification $notification
     * @return JsonResponse
     * @throws
     */
    public function removeNotificationAction(Notification $notification): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($notification);
        $entityManager->flush();

        return $this->json('', JsonResponse::HTTP_NO_CONTENT);
    }
}