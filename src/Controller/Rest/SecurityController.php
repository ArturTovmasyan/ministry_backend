<?php

namespace App\Controller\Rest;

use App\Components\Helper\JsonHelper;
use App\Controller\Exception\Exception;
use App\Entity\User;
use App\Form\UserType;
use App\Services\EmailService;
use App\Services\ValidateService;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;

/**
 * Class SecurityController
 * @package App\Controller\Rest
 */
class SecurityController extends AbstractController
{
    /**
     * This function is used to login user
     *
     * @Route("/api/public/v1/user/login", methods={"POST"}, name="user_login")
     *
     * @param bool $autoLogin
     * @param $request
     * @param SerializerInterface $serializer
     * @return JsonResponse
     * @throws
     */
    public function loginAction(Request $request, SerializerInterface $serializer, $autoLogin = false): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        // get user credentials
        $email = $request->get('user')['email'];

        if ($autoLogin) {
            $password = $request->get('user')['plainPassword']['first'];
        } else {
            $password = $request->get('user')['plainPassword'];
        }

        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        // check if user not exists
        if (!$user) {
            throw new Exception("User by username` $email not found", JsonResponse::HTTP_BAD_REQUEST);
        }

        // generate encoder factory
        $defaultEncoder = new MessageDigestPasswordEncoder('sha512', true);
        $encoders = [User::class => $defaultEncoder];
        $encoderFactory = new EncoderFactory($encoders);

        /** @var EncoderFactory $encoderService */
        $encoder = $encoderFactory->getEncoder($user);

        // check password is invalid valid
        if (!$encoder->isPasswordValid($user->getPassword(), $password, $user->getSalt())) {
            throw new Exception('Invalid username or password.', JsonResponse::HTTP_BAD_REQUEST);
        }

        // get user token
        $token = $this->getUserAuthToken();
        $token = $token ? json_decode($token, true) : [];

        // check if access token response is invalid
        if (!$token || !array_key_exists('access_token', $token)) {
            throw new Exception('Can not get auth token', JsonResponse::HTTP_BAD_REQUEST, $token);
        }

        // generate data for json response
        $data = [
            'status' => JsonResponse::HTTP_OK,
            'message' => 'Success',
            'data' => ['token' => $token, 'user' => [$user]]
        ];

        $userContent = $serializer->serialize($data, 'json', SerializationContext::create()->setGroups(['user']));

        // create new json Response
        $response = new JsonResponse();

        // set data in response content
        $response->setContent($userContent);

        return $response;
    }

    /**
     * This function is used to registration user
     *
     * @Route("/api/public/v1/user/registration", methods={"POST"}, name="user_registration")
     * @Route("/api/private/v1/user/edit/{id}", methods={"PUT"}, name="user_edit_data")
     *
     * @param int $id
     * @param Request $request
     * @param EmailService $emailService
     * @param ValidateService $validateService
     * @param SerializerInterface $serializer
     * @return JsonResponse
     * @throws
     */
    public function registrationAction(
        Request $request,
        EmailService $emailService,
        ValidateService $validateService,
        SerializerInterface $serializer,
        $id = null
    ): JsonResponse
    {
        $loginUserData = [];

        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            //start DB transaction
            $entityManager->getConnection()->beginTransaction();
            $isEdit = false;

            // check id edit action
            if ($id) {
                $user = $entityManager->getRepository(User::class)->find($id);
                $isEdit = true;
            } else {
                // get app host and email data
                $confirmToken = JsonHelper::generateCode(5).time();
                $user = new User();
                $user->setConfirmToken($confirmToken);
            }

            // create FORM for handle all data with errors
            $form = $this->createForm(UserType::class, $user, [
                'method' => $request->getMethod(),
                'is_edit' => $isEdit
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->persist($user);
                $entityManager->flush();

                if (!$id) {
                    // generate and send email
                    $this->sendConfirmEmail($emailService, $user);
                    $loginUserData = $this->loginAction($request, $serializer, true);
                }

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

        if ($isEdit) {
            return $this->json(['status' => JsonResponse::HTTP_CREATED], JsonResponse::HTTP_CREATED);
        }

        return $loginUserData;
    }

    /**
     * This function is used to confirm user registration
     *
     * @Route("/api/public/v1/user/{id}/confirm/reg/{confirmToken}", methods={"GET"}, name="user_registration_confirm")
     * @param $id
     * @param $confirmToken
     * @param EmailService $emailService
     * @return RedirectResponse
     * @throws
     */
    public function confirmRegAction($id, $confirmToken, EmailService $emailService): RedirectResponse
    {
        // check if confirm token not exist
        if (!$confirmToken || !$id) {
            throw new Exception('Invalidate confirm url.', JsonResponse::HTTP_BAD_REQUEST);
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['confirmToken' => $confirmToken, 'id' => $id]);

        // check if user not exist
        if (!$user) {
            throw new Exception('Invalidate confirm token.', JsonResponse::HTTP_BAD_REQUEST);
        }

        // activate user
        $user->setConfirmToken(null);
        $user->setEnabled(true);
        $entityManager->persist($user);
        $entityManager->flush();

        // generate confirmation email data
        $data = [
            'subject' => 'CONGRATULATION !!!',
            'toEmail' => $user->getEmail(),
            'type' => 'congratulation',
            'web_host' => getenv('WEB_HOST'),
            'backend_host' => getenv('BACKEND_HOST')
        ];

        // send email by service
        $emailService->sendEmail($data);
        $host = getenv('WEB_HOST');

        return $this->redirect($host, JsonResponse::HTTP_FOUND);
    }

    /**
     * This function is used to get user access token by Auth0
     *
     * @return mixed|string|JsonResponse
     */
    private function getUserAuthToken()
    {
        // get auth parameters for get access token
        $auth_domain = getenv('AUTH_TOKEN_DOMAIN');
        $client_id = getenv('AUTH_CLIENT_ID');
        $client_secret = getenv('AUTH_CLIENT_SECRET');
        $audience = getenv('AUTH_API_IDENTIFIER');

        $postData = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'audience' => $audience,
            'grant_type' => 'client_credentials'
        ];

        // start curl
        $curl = curl_init();

        // generate curl body for get token
        curl_setopt_array($curl, [
            CURLOPT_URL => $auth_domain,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST, true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'content-type: application/json'
            ]
        ]);

        // run curl
        $response = curl_exec($curl);
        $err = curl_error($curl);

        // close curl
        curl_close($curl);

        if ($err) {
            return 'cURL Error #:' . $err;
        }

        return $response;
    }

    /**
     * This function is used to send confirm email after registration
     *
     * @param EmailService $emailService
     * @param User $user
     * @throws \Exception
     */
    private function sendConfirmEmail(EmailService $emailService, User $user): void
    {
        $backendHost = getenv('BACKEND_HOST');
        $confirmUrl = $this->generateUrl('user_registration_confirm', ['id' => $user->getId(), 'confirmToken' => $user->getConfirmToken()]);

        // generate confirm registration url
        $confirmAction = $backendHost . $confirmUrl;

        // generate confirmation email data
        $data = [
            'subject' => 'Confirm your email address.',
            'toEmail' => $user->getEmail(),
            'confirmAction' => $confirmAction,
            'fullName' => $user->getFullName(),
            'type' => 'confirm-registration',
            'backend_host' => $backendHost
        ];

        // send email by service
        $emailService->sendEmail($data);
    }

    /**
     * @Route("/api/private/v1/change-password", methods={"POST"}, name="user_change_password")
     *
     * @param Request $request
     * @param ValidateService $validateService
     * @return JsonResponse
     * @throws Exception
     * @throws ConnectionException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function postChangePasswordAction(Request $request, ValidateService $validateService): JsonResponse
    {
        try {
            /** @var EntityManager $entityManager */
            $entityManager = $this->getDoctrine()->getManager();

            //start DB transaction
            $entityManager->getConnection()->beginTransaction();

            $userId = $request->request->get('user_change_password')['user'];
            $currentPassword = $request->request->get('user_change_password')['current_password'];

            /** @var User $user */
            $user = $entityManager->getRepository(User::class)->find($userId);

            if (!$user) {
                return $this->json(['message' => "User by id=$userId not found"], JsonResponse::HTTP_NOT_FOUND);
            }

            // generate encoder factory
            $defaultEncoder = new MessageDigestPasswordEncoder('sha512', true, 5000);
            $encoderFactory = new EncoderFactory([User::class => $defaultEncoder]);

            /** @var EncoderFactory $encoderService */
            $encoder = $encoderFactory->getEncoder($user);

            // check password is valid
            if (!$encoder->isPasswordValid($user->getPassword(), $currentPassword, $user->getSalt())) {
                return $this->json(['message' => 'Invalid current password.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            // get new passwords data
            $newPassword = $request->request->get('user_change_password')['new_password'];
            $confirmPassword = $request->request->get('user_change_password')['confirm_password'];

            if ($newPassword && $newPassword === $confirmPassword) {
                $user->setPlainPassword($newPassword);
                $entityManager->persist($user);

                // check validation for user model
                $validateService->checkValidation($user);

                $entityManager->flush();
                $entityManager->getConnection()->commit();
            } else {
                return $this->json(['message' => 'Password fields must match.'], JsonResponse::HTTP_BAD_REQUEST);
            }

        } catch (Exception $e) {
            // roll back query changes in DB
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json(['message' => 'Password changed.'], JsonResponse::HTTP_OK);
    }

    /**
     * @Route("/api/public/v1/reset/password", name="reset_user_password")
     * @param Request $request
     * @param EmailService $emailService
     * @return JsonResponse
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \Exception
     */
    public function postResetPasswordAction(Request $request, EmailService $emailService):JsonResponse
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        if ($request->isMethod('POST')) {

            $email = $request->get('email');

            if (!$email) {
                return $this->json(['status' => JsonResponse::HTTP_BAD_REQUEST, 'message' => 'Email is required params.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            /** @var User $user */
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user) {
                return $this->json(['status' => JsonResponse::HTTP_NOT_FOUND, 'message' => "User by email`$email not found"], JsonResponse::HTTP_NOT_FOUND);
            }

            $generatePassword = JsonHelper::generateCode(8);

            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $user->setPlainPassword($generatePassword);
            $user->isPasswordReset = true;

            $em->persist($user);
            $em->flush($user);

            // generate invite people join email
            $studentEmailData = [
                'subject' => 'Reset Password',
                'backend_host' => getenv('BACKEND_HOST'),
                'fullName' => $user->getFullName(),
                'password' => $generatePassword,
                'toEmail' => $email,
                'type' => 'reset-password'
            ];

            // send email by service
            $emailService->sendEmail($studentEmailData);
            return $this->json(['status' => JsonResponse::HTTP_OK, 'message' => 'Password successfully reset.'], JsonResponse::HTTP_OK);
        }

        return $this->json(['status' => JsonResponse::HTTP_NO_CONTENT], JsonResponse::HTTP_NO_CONTENT);
    }
}
