<?php

namespace App\Controller\Rest;

use App\Controller\Exception\Exception;
use App\Entity\AssignTest;
use App\Entity\Question;
use App\Entity\Test;
use App\Entity\User;
use App\Form\TestType;
use App\Services\MinistryService;
use App\Services\ValidateService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TestController
 * @package App\Controller\Rest
 */
class TestController extends AbstractController
{
    /**
     * This function is used to create ministry tests by Instructor
     *
     * @Route("/api/private/v1/test/create", methods={"POST"}, name="ministry_test_create")
     * @Route("/api/private/v1/test/edit/{id}", methods={"PUT"}, requirements={"id" : "\d+"}, name="ministry_test_edit")
     *
     * @param int $id
     * @param Request $request
     * @param ValidateService $validateService
     * @return JsonResponse
     *
     * @throws
     */
    public function createTestAction(Request $request, ValidateService $validateService, $id = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            // start DB transaction
            $entityManager->getConnection()->beginTransaction();

            // check id edit action
            if ($id) {
                $test = $entityManager->getRepository(Test::class)->find($id);
            } else {
                $test = new Test();
            }

            // create FORM for handle all data with errors
            $form = $this->createForm(TestType::class, $test, ['method' => $request->getMethod()]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->persist($test);
                $entityManager->flush();
                $entityManager->getConnection()->commit();
            } else {

                // get error by form handler
                $errors = $form->getErrors(true, true);
                $validateService->checkFormErrors($errors);
            }

        } catch (Exception $e) {

            // roll back query changes in DB
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json(['status' => JsonResponse::HTTP_CREATED], JsonResponse::HTTP_CREATED);
    }

    /**
     * This function is used to get Test Calculator data
     *
     * @Route("/api/private/v1/assigned-test/{studentId}/{assignTestId}", requirements={"studentId" : "\d+"}, methods={"GET"}, name="ministry_get_assigned_test")
     *
     * @param int $studentId
     * @param int|null $assignTestId
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function getAssignedTestAction(int $studentId, $assignTestId = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $assignTests */
        $assignTests = $entityManager->getRepository(AssignTest::class)->findAssignTestData($studentId, $assignTestId);

        return $this->json($assignTests, JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to get Test Calculator data
     *
     * @Route("/api/private/v1/test/bank", methods={"POST"}, name="ministry_test_bank_get")
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param MinistryService $ministryService
     * @throws
     *
     * @return JsonResponse
     */
    public function getTestBankAction(Request $request, SerializerInterface $serializer, MinistryService $ministryService): JsonResponse
    {
        // get request data
        $userId = $request->get('userId');

        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $testBanks */
        $testBanks = $entityManager->getRepository(Test::class)->getTestBankData($userId);
        $count = $testBanks['count'];
        $testBankData = $testBanks['data'];

        $testBanks = $serializer->serialize($testBankData, 'json', SerializationContext::create()->setGroups(['test_bank']));
        $testBanks = $ministryService->generateTestBankResponse($testBanks);

        $testData['test_count'] = $count;
        $testData['data'] = $testBanks;
        $testData = json_encode($testData);

        $response = new JsonResponse();
        $response->setContent($testData);

        return $response;
    }

    /**
     * This function is used to get Test data by id
     *
     * @Route("/api/private/v1/test/{id}/{schoolId}", requirements={"id" : "\d+"}, methods={"GET"}, name="ministry_test_data")
     *
     * @param int $id
     * @param int $schoolId
     * @param SerializerInterface $serializer
     * @param MinistryService $ministryService
     * @return JsonResponse
     * @throws
     */
    public function getTestDataAction(SerializerInterface $serializer, MinistryService $ministryService, int $id, $schoolId = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var Test $test */
        $test = $entityManager->getRepository(Test::class)->getTesById($id, $schoolId);
        $serializeTest = $serializer->serialize($test, 'json', SerializationContext::create()->setGroups(['test']));
        $questionData = json_decode($serializeTest, true);

        // generate test data response body
        $testData = $ministryService->generateTestDataResponse($questionData);
        $data = json_encode($testData);

        $response = new JsonResponse();
        $response->setContent($data);

        return $response;
    }

    /**
     * This function is used to get Test data by id
     *
     * @Route("/api/private/v1/test/data/{assignTestId}", requirements={"assignTestId" : "\d+"}, methods={"GET"}, name="ministry_test_data_by_assign_id")
     *
     * @param int $assignTestId
     * @param SerializerInterface $serializer
     * @return JsonResponse
     * @throws
     */
    public function getTestDataByAssignIdAction($assignTestId, SerializerInterface $serializer): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var Test $test */
        $test = $entityManager->getRepository(Test::class)->getTesByAssignId($assignTestId);

        // Generate json response data by JMS Serializer group method
        $serializeTest = $serializer->serialize($test, 'json', SerializationContext::create()->setGroups(['test_by_assign_id']));

        $response = new JsonResponse();
        $response->setContent($serializeTest);

        return $response;
    }

    /**
     * This function is used to get Test data by id
     *
     * @Route("/api/private/v1/test/archive/{testId}/{isArchive}", requirements={"testId" : "\d+", "isArchive" : "\d+"}, methods={"GET"}, name="ministry_test_archive")
     *
     * @param int $testId
     * @param int $isArchive
     * @param ValidateService $validateService
     *
     * @return JsonResponse
     * @throws
     */
    public function archiveTestByIdAction(int $testId, int $isArchive, ValidateService $validateService): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        // disable archive filter
        $entityManager->getFilters()->disable('publish_filter');

        /** @var Test $test */
        $test = $entityManager->getRepository(Test::class)->find($testId);

        if (!$test) {
            return $this->json(['status' => JsonResponse::HTTP_NOT_FOUND, 'message' => "Test by id=$testId not found or archived"], JsonResponse::HTTP_NOT_FOUND);
        }

        // archive test
        $test->setArchived($isArchive);
        $entityManager->persist($test);
        $validateService->checkValidation($test);
        $entityManager->flush();

        return $this->json([], JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to create student custom tests
     *
     * @Route("/api/private/v1/custom-test/create", methods={"POST"}, name="ministry_custom_test_create")
     *
     * @param Request $request
     * @param ValidateService $validateService
     * @return JsonResponse
     *
     * @throws
     */
    public function createCustomTestAction(Request $request, ValidateService $validateService): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            // start DB transaction
            $entityManager->getConnection()->beginTransaction();
            $requestData = $request->request->all();

            // set limit for question
            if ($requestData['question_count'] > 200 || $requestData['question_count'] < 10) {
                return $this->json(['status' => JsonResponse::HTTP_NOT_FOUND, 'message' => 'Questions count must be MIN 10 and MAX 200.'], JsonResponse::HTTP_NOT_FOUND);
            }

            /** @var array $questionIds */
            $questionIds = $entityManager->getRepository(Question::class)->findQuestionIdsByCategory($requestData['filter_ids'], $requestData['question_count']);

            if (\count($questionIds) === 0) {
                return $this->json(['status' => JsonResponse::HTTP_NOT_FOUND, 'message' => 'Questions Not Found'], JsonResponse::HTTP_NOT_FOUND);
            }

            $questionIds = array_map(static function($item) {
                return $item['id'];
            }, $questionIds);

            $requestData['test']['question'] = $questionIds;
            $request->request->add(['test' => $requestData['test']]);

            $test = new Test();

            // create FORM for handle all data with errors
            $form = $this->createForm(TestType::class, $test, ['method' => $request->getMethod()]);
            $form->handleRequest($request);

            $data['status'] = JsonResponse::HTTP_CREATED;

            // check if data is submitted and valid
            if ($form->isSubmitted() && $form->isValid()) {

                /** @var User $student */
                $student = $entityManager->getRepository(User::class)->find($requestData['student_id']);
                $entityManager->persist($test);

                $assignTest = new AssignTest();
                $assignTest->setTest($test);
                $assignTest->setStudent($student);
                $assignTest->setStatus(AssignTest::ASSIGN);
                $assignTest->setDeadline(new \DateTime());
                $assignTest->setExpectation(70);
                $assignTest->setTimeLimit(90);
                $entityManager->persist($assignTest);

                // check validation for user model
                $validateService->checkValidation($assignTest);

                $entityManager->flush();
                $entityManager->getConnection()->commit();
                $data['assign_test_id'] = $assignTest->getId();

            } else {
                $errors = $form->getErrors(true, true);
                $validateService->checkFormErrors($errors);
            }

        } catch (Exception $e) {

            // roll back query changes in DB
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json($data, JsonResponse::HTTP_CREATED);
    }
}
