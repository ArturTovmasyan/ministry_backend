<?php

namespace App\Controller\Rest;

use App\Components\Helper\JsonHelper;
use App\Controller\Exception\Exception;
use App\Entity\AssignTest;
use App\Entity\ChallengeTest;
use App\Entity\ChallengeTestHistory;
use App\Entity\Test;
use App\Entity\User;
use App\Services\ChallengeTestService;
use App\Services\EmailService;
use App\Services\MinistryService;
use App\Services\ValidateService;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ChallengeTestController
 * @package App\Controller\Rest
 */
class ChallengeTestController extends AbstractController
{
    /** @var $emailService */
    protected $emailService;

    /** @var $ministryService */
    protected $ministryService;

    /** @var $challengeTestService */
    protected $challengeTestService;

    /**
     * ChallengeTestController constructor.
     * @param MinistryService $ministryService
     * @param EmailService $emailService
     * @param ChallengeTestService $challengeTestService
     */
    public function __construct(MinistryService $ministryService, EmailService $emailService, ChallengeTestService $challengeTestService)
    {
        $this->ministryService = $ministryService;
        $this->emailService = $emailService;
        $this->challengeTestService = $challengeTestService;
    }

    /**
     * This function is used to create challenge test
     *
     * @Route("/api/private/v1/challenge-test/create", methods={"POST"}, name="ministry_challenge_test_create")
     *
     * @param Request $request
     * @param ValidateService $validateService
     * @return JsonResponse
     *
     * @throws
     */
    public function challengeTestAction(Request $request, ValidateService $validateService): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            // start DB transaction
            $entityManager->getConnection()->beginTransaction();
            $postData = $request->request->all();

            /** @var ChallengeTest $existChallengeTest */
            $existChallengeTest = $entityManager->getRepository(ChallengeTest::class)->findOneBy(['student' => $postData['student_id']], ['id' => 'DESC']);

            if ($existChallengeTest) {
                $currentDate = new \DateTime();
                $finishDate = $existChallengeTest->getCreatedAt();

                // check if test challenged less then 24 hour
                if ($finishDate->add(new \DateInterval('P1D')) > $currentDate) {
                    return $this->json(['status' => JsonResponse::HTTP_BAD_REQUEST, 'message' => 'You can challenge only 1 test in 24 hour.'], JsonResponse::HTTP_BAD_REQUEST);
                }
            }

            /** @var Test $test */
            $test = $entityManager->getRepository(Test::class)->find($postData['test_id']);

            /** @var User $student */
            $student = $entityManager->getRepository(User::class)->find($postData['student_id']);

            /** @var User $competitor */
            $competitor = $entityManager->getRepository(User::class)->find($postData['competitor_id']);

            // check if objects not found
            if (!$test || !$student || !$competitor) {
                return $this->json(['status' => JsonResponse::HTTP_BAD_REQUEST, 'message' => 'Invalid post data.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $generateConfirmToken = JsonHelper::generateCode(25);

            $challengeTest = new ChallengeTest();
            $challengeTest->setTest($test);
            $challengeTest->setConfirmToken($generateConfirmToken);
            $challengeTest->setStudent($student);
            $challengeTest->setCompetitor($competitor);
            $challengeTest->setConfirmToken($generateConfirmToken);
            $challengeTest->setLastCheckedDate(new \DateTime());
            $entityManager->persist($challengeTest);

            $validateService->checkValidation($challengeTest);

            // create assign tests
            $assignTestForStudent = $this->ministryService->createAssignTest($test, $student, AssignTest::STARTED);
            $assignTestForCompetitor = $this->ministryService->createAssignTest($test, $competitor);

            // connect assign tests with challenge object
            $assignTestForStudent->setChallengeTest($challengeTest);
            $assignTestForCompetitor->setChallengeTest($challengeTest);

            $entityManager->persist($assignTestForStudent);
            $entityManager->persist($assignTestForCompetitor);

            $entityManager->flush();
            $entityManager->getConnection()->commit();

            // generate student confirm to assign class url
            $confirmChallengeUrl = $this->generateUrl('ministry_confirm_challenge_test');
            $confirmChallengeUrl = getenv('BACKEND_HOST') . $confirmChallengeUrl . '?assignTestId=' . $assignTestForCompetitor->getId() . '&&ct=' . $generateConfirmToken;

            // generate challenge test data
            $emailData = [
                'subject' => 'You have been challenged!',
                'test' => $test->getName(),
                'sender' => $student->getFullName(),
                'competitor' => $competitor->getFullName(),
                'toEmail' => $competitor->getEmail(),
                'confirmChallengeUrl' => $confirmChallengeUrl,
                'backend_host' => getenv('BACKEND_HOST'),
                'type' => 'challenge-test',
            ];

            // send email
            $this->emailService->sendEmail($emailData);

        } catch (Exception $e) {

            // roll back query changes in DB
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json(['status' => JsonResponse::HTTP_CREATED, 'assign_test_id' => $assignTestForStudent->getId()], JsonResponse::HTTP_CREATED);
    }

    /**
     * This function is used to for confirm challenge test
     *
     * @Route("/api/public/v1/confirm/challenge-test", methods={"GET"}, name="ministry_confirm_challenge_test")
     *
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     * @throws
     */
    public function confirmChallengeTestAction(Request $request)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();
        $confirmToken = $request->query->get('ct');
        $assignTestId = $request->query->get('assignTestId');

        /** @var ChallengeTest $challengeTest */
        $challengeTest = $entityManager->getRepository(ChallengeTest::class)->findOneBy(['confirmToken' => $confirmToken]);

        /** @var AssignTest $assignTest */
        $assignTest = $entityManager->getRepository(AssignTest::class)->find($assignTestId);

        if (!$challengeTest || !$assignTest) {
            return $this->redirect(getenv('WEB_HOST'), JsonResponse::HTTP_FOUND);
        }

        // start DB transaction
        $entityManager->getConnection()->beginTransaction();
        $challengeTest->setConfirmToken('');
        $challengeTest->setType(ChallengeTest::STARTED);
        $entityManager->persist($challengeTest);

        $assignTest->setStatus(AssignTest::STARTED);
        $entityManager->persist($assignTest);
        $entityManager->flush();

        $entityManager->getConnection()->commit();
        $startTestUrl = getenv('WEB_HOST').'/taking-test/'.$assignTestId;

        return $this->redirect($startTestUrl, JsonResponse::HTTP_FOUND);
    }

    /**
     * This function is used to for check challenge test time limit
     *
     * @Route("/api/private/v1/check/challenge/time-limit/{challengeTestId}",
     *      methods={"GET"},
     *      name="ministry_check_challenge_test_time_limit")
     *
     * @param int $challengeTestId
     * @return JsonResponse|RedirectResponse
     * @throws
     */
    public function checkChallengeTestTimeLimitAction(int $challengeTestId)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ChallengeTest $challengeTest */
        $challengeTest = $entityManager->getRepository(ChallengeTest::class)->find($challengeTestId);

        if (!$challengeTest || $challengeTest->getType() === ChallengeTest::FINISHED) {
            return $this->json(['status' => JsonResponse::HTTP_NOT_FOUND, 'message' => "Challenge test by id=$challengeTestId not found or finished."], JsonResponse::HTTP_NOT_FOUND);
        }

        $lastCheckDate = $challengeTest->getLastCheckedDate();
        $finishDate = $challengeTest->getCreatedAt();
        $currentDate = new \DateTime();

        // check last check time (1 hour) and test time limit (24 hour)
        if ($lastCheckDate && $lastCheckDate->add(new \DateInterval('PT1H')) < $currentDate &&
            $finishDate->add(new \DateInterval('P1D')) < $currentDate) {

            // finish challenge test
            $this->challengeTestService->finishChallengeTestAndSendEmail($challengeTest, $entityManager, $this->emailService);
            return $this->json(['status' => JsonResponse::HTTP_OK, 'message' => 'Challenge test was be successfully calculated and finished, because time is limited.'], JsonResponse::HTTP_OK);
        }

        $challengeTest->setLastCheckedDate(new \DateTime());
        $entityManager->persist($challengeTest);
        $entityManager->flush();

        return $this->json(['status' => JsonResponse::HTTP_OK, 'message' => "Your test limit will be finished at {$challengeTest->getUpdatedAt()->format('Y-m-d h:m:s')}"], JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to get challenge test data for all students
     *
     * @Route("/api/private/v1/get/challenge-test/{limit}", methods={"GET"}, name="ministry_get_challenge_test_lm")
     * @Route("/api/private/v1/get/challenge-test", methods={"GET"}, name="ministry_get_challenge_test")
     * @param EmailService $emailService
     * @param $limit
     * @return JsonResponse|RedirectResponse
     * @throws
     */
    public function getChallengeTestDataAction(EmailService $emailService, $limit = null)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();
        $this->challengeTestService->updateChallengeTestState($entityManager, $emailService);

        /** @var array $challengeTests */
        $challengeTests = $entityManager->getRepository(ChallengeTestHistory::class)->findChallengeTestData($limit);

        return $this->json($challengeTests, JsonResponse::HTTP_OK);
    }
}
