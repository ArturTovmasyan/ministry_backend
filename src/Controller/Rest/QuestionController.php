<?php

namespace App\Controller\Rest;

use App\Controller\Exception\Exception;
use App\Entity\Question;
use App\Form\QuestionType;
use App\Services\ValidateService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class QuestionController
 * @package AppBundle\Controller\Rest
 */
class QuestionController extends AbstractController
{
    /**
     * This function is used to create question for test
     *
     * @Route("/api/private/v1/question/create", methods={"POST"}, name="ministry_manage_question")
     * @Route("/api/private/v1/question/edit/{id}", requirements={"id" : "\d+"}, methods={"PUT"}, name="ministry_question_edit")
     *
     * @param int $id
     * @param Request $request
     * @param ValidateService $validateService
     * @param ContainerInterface $container
     * @return JsonResponse | null
     * @throws
     */
    public function manageQuestionAction(
        Request $request,
        ValidateService $validateService,
        ContainerInterface $container,
        $id = null
    ): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            $entityManager->getConnection()->beginTransaction();

            // check id edit action
            if ($id) {
                $question = $entityManager->getRepository(Question::class)->find($id);
            } else {
                $question = new Question();
            }

            // create FORM for handle all data with errors
            $form = $this->createForm(QuestionType::class, $question, [
                'method' => $request->getMethod(),
                'entity_manager' => $entityManager
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $entityManager->persist($question);
                $entityManager->flush($question);
                $entityManager->getConnection()->commit();

                // get base64 string from request
                $base64String = $request->request->get('question')['file'] ?? null;

                // remove file from AWS S3
                if ($question->getFileName() && \array_key_exists('file', $request->request->get('question'))) {
                    $awsKey[] = Question::getUploadDir().$question->getFileName();

                    // remove file from AWS S3 async.
                    $removeFileProducer = $container->get('old_sound_rabbit_mq.remove_file_producer');
                    $removeFileProducer ? $removeFileProducer->publish(json_encode($awsKey)) : null;
                }

                if ($base64String) {

                    // Create and save file on AWS S3
                    $fileName = 'question_' . $question->getId() . '_' . time();
                    $fileInfo = explode(';', $base64String);
                    $extension = explode('/', $fileInfo[0]);
                    $extension = $extension[1];

                    $fullName = $fileName.'.'.$extension;
                    $awsKey = Question::getUploadDir().$fullName;

                    $question->setFileName($fullName);
                    $entityManager->persist($question);

                    // generate upload file data for RabbitMQ
                    $data = [
                        'awsKey' => $awsKey,
                        'base64' => $base64String
                    ];

                    $uploadFileProducer = $container->get('old_sound_rabbit_mq.upload_file_producer');
                    $uploadFileProducer ? $uploadFileProducer->publish(json_encode($data)) : null;
                }

                $entityManager->flush($question);
            } else {
                $errors = $form->getErrors(true, true);
                $validateService->checkFormErrors($errors);
            }

        } catch (Exception $e) {
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json(['status' => JsonResponse::HTTP_CREATED], JsonResponse::HTTP_CREATED);
    }

    /**
     * This function is used to get Questions
     *
     * @Route("/api/private/v1/questions", methods={"POST"}, name="ministry_get_questions")
     *
     * @param Request $request
     * @return JsonResponse
     * @throws
     */
    public function getQuestionsAction(Request $request): JsonResponse
    {
        // get filter ids data from request
        $filterIds = $request->get('filterIds');
        $categoryIds = $request->get('categoryIds', []);
        $page = $request->get('page');
        $limit = $request->get('limit');

        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $questions */
        $questions = $entityManager->getRepository(Question::class)->findQuestions($limit, $filterIds, $categoryIds, $page);

        return $this->json($questions, JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to get Questions
     *
     * @Route("/api/private/v1/delete/question/{id}", methods={"DELETE"}, name="ministry_delete_question")
     * @ParamConverter("question", class="App\Entity\Question")
     *
     * @param Question $question
     *
     * @return JsonResponse
     * @throws
     */
    public function removeQuestionAction(Question $question): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        $entityManager->getConnection()->beginTransaction();
        $fullPath = null;

        if ($question->getFileName()) {
            $fullPath = $question->getWebPath();
        }

        $entityManager->remove($question);
        $entityManager->flush();
        $entityManager->getConnection()->commit();

        $fullPath ? unlink($fullPath) : null;

        return $this->json('', JsonResponse::HTTP_NO_CONTENT);
    }
}