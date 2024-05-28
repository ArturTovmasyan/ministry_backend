<?php

namespace App\Controller\Rest;

use App\Controller\Exception\Exception;
use App\Entity\Blog;
use App\Form\BlogType;
use App\Services\ValidateService;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class BlogController
 * @package App\Controller\Rest
 * @Route("/api/private/v1/blog", name="ministry_blog_")
 */
class BlogController extends AbstractController
{
    public const BLOG_LIMIT_ITEMS = 100;

    /**
     * This function is used to create/edit Blog
     *
     * @Route("/add", methods={"POST"}, name="add")
     * @Route("/edit/{id}", requirements={"id" : "\d+"}, methods={"PUT"}, name="edit")
     *
     * @param ValidateService $validateService
     * @param Request $request
     * @param null $id
     * @return JsonResponse
     * @throws ConnectionException
     * @throws Exception
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function manageBlog(ValidateService $validateService, Request $request, $id = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            // start DB transaction
            $entityManager->getConnection()->beginTransaction();

            // check id edit action
            if ($id) {
                $blog = $entityManager->getRepository(Blog::class)->find($id);

                if (!$blog) {
                    return $this->json(['message' => "Blog with id=$id not found"], JsonResponse::HTTP_NOT_FOUND);
                }

            } else {
                $blog = new Blog();
            }

            // create FORM for handle all data with errors
            $form = $this->createForm(BlogType::class, $blog, [
                'method' => $request->getMethod()
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->persist($blog);
                $entityManager->flush($blog);
                $entityManager->getConnection()->commit();
            } else {
                $errors = $form->getErrors(true, true);
                $validateService->checkFormErrors($errors);
            }

        } catch (Exception $e) {
            // roll back query changes in DB
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json(['status' => JsonResponse::HTTP_OK]);
    }

    /**
     * This function is used to get Blog by id or limit
     *
     * @Route("/get/{id}", requirements={"id" : "\d+"}, methods={"GET"}, name="get")
     * @Route("/get/limit/{limit}", requirements={"limit" : "\d+"}, methods={"GET"}, name="get_all")
     *
     * @param SerializerInterface $serializer
     * @param int|null $limit
     * @param null $id
     * @return JsonResponse
     */
    public function getBlog(SerializerInterface $serializer, $limit = null, $id = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        if ($id) {
            $blog = $entityManager->getRepository(Blog::class)->find($id);
        } elseif ($limit) {

            if ($limit > self::BLOG_LIMIT_ITEMS) {
                return $this->json(['message' => 'Max limit number is 1000'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $blog = $entityManager->getRepository(Blog::class)->findBy([], ['title' => 'ASC'], $limit, 0);
        } else {
            return $this->json(['message' => 'Invalid url param'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $blogData = $serializer->serialize($blog, 'json', SerializationContext::create()->setGroups(['blog']));
        $blogData = json_decode($blogData, true);

        return $this->json($blogData, JsonResponse::HTTP_OK);
    }

    /**
     * @Route("/delete/{id}", requirements={"id" : "\d+"}, methods={"DELETE"}, name="delete")
     * @ParamConverter("question", class="App\Entity\Blog")
     * @param Blog $blog
     * @return JsonResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteBlogAction(Blog $blog): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($blog);
        $entityManager->flush();

        return $this->json('', JsonResponse::HTTP_NO_CONTENT);
    }
}