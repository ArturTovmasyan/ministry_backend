<?php

namespace App\Repository;

use App\Entity\Answer;
use App\Entity\PassedQuestion;
use App\Entity\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class QuestionRepository
 * @package App\Repository
 */
class QuestionRepository extends ServiceEntityRepository
{
    /**
     * QuestionRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    /**
     * This function is used to get random question ids by category
     *
     * @param array $filterIds
     * @param int $count
     * @return array
     */
    public function findQuestionIdsByCategory(array $filterIds, int $count = 10):array
    {
        $qb = $this
            ->createQueryBuilder('q')
            ->select('q.id, RAND() as HIDDEN rand')
            ->join('q.filters', 'f')
            ->where('f.id IN (:ids)')
            ->setParameter('ids', $filterIds)
            ->groupBy('q.id')
            ->orderBy('rand')
            ->setFirstResult(0)
            ->setMaxResults($count);

        return $qb->getQuery()->getScalarResult();
    }

    /**
     * @return mixed
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findLast()
    {
        $qb = $this->createQueryBuilder('tc');
        $qb->setMaxResults(1);
        $qb->orderBy('tc.id', 'DESC');

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * This function is used to get all questions by selected fields
     *
     * @param $limit
     * @param null $filterIds
     * @param array $categoryIds
     * @param int $page
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findQuestions($limit, $filterIds = null, $categoryIds = [], $page = 1): array
    {
        gc_enable();
        $filterQuestions = [];
        $qb = $this->createQueryBuilder('q');

        if ($filterIds && \is_array($filterIds)) {

            // collect questions by filter ids with AND logic
            foreach ($filterIds as $filterId) {

                $queryBuilder = $this->createQueryBuilder('item')
                    ->join('item.filters', 'filters')
                    ->where('filters.id = :filterId')
                    ->setParameter('filterId', $filterId);

                $result = $queryBuilder->getQuery()->getResult();

                if (\count($result) > 0) {
                    $filterQuestions[$filterId] = array_map(static function (Question $question) {
                        return $question->getId();
                    }, $result);
                } else {
                    $filterQuestions[$filterId] = [];
                }

                $filterQuestions = array_values($filterQuestions);
                gc_collect_cycles();
            }

            if (\count($filterQuestions) > 0) {

                $values = $filterQuestions[0];
                $count = \count($filterQuestions);

                for ($i = 1; $i < $count; $i++) {
                    $values = array_intersect($values, $filterQuestions[$i]);
                }

                if (\count($values) > 0) {
                    $qb
                        ->where('q.id IN (:questionIds)')
                        ->setParameter('questionIds', $values);
                } else {
                    $qb->where('q.id IS NULL');
                }
            }
        }

        // clone query for get all count
        $cloneQuery = clone $qb;
        $cloneQuery->select('COUNT(q.id)');
        $allCount = (int)$cloneQuery->getQuery()->getSingleScalarResult();

        $qb
            ->select('q')
            ->groupBy('q.id')
            ->orderBy('q.id', 'DESC');

        if ($limit) {
            $qb
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);
        }

        $questions = $qb->getQuery()->getResult();

        return $this->generateQuestionsResponse($questions, $categoryIds, $allCount);
    }

    /**
     * This function is used to get preview, marked or all passed questions data
     *
     * @param $assignTestId
     * @param $studentId
     * @param $type
     * @param $page
     *
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function findPassedQuestionData($assignTestId, $studentId, $type, $page = 1)
    {
        $qb = $this->createQueryBuilder('q')
            ->join('q.passedQuestion', 'pq')
            ->join('pq.assignTest', 'at')
            ->join('pq.student', 'st')
            ->where('at.id = :assignTestId AND st.id = :studentId')
            ->orderBy('pq.id', 'DESC')
            ->setParameters([
                'assignTestId' => $assignTestId,
                'studentId' => $studentId
            ]);

        // get all questions count
        $cloneQuery = clone $qb;
        $cloneQuery->select('COUNT(q.id) AS all_count');
        $allCount = $cloneQuery->getQuery()->getResult();
        $allCount = (int)reset($allCount)['all_count'];

        $qb->select('q AS question, pq.answer AS answered_id, pq.marked');

        switch ($type) {
            case PassedQuestion::MARKED:
                $qb
                    ->addSelect('COUNT(q.id) AS count')
                    ->andWhere('pq.marked = :marked')->setParameter('marked', PassedQuestion::MARKED)
                    ->setFirstResult(($page - 1) * 1)->setMaxResults(1);

                $result = $qb->getQuery()->getOneOrNullResult();
                break;
            case PassedQuestion::ALL_FINISHED:
                $qb->andWhere('at.status = :status')->setParameter('status', PassedQuestion::ALL_FINISHED);
                $result = $qb->getQuery()->getResult();
                break;
            default:
                $qb->setFirstResult(($page - 1) * 1)->setMaxResults(1);
                $result = $qb->getQuery()->getOneOrNullResult();
                break;
        }

        $response['questions'] = $result;
        $response['all_count'] = $allCount;

        return $response;
    }

    /**
     * @param array $questions
     * @param array|null $categoryIds
     * @param int $allCount
     *
     * @return array
     */
    private function generateQuestionsResponse(array $questions, array $categoryIds, $allCount = 0): array
    {
        $data = ['all_count' => $allCount, 'count' => \count($questions)];

        /** @var Question $question */
        foreach ($questions as $question) {

            $categories = [];
            $answerData = [];

            $categories['question_id'] = $question->getId();
            $categories['question_name'] = $question->getName();
            $categories['number'] = $question->getNumber();
            $categories['explanation'] = $question->getExplanation();

            if ($question->getFileName()) {
                $categories['file_name'] = getenv('AWS_HOST') . $question->getWebPath();
            }

            $answers = $question->getAnswer();

            /** @var Answer $answer */
            foreach ($answers as $answer) {
                $answerData[] = ['id' => $answer->getId(), 'name' => $answer->getName(), 'isRight' => $answer->isRight()];
            }

            $categories['answers'] = $answerData;
            $filters = $question->getFilters();

            foreach ($filters as $filter) {

                $category = $filter->getCategory();

                if ($category && ((\count($categoryIds) > 0 && \in_array($category->getId(), $categoryIds, true)) ||
                        (\count($categoryIds) === 0))) {

                    $categories['category'][] = [
                        'id' => $category->getId(),
                        'name' => $category->getName(),
                        'filter' => ['id' => $filter->getId(), 'name' => $filter->getName()]
                    ];
                }
            }

            $data['questions'][] = $categories;
        }

        return $data;
    }
}
