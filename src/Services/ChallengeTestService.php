<?php

namespace App\Services;

use App\Controller\Exception\Exception;
use App\Entity\AssignTest;
use App\Entity\ChallengeTest;
use App\Entity\ChallengeTestHistory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ChallengeTestService
 * @package App\Services
 */
class ChallengeTestService
{
    /**
     * This function is used to run challenge test functionality
     *
     * @param ChallengeTest $challengeTest
     * @param EntityManager $entityManager
     * @param EmailService $emailService
     * @throws Exception
     * @throws ConnectionException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function finishChallengeTestAndSendEmail(ChallengeTest $challengeTest, EntityManager $entityManager, EmailService $emailService):void
    {
        try {
            //set default values
            $studentAssignTest = null;
            $competitorAssignTest = null;
            $competitorScore = 0;
            $studentScore = 0;

            $entityManager->getConnection()->beginTransaction();

            /** @var ArrayCollection $assignTests */
            $assignTests = $challengeTest->getAssignTests();

            /** @var AssignTest $assignTest */
            foreach ($assignTests as $assignTest) { // finish assigned tests

                $competitorId = $challengeTest->getCompetitor() ? $challengeTest->getCompetitor()->getId() : null;

                if ($assignTest->isCompetitorTest($competitorId)) {
                    $competitorAssignTest = $assignTest;
                } else {
                    $studentAssignTest = $assignTest;
                }

                $assignTest->setStatus(AssignTest::COMPLETED);
                $entityManager->persist($assignTest);
            }

            // sum score for each student in challenge test
            $this->sumChallengeTestScore($competitorAssignTest, $studentAssignTest, $competitorScore, $studentScore);

            // save score for each student
            $challengeTest->setCompetitorScore($competitorScore);
            $challengeTest->setStudentScore($studentScore);
            $challengeTest->setType(ChallengeTest::FINISHED);
            $entityManager->persist($challengeTest);

            // create challenge test history for each student
            $this->createChallengeTestHistoryForEachStudent($challengeTest, $entityManager);

            $entityManager->flush();
            $entityManager->getConnection()->commit();

            // send email for challenge test students about win and lose
            $emailService->sendEmailForChallengeStudents($challengeTest);

        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param ChallengeTest $challengeTest
     * @return boolean
     */
    public function checkIfCompetitorIsFinished(ChallengeTest $challengeTest): bool
    {
        $finished = true;

        /** @var ArrayCollection $assignTests */
        $assignTests = $challengeTest->getAssignTests();

        /** @var AssignTest $assignTest */
        foreach ($assignTests as $assignTest) {

            if ($assignTest->getStatus() !== AssignTest::COMPLETED) {
                $finished = false;
                break;
            }
        }

        return $finished && \count($assignTests) > 0;
    }

    /**
     * @param AssignTest $competitorAssignTest
     * @param AssignTest $studentAssignTest
     * @param int $competitorScore
     * @param int $studentScore
     */
    private function sumChallengeTestScore(
        AssignTest $competitorAssignTest,
        AssignTest $studentAssignTest,
        &$competitorScore,
        &$studentScore): void
    {
        // calculate score for challenge test
        switch ($studentAssignTest && $competitorAssignTest) {
            case $competitorAssignTest->getScore() === $studentAssignTest->getScore():
                $competitorScore = 2;
                $studentScore = 2;
                break;
            case $competitorAssignTest->getScore() > $studentAssignTest->getScore():
                $competitorScore = 3;

                if ($studentAssignTest->getScore() > 75) {
                    $studentScore = 1;
                }
                break;
            case $competitorAssignTest->getScore() < $studentAssignTest->getScore():
                $studentScore = 3;

                if ($competitorAssignTest->getScore() > 75) {
                    $competitorScore = 1;
                }
                break;
            default:
                $competitorScore = 0;
                $studentScore = 0;
                break;
        }
    }

    /**
     * This function is used to create student challenge test history
     *
     * @param ChallengeTest $challengeTest
     * @param EntityManager $entityManager
     * @throws ORMException
     */
    private function createChallengeTestHistoryForEachStudent(ChallengeTest $challengeTest, EntityManager $entityManager):void
    {
        if ($challengeTest->getStudent()) {
            /** @var ChallengeTestHistory $studentHistory */
            $studentHistory = new ChallengeTestHistory();
            $studentHistory->setStudent($challengeTest->getStudent()->getId());
            $studentHistory->setCountry($challengeTest->getStudent()->getCountry());
            $studentHistory->setFullName($challengeTest->getStudent()->getFullName());
            $studentHistory->setScore($challengeTest->getStudentScore());
            $studentHistory->setCreatedAt($challengeTest->getCreatedAt());
            $studentHistory->setUpdatedAt($challengeTest->getUpdatedAt());
            $entityManager->persist($studentHistory);
        }

        if ($challengeTest->getCompetitor()) {
            /** @var ChallengeTestHistory $history */
            $competitorHistory = new ChallengeTestHistory();
            $competitorHistory->setStudent($challengeTest->getCompetitor()->getId());
            $competitorHistory->setCountry($challengeTest->getCompetitor()->getCountry());
            $competitorHistory->setFullName($challengeTest->getCompetitor()->getFullName());
            $competitorHistory->setScore($challengeTest->getCompetitorScore());
            $competitorHistory->setCreatedAt($challengeTest->getCreatedAt());
            $competitorHistory->setUpdatedAt($challengeTest->getUpdatedAt());
            $entityManager->persist($competitorHistory);
        }
    }

    /**
     * @param $studentId
     * @param EntityManager $entityManager
     * @param EmailService $emailService
     * @throws ConnectionException
     * @throws Exception
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateChallengeTestState(EntityManager $entityManager, EmailService $emailService, $studentId = null): void
    {
        /** @var ArrayCollection $challenges */
        $challenges = $entityManager->getRepository(ChallengeTest::class)->findNotFinishedChallenges($studentId);

        if (\count($challenges) > 0) {
            $currentDate = new \DateTime();

            /** @var ChallengeTest $challenge */
            foreach ($challenges as $challenge) {

                $finishDate = $challenge->getCreatedAt();

                // check if test challenged less then 24 hour
                if ($finishDate->add(new \DateInterval('P1D')) < $currentDate) {
                    // manage challenge test, sent email for students about win and lose
                    $this->finishChallengeTestAndSendEmail($challenge, $entityManager, $emailService);
                }
            }
        }
    }
}