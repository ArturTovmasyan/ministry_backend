<?php

/**
 * Created by PhpStorm.
 * User: arthurt
 * Date: 20/1/19
 * Time: 9:45 PM
 */
namespace App\Controller\Rest;

use App\Controller\Exception\Exception;
use App\Entity\Answer;
use App\Entity\AssignTest;
use App\Entity\ChallengeTest;
use App\Entity\PassedQuestion;
use App\Entity\Question;
use App\Entity\Test;
use App\Entity\User;
use App\Services\ChallengeTestService;
use App\Services\EmailService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PassTestController
 * @package App\Controller\Rest
 */
class PassTestController extends AbstractController
{
    /**
     * This function is used to pass test by student
     *
     * @Route("/api/private/v1/student/pass-test", methods={"POST"}, name="ministry_student_pass_test")
     *
     * @param Request $request
     * @param ChallengeTestService $challengeTestService
     * @param EmailService $emailService
     *
     * @return JsonResponse
     * @throws
     */
    public function passTestAction(Request $request, ChallengeTestService $challengeTestService, EmailService $emailService): JsonResponse
    {
        // get passed test data from request
        $assignTestId = $request->get('assignTest');
        $questionId = $request->get('question');
        $answerId = $request->get('answer'); // optional
        $marked = (bool)$request->get('marked'); // optional

        // add validation for pass test
        if (!$marked && !$answerId) {
            return $this->json(['message' => 'You must select one answer or mark it as later', 'status' => JsonResponse::HTTP_BAD_REQUEST], JsonResponse::HTTP_BAD_REQUEST);
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            // start DB transaction
            $entityManager->getConnection()->beginTransaction();

            /** @var AssignTest $assignTest */
            $assignTest = $entityManager->getRepository(AssignTest::class)->find($assignTestId);

            if (!\is_object($assignTest)) {
                return $this->json(['message' => "Assigned test by id=$assignTestId not found", 'status' => JsonResponse::HTTP_NOT_FOUND], JsonResponse::HTTP_NOT_FOUND);
            }

            // get test status
            $testStatus = $assignTest->getStatus();

            // check if test already finished
            if ($testStatus === AssignTest::COMPLETED) {
                return $this->json(['message' => 'This test already finished', 'status' => JsonResponse::HTTP_OK], JsonResponse::HTTP_OK);
            }

            $questionsFinish = false;
            $isRightAnswer = 0;

            if ($answerId) {
                // get and check answer is right or not
                $isRightAnswer = $entityManager->getRepository(Answer::class)->findRightAnswerById($questionId, $answerId);
            }

            $allQuestionsCount = 0;
            $existQuestionIds = [];

            // manage assign test data
            $this->manageAssignTest($allQuestionsCount, $existQuestionIds, $assignTest, $entityManager);

            // generate passed question array data
            $passedQuestionData = [
                'isRightAnswer' => $isRightAnswer,
                'answerId' => $answerId,
                'marked' => $marked,
                'questionId' => $questionId,
                'allQuestionCount' => $allQuestionsCount,
                'existQuestionsCount' => \count($existQuestionIds)
            ];

            // recalculate test score by passed question
            $calculated = $this->recalculateTestScore($passedQuestionData, $assignTest, $entityManager);

            // check if pass new question from test
            if (!$calculated && \count($existQuestionIds) > 0 && \in_array($questionId, $existQuestionIds, true)) {

                // create passed question data
                $this->createPassedQuestion($passedQuestionData, $assignTest, $entityManager);

                // calculate test score for student
                $this->sumScore($assignTest, $allQuestionsCount, $isRightAnswer);
            }

            // check if all test questions is passed
            if ($passedQuestionData['existQuestionsCount'] === 0) {
                $questionsFinish = true;
            }

            // send DB transaction
            $entityManager->persist($assignTest);
            $entityManager->flush();
            $entityManager->getConnection()->commit();

            /** @var ChallengeTest $challengeTest */
            $challengeTest = $assignTest->getChallengeTest();

            // check and finish challenge test
            if ($challengeTest) {
                $finishDate = $challengeTest->getCreatedAt();
                $currentDate = new \DateTime();

                // check if challenged test time is finished (24 hour)
                if ($finishDate->add(new \DateInterval('P1D')) < $currentDate) {

                    // finish challenge test and sent email for students about win and lose
                    $challengeTestService->finishChallengeTestAndSendEmail($challengeTest, $entityManager, $emailService);
                    return $this->json(['message' => 'Test time is limited (24 hour) and was be automatically finished.', 'status' => JsonResponse::HTTP_OK], JsonResponse::HTTP_OK);
                }
            }

        } catch (Exception $e) {
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json(['test_questions_finish' => $questionsFinish, 'status' => JsonResponse::HTTP_OK], JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to return passed, marked question by pagination
     *
     * @Route("/api/private/v1/test/passed/questions", methods={"POST"}, name="ministry_passed_questions")
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     *
     * @return JsonResponse
     * @throws
     */
    public function getPassedQuestionsAction(Request $request, SerializerInterface $serializer): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            // get request data
            $assignTestId = $request->request->get('assignTestId');
            $studentId = $request->request->get('studentId');
            $type = $request->request->get('type');

            // check if type param is invalid
            if (!\in_array((int)$type, [PassedQuestion::MARKED, PassedQuestion::ALL], true)) {
                return $this->json(['message' => 'Type param must be 0 or 1', 'status' => JsonResponse::HTTP_BAD_REQUEST], JsonResponse::HTTP_BAD_REQUEST);
            }

            $page = $request->request->get('page');

            /** @var ArrayCollection $question */
            $question = $entityManager->getRepository(Question::class)->findPassedQuestionData($assignTestId, $studentId, (int)$type, $page);

            /** @var Question $currentQuestion */
            $currentQuestion = $question['questions']['question'] ?? [];
            $questionId = $currentQuestion ? $currentQuestion->getId() : null;

            if ($questionId) {
                $test = $currentQuestion->getTest() ? $currentQuestion->getTest()->first() : null;
                $testQuestionIds = $test->getQuestionIds();
                $key = \array_search($questionId, $testQuestionIds, true);

                if ($key !== false) {
                    ++$key;
                }

                $question['question_number'] = $key;
            }

            $usersContent = $serializer->serialize($question, 'json', SerializationContext::create()->setGroups(['test']));

            $response = new JsonResponse();
            $response->setContent($usersContent);
        } catch (Exception $e) {
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $response;
    }

    /**
     * This function is used to return finished questions list
     *
     * @Route("/api/private/v1/finished/questions/{assignTestId}/{studentId}",
     *      requirements={
     *            "assignTestId":"\d+",
     *            "studentId":"\d+"
     *       },
     *      methods={"GET"},
     *      name="ministry_get_finished_questions")
     *
     * @param int $assignTestId
     * @param int $studentId
     * @param SerializerInterface $serializer
     *
     * @return JsonResponse
     * @throws
     */
    public function getFinishedQuestionsAction(int $assignTestId, int $studentId, SerializerInterface $serializer): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            $finishedQuestions = [];

            /** @var ArrayCollection $questions */
            $questions = $entityManager->getRepository(Question::class)->findPassedQuestionData($assignTestId, $studentId, PassedQuestion::ALL_FINISHED);

            /** @var AssignTest $assignTest */
            $assignTest = $entityManager->getRepository(AssignTest::class)->find($assignTestId);

            /** @var Test $test */
            $test = $assignTest ? $assignTest->getTest() : null;
            $testName = $test->getName();
            $finishedQuestions['test_name'] = $testName;

            /** @var ArrayCollection $correctAnswerCount */
            $correctAnswerCount = $entityManager->getRepository(PassedQuestion::class)
                ->findCorrectAnswersCount($assignTestId, $studentId);

            /**
             * @var  $k
             * @var Question $question
             */
            foreach ($questions['questions'] as $k => $question) {

                /** @var Question $questionObject */
                $questionObject = $question['question'];
                $questionId = $questionObject ? $questionObject->getId() : null;

                if ($questionId) {

                    // get all question id from test
                    $testQuestionIds = $test->getQuestionIds();
                    $key = \array_search($questionId, $testQuestionIds, true);

                    if ($key !== false) {
                        ++$key;
                    }

                    $question['question_number'] = $key;
                    $finishedQuestions['questions'][] = $question;
                }
            }

            $finishedQuestions['all_count'] = $questions['all_count'];
            $finishedQuestions['correct_answer_count'] = $correctAnswerCount;
            $usersContent = $serializer->serialize($finishedQuestions, 'json', SerializationContext::create()->setGroups(['pass-test']));

            $response = new JsonResponse();
            $response->setContent($usersContent);
        } catch (Exception $e) {
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $response;
    }

    /**
     * This function is used to get last passed question for continue test
     *
     * @Route("/api/private/v1/last/passed/question/{assignTestId}/{studentId}", requirements={"assignTestId" : "\d+", "studentId" : "\d+"},
     * methods={"GET"}, name="ministry_last_passed_question")
     *
     * @param $assignTestId
     * @param $studentId
     *
     * @return JsonResponse
     * @throws
     */
    public function getLastPassedQuestionAction($assignTestId, $studentId): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            /** @var PassedQuestion $passedQuestion */
            $passedQuestion = $entityManager->getRepository(PassedQuestion::class)->findLastPassedQuestionData($assignTestId, $studentId);
        } catch (Exception $e) {
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json($passedQuestion, JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to finish test
     *
     * @Route("/api/private/v1/finish/test/{assignTestId}", requirements={"assignTestId" : "\d+"}, methods={"GET"}, name="ministry_finish_test")
     * @param int $assignTestId
     * @param ChallengeTestService $challengeTestService
     * @param EmailService $emailService
     * @return JsonResponse
     * @throws
     */
    public function finishTestAction(int $assignTestId, ChallengeTestService $challengeTestService, EmailService $emailService): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var AssignTest $assignTest */
        $assignTest = $entityManager->getRepository(AssignTest::class)->find($assignTestId);

        try {
            $testData = [];

            $assignTest->setStatus(AssignTest::COMPLETED);
            $entityManager->persist($assignTest);
            $entityManager->flush($assignTest);

            /** @var User $student */
            $student = $assignTest->getStudent();

            /** @var ChallengeTest $challengeTest */
            $challengeTest = $assignTest->getChallengeTest();

            if ($student) {
                $testData = $entityManager->getRepository(AssignTest::class)->findAssignTestData($student->getId(), $assignTest->getId());
            }

            // check if test is challenged
            if ($challengeTest) {

                // check if competitor is finish
                $competitorIsFinished = $challengeTestService->checkIfCompetitorIsFinished($challengeTest);

                // check if competitor is finished also
                if ($challengeTest->getType() === ChallengeTest::FINISHED) {
                    $testData['challenge_status'] = 'Challenge test already has been auto finished!';
                    $testData = array_merge($testData, $challengeTest->getPlayers());
                } elseif ($competitorIsFinished) {
                    $challengeTestService->finishChallengeTestAndSendEmail($challengeTest, $entityManager, $emailService);
                    $testData = array_merge($testData, $challengeTest->getPlayers());
                    $testData['challenge_status'] = 'Finished';
                } else {
                    $testData['challenge_status'] = 'Waiting your competitor to finish the challenge.';
                }
            }

        } catch (Exception $e) {
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json($testData, JsonResponse::HTTP_OK);
    }

    /**
     * @param array $data
     * @param AssignTest $assignTest
     * @param EntityManager $entityManager
     * @throws ORMException
     */
    private function createPassedQuestion(array &$data, AssignTest $assignTest, EntityManager $entityManager): void
    {
        /** @var Question $question */
        $question = $entityManager->getRepository(Question::class)->find($data['questionId']);

        /** @var User $student */
        $student = $assignTest->getStudent();

        // save passed question data
        $passedQuestion = new PassedQuestion();
        $passedQuestion->setStudent($student);
        $passedQuestion->setQuestion($question);
        $passedQuestion->setScore($data['isRightAnswer']);
        $passedQuestion->setMarked($data['marked']);
        $passedQuestion->setAssignTest($assignTest);

        if ($data['answerId'] > 0) {
            $passedQuestion->setAnswer($data['answerId']);
        }

        // check if last question is passed reset count
        if ($data['existQuestionsCount'] === 1) {
            $data = [];
            $data['existQuestionsCount'] = 0;
        }

        $entityManager->persist($passedQuestion);
    }

    /**
     * @param $allQuestionsCount
     * @param $existQuestionIds
     * @param AssignTest $assignTest
     * @param EntityManager $entityManager
     */
    private function manageAssignTest(&$allQuestionsCount, &$existQuestionIds, AssignTest $assignTest, EntityManager $entityManager): void
    {
        // get all passed questions for current test
        $passedQuestionIds = $entityManager->getRepository(PassedQuestion::class)->findCompletedQuestionIds($assignTest);

        if (\count($passedQuestionIds) === 0) {
            $assignTest->setStatus(AssignTest::STARTED);
        }

        // get questions count and ids
        $allQuestionIds = $assignTest->getTest() ? $assignTest->getTest()->getQuestionIds() : null;
        $allQuestionsCount = \count($allQuestionIds);
        $existQuestionIds = array_diff($allQuestionIds, $passedQuestionIds);
    }

    /**
     * This function is used to recalculate test score
     *
     * @param array $data
     * @param AssignTest $assignTest
     * @param EntityManager $entityManager
     * @return boolean
     * @throws ORMException
     */
    private function recalculateTestScore(array $data, AssignTest $assignTest, EntityManager $entityManager): bool
    {
        /** @var PassedQuestion $passedQuestion */
        $passedQuestion = $entityManager->getRepository(PassedQuestion::class)->findOneBy(['assignTest' => $assignTest->getId(), 'question' => $data['questionId']]);

        // check if test question was be edited
        if (\is_object($passedQuestion)) {

            // get exist score for passed question
            $passedScore = $passedQuestion->getScore();
            $currentScore = $assignTest->getScore();

            // check and change exist score
            if (!$passedScore && $data['isRightAnswer']) {
                // sum score
                $currentScore += 100 / $data['allQuestionCount'];
                $currentScore = ceil($currentScore) > 100 ? 100 : ceil($currentScore);
            } elseif ($passedScore && !$data['isRightAnswer']) {
                // minus score
                $currentScore -= 100 / $data['allQuestionCount'];
                $currentScore = floor($currentScore) > 100 ? 100 : floor($currentScore);
            }

            // change score values
            $assignTest->setScore($currentScore);
            $passedQuestion->setScore($data['isRightAnswer']);
            $passedQuestion->setMarked($data['marked']);

            if ($data['answerId'] > 0) {
                $passedQuestion->setAnswer($data['answerId']);
            } else {
                $passedQuestion->setAnswer(0);
            }

            $entityManager->persist($passedQuestion);
            return true;
        }

        return false;
    }

    /**
     * Sum score for student
     *
     * @param AssignTest $assignTest
     * @param $allQuestionsCount
     * @param $isRightAnswer
     */
    private function sumScore(
        $assignTest,
        $allQuestionsCount,
        $isRightAnswer
    ): void
    {
        if ($isRightAnswer) {
            // sum score for test
            $currentScore = $assignTest->getScore();
            $score = (100 / $allQuestionsCount) + $currentScore;
            $ceilScore = ceil($score) > 100 ? 100 : ceil($score);
            $assignTest->setScore($ceilScore);
        }
    }
}
