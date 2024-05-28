<?php

namespace App\EventListener;

use App\Entity\User;
use App\Services\EmailService;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Exception;

/**
 * Class DoctrineListener
 * @package App\EventListener
 */
class DoctrineListener
{
    /** @var EmailService $emailService */
    protected $emailService;

    /**
     * RequestListener constructor.
     * @param EmailService $emailService
     */
    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * @param PreUpdateEventArgs $args
     * @throws Exception
     */
    public function preUpdate(PreUpdateEventArgs $args):void
    {
        // get entity
        $entity = $args->getObject();

        // check entity
        if ($entity instanceof User && $entity->getStatus() === User::REGISTERED) {

            // set default variables
            $firstNameChanged = false;
            $lastNameChanged = false;
            $passwordChanged = false;
            $newFullName = null;
            $newFirstName = null;
            $oldFullName = null;
            $oldFirstName = null;

            // check if user data changed
            if ($args->hasChangedField('firstName')) {

                $firstNameChanged = true;

                $oldFirstName = $args->getOldValue('firstName');
                $oldFullName = $oldFirstName . ' ' . $entity->getLastName();

                $newFirstName = $entity->getFirstName();
                $newFullName = $newFirstName . ' ' . $entity->getLastName();
            }

            if ($args->hasChangedField('lastName')) {

                $lastNameChanged = true;

                $oldLastName = $args->getOldValue('lastName');
                $oldFullName = ($oldFirstName ?? $entity->getFirstName()) . ' ' . $oldLastName;

                $newLastName = $entity->getLastName();
                $newFullName = ($newFirstName ?? $entity->getFirstName()) . ' ' . $newLastName;
            }

            if ($args->hasChangedField('password')) {
                $passwordChanged = true;
            }

            $isPasswordReset = $entity->isPasswordReset;

            if (($firstNameChanged || $lastNameChanged) && $passwordChanged) {

                // generate change email data
                $data = [
                    'subject' => 'You recently changed your name, surname and password',
                    'fullName' => $newFullName,
                    'toEmail' => $entity->getEmail(),
                    'type' => 'change-user-all-data',
                    'backend_host' => getenv('BACKEND_HOST')
                ];

            } elseif (($firstNameChanged || $lastNameChanged) && !$passwordChanged) {

                // generate change email data
                $data = [
                    'subject' => 'You recently changed your name/surname',
                    'userData' => ['oldFullName' => $oldFullName, 'newFullName' => $newFullName],
                    'toEmail' => $entity->getEmail(),
                    'type' => 'change-user-name',
                    'backend_host' => getenv('BACKEND_HOST')
                ];

            } elseif ((!$firstNameChanged && !$lastNameChanged) && $passwordChanged && !$isPasswordReset) {

                // generate change email data
                $data = [
                    'subject' => 'You recently changed your password',
                    'fullName' => $entity->getFullName(),
                    'toEmail' => $entity->getEmail(),
                    'type' => 'change-password',
                    'backend_host' => getenv('BACKEND_HOST')
                ];
            }

            if (!empty($data)) {
                // send email by service
                $this->emailService->sendEmail($data);
            }
        }
    }
}

