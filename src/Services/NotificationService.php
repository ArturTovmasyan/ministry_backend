<?php

namespace App\Services;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class NotificationService
 * @package App\Services
 */
class NotificationService
{
    /** @var EntityManagerInterface $entityManage */
    public $entityManage;

    /**
     * NotificationService constructor.
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManage = $entityManager;
    }

    /**
     * This function is used to send notification
     *
     * @param array $users
     * @param string $link
     * @throws \Exception
     */
    public function createNotification(array $users, string $link): void
    {
        if (\is_array($users) && \count($users) > 0) {

            $this->entityManage->beginTransaction();

            foreach ($users as $user) {
                $newNotification = new Notification();
                $newNotification->setUser($user);
                $newNotification->setLink($link);
                $this->entityManage->persist($newNotification);
            }

            $this->entityManage->flush();
            $this->entityManage->getConnection()->commit();
        }
    }
}