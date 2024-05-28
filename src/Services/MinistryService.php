<?php

namespace App\Services;

use App\Entity\AssignTest;
use App\Entity\ChallengeTest;
use App\Entity\ChallengeTestHistory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;

/**
 * Class MinistryService
 * @package App\Services
 */
class MinistryService
{
    /**
     * This function is used to generate test data response body
     *
     * @param array $data
     * @return array
     */
    public function generateTestDataResponse(array &$data):array
    {
        // get questions data
        $questions = reset($data)['questions'];

        foreach ($questions as $key => $question) {

            $categories = [];
            $filters = $question['filters'];

            foreach ($filters as $filter) {

                $category = $filter['category'];
                $categories[] = [
                    'id' => $category['category_id'],
                    'name' => $category['category_name'],
                    'filter_name' => $filter['name']
                ];
            }

            unset($data[0]['questions'][$key]['filters']);
            $data[0]['questions'][$key]['category'] = $categories;
        }

        return $data;
    }

    /**
     * This function is used to for create Assign test object
     *
     * @param $test
     * @param $student
     * @param null $status
     * @return AssignTest
     * @throws \Exception
     */
    public function createAssignTest($test, $student, $status = null):AssignTest
    {
        $assignTest = new AssignTest();
        $assignTest->setDeadline(new \DateTime());
        $assignTest->setTest($test);
        $assignTest->setType(AssignTest::CHALLENGED);
        $assignTest->setStudent($student);
        $assignTest->setExpectation(70);
        $assignTest->setTimeLimit(90);

        if ($status) {
            $assignTest->setStatus($status);
        }

        return $assignTest;
    }

    /**
     * This function is used to generate test bank response data body
     *
     * @param $data
     * @return array
     */
    public function generateTestBankResponse($data): array
    {
        $response = json_decode($data, true);

        foreach ($response as $key => $question) {

            $categoryData = [];
            $categories = $question['questions'] ?? [];

            foreach ($categories as $c => $category) {

                // get each question filters
                $filters = $category['filters'];

                foreach ($filters as $f => $filter) {

                    // get category name
                    $i = 1;
                    $categoryName = $filter['category']['category_name'];
                    $filterName = $filter['name'];

                    if (array_key_exists($categoryName, $categoryData)) {

                        if (array_key_exists($filterName, $categoryData[$categoryName]['filters'])) {
                            $existCount = $categoryData[$categoryName]['filters'][$filterName]['count'];

                            // sum existing filter count by each category
                            if ($existCount > 0) {
                                $i = ++$existCount;
                            } else {
                                ++$i;
                            }

                            $categoryData[$categoryName]['filters'][$filterName]['count'] = $i;
                        } else {
                            $filtersData = ['name' => $filterName, 'count' => $i];
                            $categoryData[$categoryName]['filters'][$filterName] = $filtersData;
                        }

                    } else {

                        // add category and filter name in array as key for group by
                        $filtersData = ['name' => $filterName, 'count' => $i];
                        $categoryData[$categoryName] = [
                            'name' => $categoryName,
                            'filters' => [$filterName => $filtersData]
                        ];
                    }
                }

                // check if answer exist in array
                if (array_key_exists('answer', $category)) {
                    unset($response[$key]['questions'][$c]['filters']);
                }
            }

            // remove category and filter name in array as key case
            $categoryData = array_values($categoryData);

            foreach ($categoryData as $k => $newData) {
                $newFilter = array_values($newData['filters']);
                $categoryData[$k]['filters'] = $newFilter;
            }

            unset($response[$key]['questions']);
            $response[$key]['categories'] = $categoryData;
        }

        return $response;
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
            $studentHistory = new ChallengeTestHistory();
            $studentHistory->setStudent($challengeTest->getStudent()->getId());
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
            $competitorHistory->setFullName($challengeTest->getCompetitor()->getFullName());
            $competitorHistory->setScore($challengeTest->getCompetitorScore());
            $competitorHistory->setCreatedAt($challengeTest->getCreatedAt());
            $competitorHistory->setUpdatedAt($challengeTest->getUpdatedAt());
            $entityManager->persist($competitorHistory);
        }
    }
}