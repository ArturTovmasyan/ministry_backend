<?php

namespace App\Controller\Rest;

use App\Components\Helper\JsonHelper;
use App\Controller\Exception\Exception;
use App\Entity\AssignTest;
use App\Entity\StudentClass;
use App\Entity\User;
use App\Form\AssignTestType;
use App\Form\SelfAssignTestType;
use App\Form\StudentClassType;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\ValidateService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\PersistentCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class InstructorController
 * @package App\Controller\Rest
 */
class InstructorController extends AbstractController
{
    /** @var $validateService */
    protected $validateService;

    /** @var $emailService */
    protected $emailService;

    /** @var $notificationService */
    protected $notificationService;

    /**
     * InstructorController constructor.
     *
     * @param ValidateService $validateService
     * @param EmailService $emailService
     * @param NotificationService $notificationService
     */
    public function __construct
    (
        ValidateService $validateService,
        EmailService $emailService,
        NotificationService $notificationService
    )
    {
        $this->validateService = $validateService;
        $this->emailService = $emailService;
        $this->notificationService = $notificationService;
    }

    /**
     * This function is used to assign test for students
     *
     * @Route("/api/private/v1/assign-test/create", methods={"POST"}, name="ministry_create_assigned_test")
     * @Route("/api/private/v1/assign-test/edit/{id}", methods={"PUT"}, requirements={"id" : "\d+"}, name="ministry_edit_assigned_test")
     *
     * @param Request $request
     * @param int $id
     *
     * @return JsonResponse
     * @throws
     */
    public function manageAssignTestAction(Request $request, $id = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            //start DB transaction
            $entityManager->getConnection()->beginTransaction();
            $existStudent = null;
            $isEdit = false;

            // check if edit action
            if ($id) {

                /** @var AssignTest $assignTest */
                $assignTest = $entityManager->getRepository(AssignTest::class)->find($id);

                /** @var User $existStudent */
                $existStudent = $assignTest->getStudent();
                $isEdit = true;
            } else {
                $assignTest = new AssignTest();
            }

            // get class ids by request data
            $classIds = $request->get('assign_test')['class'] ?? [];

            // create FORM for handle all data with errors
            $form = $this->createForm(AssignTestType::class, $assignTest, [
                'method' => $request->getMethod(),
                'entity_manager' => $entityManager,
                'is_edit' => $isEdit,
                'exist_student' => $existStudent,
                'classIds' => $classIds
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                $entityManager->flush();
                $entityManager->getConnection()->commit();

                // get students from form event
                $students = $assignTest->studentArray;

                // check if not edit action
                if (!$id) {
                    $this->notificationService->createNotification($students, getenv('WEB_HOST'));
                }

                $studentsEmail = array_map(static function (User $item) {
                    return $item->getEmail();
                }, $students);

                if (\count($studentsEmail) > 0) {

                    // create email data params
                    $testData = [
                        'deadline' => $assignTest->getDeadline(),
                        'expectation' => $assignTest->getExpectation(),
                        'timeLimit' => $assignTest->getTimeLimit()
                    ];

                    // send email
                    $this->sendAssignTestMail($studentsEmail, $testData);
                }

            } else {
                // get error by form handler
                $errors = $form->getErrors(true, true);
                $this->validateService->checkFormErrors($errors);
            }

        } catch (Exception $e) {

            // roll back query changes in DB
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json(['status' => JsonResponse::HTTP_CREATED], JsonResponse::HTTP_CREATED);
    }

    /**
     * This function is used to assign test from student
     *
     * @Route("/api/private/v1/self-assign-test", methods={"POST"}, name="ministry_self_assigned_test")
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws
     */
    public function selfStudentAssignTestAction(Request $request): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            //start DB transaction
            $entityManager->getConnection()->beginTransaction();
            $assignTest = new AssignTest();

            // create FORM for handle all data with errors
            $form = $this->createForm(SelfAssignTestType::class, $assignTest, [
                'method' => $request->getMethod(),
                'entity_manager' => $entityManager
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                // get ids
                $testId = $assignTest->getTest() ? $assignTest->getTest()->getId() : null;
                $studentId = $assignTest->getStudent() ? $assignTest->getStudent()->getId() : null;

                /** @var AssignTest $existAssignTest */
                $existAssignTest = $entityManager->getRepository(AssignTest::class)->findOneBy(
                    [
                        'student' => $studentId,
                        'test' => $testId,
                        'status' => AssignTest::STARTED
                    ]
                );

                if ($existAssignTest) {
                    return $this->json(['assign_test_id' => $existAssignTest->getId(), 'status' => JsonResponse::HTTP_OK], JsonResponse::HTTP_OK);
                }

                $entityManager->persist($assignTest);
                $entityManager->flush();
                $entityManager->getConnection()->commit();

            } else {
                // get error by form handler
                $errors = $form->getErrors(true, true);
                $this->validateService->checkFormErrors($errors);
            }

        } catch (Exception $e) {

            // roll back query changes in DB
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json(['assign_test_id' => $assignTest->getId(), 'status' => JsonResponse::HTTP_OK], JsonResponse::HTTP_OK);
    }

    /**
     * @param array $newStudents
     * @return array
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function createNewStudents(array $newStudents): array
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();
        $newStudentIds = [];

        foreach ($newStudents as $studentEmail) {

            $student = new User();
            $student->setEmail($studentEmail);
            $student->setRoles(['ROLE_STUDENT']);
            $student->setStatus(User::CREATED);

            $entityManager->persist($student);
            $entityManager->flush();

            $newStudentIds[] = $student->getId();
        }

        return $newStudentIds;
    }

    /**
     * This function is used to create student class and add students
     *
     * @Route("/api/private/v1/class/create", methods={"POST"}, name="ministry_create_class")
     * @Route("/api/private/v1/class/edit/{id}", methods={"PUT"}, requirements={"id" : "\d+"}, name="ministry_edit_class")
     *
     * @param int $id
     * @param Request $request
     *
     * @return JsonResponse
     * @throws
     */
    public function createClassAction(Request $request, $id = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        try {
            // start DB transaction
            $entityManager->getConnection()->beginTransaction();
            $instructorId = $request->get('student_class')['instructor'];
            $studentIds = $request->get('student_class')['student'];
            $newStudents = $request->get('student_class')['new_students'] ?? [];

            // get new student ids
            $newStudentIds = $this->createNewStudents($newStudents);
            $studentIds = array_merge($studentIds, $newStudentIds);
            $isEdit = false;

            // check if edit action
            if ($id) {
                $isEdit = true;

                /** @var StudentClass $studentClass */
                $studentClass = $entityManager->getRepository(StudentClass::class)
                    ->findOneBy(['id' => $id, 'instructor' => $instructorId]);
            } else {
                $studentClass = new StudentClass();
            }

            // create FORM for handle all data with errors
            $form = $this->createForm(StudentClassType::class, $studentClass, [
                'method' => $request->getMethod(),
                'is_edit' => $isEdit
            ]);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                /** @var ArrayCollection $students */
                $students = $studentClass->getStudent();

                if ($students instanceof PersistentCollection) {
                    $students = $students->getInsertDiff();
                } else {
                    /** @var User $students */
                    $students = $entityManager->getRepository(User::class)->findBy(['id' => $studentIds]);
                }

                $entityManager->persist($studentClass);
                $entityManager->flush();
                $entityManager->getConnection()->commit();

                if ($students) {

                    /** @var User $instructor */
                    $instructor = $studentClass->getInstructor();

                    /** @var User $senderData */
                    $senderName = $instructor->getFullName();

                    // generate email data
                    $emailData = [
                        'students' => $students,
                        'sender' => $senderName,
                        'class' => $studentClass
                    ];

                    $this->sendJoinToClassMail($emailData);
                }

            } else {
                $errors = $form->getErrors(true, true);
                $this->validateService->checkFormErrors($errors);
            }

        } catch (Exception $e) {
            $entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode(), $e->getData() ?? []);
        }

        return $this->json(['status' => JsonResponse::HTTP_CREATED], JsonResponse::HTTP_CREATED);
    }

    /**
     * This function is used to get instructor classes data
     *
     * @Route("/api/private/v1/instructor/classes/data/{id}/{schoolId}", methods={"GET"}, name="ministry_instructor_classes_data")
     *
     * @param int $id
     * @param int|null $schoolId
     * @param ValidateService $validateService
     * @return JsonResponse
     * @throws
     */
    public function getInstructorClassesDataAction(ValidateService $validateService, int $id, $schoolId = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $classesData */
        $classesData = $entityManager->getRepository(User::class)->findInstructorClassesData($id, $schoolId);
        $classesData = $validateService->groupArrayByKey($classesData, 'name');
        $newData = $this->changeClassDataStructure($classesData, $schoolId);

        return $this->json($newData, JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to get instructor classes list
     *
     * @Route("/api/private/v1/classes/list/{id}/{schoolId}", methods={"GET"}, name="ministry_classes_data")
     *
     * @param int $id
     * @param int|null $schoolId
     * @return JsonResponse
     * @throws
     */
    public function getInstructorClassesListAction($id, $schoolId = null): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        /** @var ArrayCollection $classList */
        $classList = $entityManager->getRepository(StudentClass::class)->findClassesByInstructorId($id, $schoolId);

        return $this->json($classList, JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to add student in class
     *
     * @Route("/api/private/v1/add/students/in-class", methods={"POST"}, name="ministry_add_students_in_class")
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws
     */
    public function addStudentsInClassAction(Request $request): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        $classId = $request->get('classId');
        $studentIds = $request->get('studentIds');
        $newStudents = $request->get('new_students') ?? [];

        // get new student ids
        $newStudentIds = $this->createNewStudents($newStudents);
        $studentIds = array_merge($studentIds, $newStudentIds);

        /** @var StudentClass $class */
        $class = $entityManager->getRepository(StudentClass::class)->find($classId);

        /** @var User $students */
        $students = $entityManager->getRepository(User::class)->findBy(['id' => $studentIds]);

        /** @var User $instructor */
        $instructor = $class->getInstructor();

        /** @var User $senderData */
        $senderName = $instructor->getFullName();

        // generate email data
        $emailData = [
            'students' => $students,
            'sender' => $senderName,
            'class' => $class
        ];

        $this->sendJoinToClassMail($emailData);

        return $this->json(['message' => 'success', 'status' => JsonResponse::HTTP_OK], JsonResponse::HTTP_OK);
    }

    /**
     * This function is used to delete student from class
     *
     * @Route("/api/private/v1/delete/students/from-class", methods={"DELETE"}, name="ministry_delete_student_from_class")
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws
     */
    public function deleteStudentsFromClassAction(Request $request): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        //start DB transaction
        $entityManager->getConnection()->beginTransaction();

        // get student ids
        $studentIds = $request->get('studentIds');

        /** @var ArrayCollection $students */
        $students = $entityManager->getRepository(User::class)->findBy(['id' => $studentIds]);

        /** @var User $student */
        foreach ($students as $student) {
            // remove student from class
            $student->setClass(null);
        }

        $entityManager->flush();
        $entityManager->getConnection()->commit();


        return $this->json(['status' => JsonResponse::HTTP_NO_CONTENT], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * This function is used to send confirm join to class email
     *
     * @param array $emailData
     * @throws
     */
    private function sendJoinToClassMail(array $emailData): void
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();

        // get data in array
        $backendHost = getenv('BACKEND_HOST');
        $students = $emailData['students'];

        /** @var StudentClass $class */
        $class = $emailData['class'];

        /** @var User $student */
        foreach ($students as $student) {

            // get student data
            $password = null;
            $studentId = $student->getId();
            $email = $student->getEmail();
            $confirmToken = JsonHelper::generateCode(11);

            // generate and save temporary password
            if ($student->getStatus() === User::CREATED) {
                $password = substr($confirmToken, 0, 6);
                $password = strrev($password);
                $student->setPlainPassword($password);
            }

            // generate student confirm to assign class url
            $confirmUrl = $this->generateUrl('ministry_confirm_user_class', [
                'studentId' => $studentId,
                'classId' => $class->getId(),
                'token' => $confirmToken,
            ]);

            // generate confirm action url for students
            $confirmUrl = $backendHost . $confirmUrl;

            // generate invite people join email
            $studentEmailData = [
                'subject' => 'You are invited to join class',
                'password' => $password,
                'backend_host' => $backendHost,
                'toEmail' => $email,
                'sender' => $emailData['sender'],
                'class' => $class->getName(),
                'type' => 'student-class',
                'url' => $confirmUrl
            ];

            // send email by service
            $this->emailService->sendEmail($studentEmailData);
            $student->setClassToken($confirmToken);
            $student->setClass($class);

            $entityManager->persist($student);
        }

        $entityManager->flush();
    }

    /**
     * This function is used to get Questions
     *
     * @Route("/api/private/v1/delete/class/{id}", methods={"DELETE"}, name="ministry_delete_class")
     * @ParamConverter("class", class="App\Entity\StudentClass")
     *
     * @param StudentClass $class
     *
     * @return JsonResponse
     * @throws
     */
    public function removeClassAction(StudentClass $class): JsonResponse
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($class);
        $entityManager->flush();

        return $this->json('', JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @param array $studentEmails
     * @param array $testData
     * @throws \Exception
     */
    private function sendAssignTestMail(array $studentEmails, array $testData): void
    {
        // get student emails
        $emails = [];

        // structure array with key emails for SendGrid.
        foreach ($studentEmails as $studentEmail) {
            $emails[$studentEmail] = '';
        }

        // generate confirmation email data
        $data = [
            'subject' => 'New Assignment',
            'toEmail' => $emails,
            'type' => 'assign-test',
            'testData' => $testData,
            'web_host' => getenv('WEB_HOST'),
            'backend_host' => getenv('BACKEND_HOST')
        ];

        // send email by service
        $this->emailService->sendEmail($data);
    }

    /**
     * @param $classesData
     * @param $schoolId
     * @return array
     */
    private function changeClassDataStructure($classesData, $schoolId = null): array
    {
        $newData = [];
        $i = 0;

        // change array structure data for frontend
        foreach ($classesData as $key => $value) {
            $newData[] = [
                'name' => $key,
                'class_id' => $classesData[$key][0]['class_id'] ?? null
            ];

            $students = array_map(static function ($item) {
                unset($item['class_id']);
                return $item;
            }, $classesData[$key]);

            $existStudent = $students[0]['student_id'] ?? null;
            $newData[$i]['students'] = $existStudent ? $students : [];
            $i++;
        }

        if ($schoolId && \count($newData) > 0) {
            $newData = reset($newData);
            unset($newData['name'], $newData['class_id']);
            $newData = $newData['students'];
        }

        return $newData;
    }
}