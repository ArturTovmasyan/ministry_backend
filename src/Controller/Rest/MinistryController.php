<?php

namespace App\Controller\Rest;

use App\Entity\FilterCategory;
use App\Entity\School;
use App\Entity\User;
use App\Services\EmailService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class MinistryController
 * @package App\Controller\Rest
 */
class MinistryController extends AbstractController
{
    /**
     * This function is used to confirm user registration
     *
     * @Route("/api/private/v1/filters/data", methods={"GET"}, name="ministry_get_filters_data")
     *
     * @param SerializerInterface $serializer
     * @return JsonResponse
     * @throws
     */
    public function getFiltersDataAction(SerializerInterface $serializer): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $filters */
        $filters = $entityManager->getRepository(FilterCategory::class)->findAll();

        // generate filters data body
        $userContent = $serializer->serialize($filters, 'json', SerializationContext::create()->setGroups(['filter']));

        // create new json Response
        $response = new JsonResponse();

        // set data in response content
        $response->setContent($userContent);

        return $response;
    }

    /**
     * This function is used to get school data
     *
     * @Route("/api/public/v1/schools", methods={"GET"}, name="ministry_get_school_data")
     *
     * @return JsonResponse
     * @throws
     */
    public function getSchoolDataAction(): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $schools */
        $schools = $entityManager->getRepository(School::class)->findSchools();

        return $this->json($schools, JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to load data in DB by migration
     *
     * @Route("/api/private/v1/load/migration", methods={"GET"}, name="ministry_migration_db")
     * @return JsonResponse
     * @throws
     */
    public function loadDbDataAction(): JsonResponse
    {
        //Run migration diff and execute custom migration on server by this API
        $process = new Process(['php  ../bin/console d:m:m --no-interaction --env=prod &&  php ../bin/console d:m:e 20181024193444 --no-interaction --env=prod']);
        $process->run();

        //executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $this->json(['status' => JsonResponse::HTTP_OK, 'message' => 'Data successfully loaded in DB'], JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to send email for invite people join project
     *
     * @Route("/api/private/v1/invite/to-join", methods={"POST"}, name="ministry_invite_people_join")
     *
     * @param Request $request
     * @param EmailService $emailService
     * @return JsonResponse | null
     *
     * @throws
     */
    public function invitePeopleToJoinAction(Request $request, EmailService $emailService): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        // get request data
        $sender = $request->request->get('instructorId');
        $userEmails = $request->request->get('userEmails');
        $webHost = getenv('WEB_HOST');

        /** @var User $senderData */
        $senderData = $entityManager->getRepository(User::class)->find($sender);
        $senderName = $senderData->getFullName();

        // structure array with key emails for SendGrid.
        $emails = [];
        foreach ($userEmails as $userEmail) {
            $emails[$userEmail] = '';
        }

        // generate invite people join email
        $emailData = [
            'subject' => 'You are part of a class now.',
            'toEmail' => $emails,
            'sender' => $senderName,
            'type' => 'invite-users',
            'web_host' => $webHost,
            'backend_host' => getenv('BACKEND_HOST')
        ];

        // send email
        $emailService->sendEmail($emailData);

        return $this->json(['status' => JsonResponse::HTTP_OK], JsonResponse::HTTP_OK);
    }
}