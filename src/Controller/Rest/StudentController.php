<?php

/**
 * Created by PhpStorm.
 * User: arthurt
 * Date: 11/4/18
 * Time: 9:54 PM
 */
namespace App\Controller\Rest;

use App\Controller\Exception\Exception;
use App\Entity\AssignTest;
use App\Entity\PassedQuestion;
use App\Entity\StudentClass;
use App\Entity\User;
use App\Services\ChallengeTestService;
use App\Services\EmailService;
use App\Services\ValidateService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class StudentController
 * @package App\Controller\Rest
 */
class StudentController extends AbstractController
{
    /**
     * This function is used to get student which not assign in class
     *
     * @Route("/api/private/v1/spare/students/{schoolId}", methods={"GET"}, name="ministry_get_spare_students")
     *
     * @param SerializerInterface $serializer
     * @param int|null $schoolId
     * @return JsonResponse
     * @throws
     */
    public function getSpareStudentsDataAction(SerializerInterface $serializer, $schoolId = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $students */
        $students = $entityManager->getRepository(User::class)->getSpareStudents($schoolId);

        // generate filters data body
        $userContent = $serializer->serialize($students, 'json', SerializationContext::create()->setGroups(['student']));

        // create new json Response
        $response = new JsonResponse();

        // set data in response content
        $response->setContent($userContent);

        return $response;
    }

    /**
     * This function is used to confirm by student assign to class
     *
     * @Route("/api/public/v1/student/{studentId}/class/{classId}/{token}", methods={"GET"}, name="ministry_confirm_user_class",
     * requirements={"studentId" : "\d+", "classId" : "\d+"})
     *
     * @ParamConverter("class", options={"id" = "classId"})
     *
     * @param $studentId
     * @param StudentClass $class
     * @param $token
     *
     * @return RedirectResponse
     * @throws
     */
    public function confirmAssignToClassAction($studentId, StudentClass $class, $token): RedirectResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {

            if (!$class) {
                throw new Exception('Class not found', JsonResponse::HTTP_NOT_FOUND);
            }

            //start DB transaction
            $entityManager->getConnection()->beginTransaction();

            /** @var User $student */
            $student = $entityManager->getRepository(User::class)->getStudentByIdAndToken($studentId, $token);

            if (!$student) {
                throw new Exception('Not user found for confirmation.', JsonResponse::HTTP_NOT_FOUND);
            }

            if ($student->getStatus() === User::CREATED) {
                $student->setStatus(User::REGISTERED);
            }

            // remove confirm token
            $student->setClassToken(null);
            $entityManager->persist($student);

            // auto assign test to students
            $this->autoAssignTestsToStudent($student, $entityManager);

            $entityManager->flush();
            $entityManager->getConnection()->commit();

        } catch (Exception $e) {
            // roll back query changes in DB
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        $host = getenv('WEB_HOST');

        return $this->redirect($host, JsonResponse::HTTP_FOUND);
    }

    /**
     * This function is used to get student analytics data
     *
     * @Route("/api/private/v1/student/analytics/{id}/{schoolId}", methods={"GET"}, requirements={"id" : "\d+"}, name="ministry_student_analytics")
     *
     * @param int $id
     * @param int|null $schoolId
     * @param ValidateService $validateService
     * @return JsonResponse
     * @throws
     */
    public function getStudentAnalyticAction(ValidateService $validateService, $id, $schoolId = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $studentHistory */
        $studentHistory = $entityManager->getRepository(User::class)->findStudentHistoryData($id, $schoolId);
        $studentHistory = $validateService->groupArrayByKey($studentHistory, 'full_name');

        /** @var ArrayCollection $studentAnalytics */
        $studentAnalytics = $entityManager->getRepository(PassedQuestion::class)->findStudentAnalyticsData($id, $schoolId);
        $studentAnalytics = $validateService->groupArrayByKey($studentAnalytics, 'groups');

        return $this->json(['history' => $studentHistory, 'analytics' => $studentAnalytics], JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to get student dashboard data
     *
     * @Route("/api/private/v1/student/dashboard/{id}/{schoolId}", methods={"GET"}, requirements={"id" : "\d+"}, name="ministry_student_dashboard")
     *
     * @param ChallengeTestService $challengeTestService
     * @param EmailService $emailService
     * @param int $id
     * @param int|null $schoolId
     * @return JsonResponse
     * @throws
     */
    public function getStudentDashboardAction
    (ChallengeTestService $challengeTestService,
     EmailService $emailService,
     $id,
     $schoolId = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();
        $challengeTestService->updateChallengeTestState($entityManager, $emailService, $id);

        /** @var ArrayCollection $studentTests */
        $studentTests = $entityManager->getRepository(AssignTest::class)->findStudentTestData($id, $schoolId);

        /** @var ArrayCollection $studentsInClass */
        $studentsInClass = $entityManager->getRepository(StudentClass::class)->findStudentsInClassData($id, $schoolId);
        $studentTests['students'] = $studentsInClass;

        return $this->json($studentTests, JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to auto assign tests to student
     *
     * @param User $student
     * @param EntityManager $entityManager
     * @throws ORMException
     */
    private function autoAssignTestsToStudent(User $student, EntityManager $entityManager): void
    {
        /** @var StudentClass $class */
        $class = $student->getClass();

        if (\is_object($class)) {

            /** @var ArrayCollection $assignTests */
            $assignTests = $entityManager->getRepository(AssignTest::class)->getTesByClassId($class->getId());

            /** @var AssignTest $assignTest */
            foreach ($assignTests as $assignTest) {

                // clone exist assign test
                $newAssignTest = clone $assignTest;

                // set default values and assign to student
                $newAssignTest->setStatus(AssignTest::ASSIGN);
                $newAssignTest->setScore(0);
                $newAssignTest->setStudent($student);
                $entityManager->persist($newAssignTest);
            }
        }
    }

    /**
     * This function is used to get all students without current
     *
     * @Route("/api/private/v1/get/students/{currentUserId}", methods={"GET"}, name="ministry_get_students")
     *
     * @param int|null $currentUserId
     * @return JsonResponse
     * @throws
     */
    public function getStudentsDataAction(int $currentUserId): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $students */
        $students = $entityManager->getRepository(User::class)->findStudentsWithoutLogged($currentUserId);

        return $this->json($students, JsonResponse::HTTP_OK);
    }
}