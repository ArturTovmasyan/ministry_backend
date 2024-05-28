<?php

namespace App\Form;

use App\Entity\AssignTest;
use App\Entity\Test;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class AssignTestType
 * @package App\Form
 */
class AssignTestType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var EntityManager $entityManager */
        $entityManager = $options['entity_manager'];
        $isEdit = $options['is_edit'];
        $existStudent = $options['exist_student'];
        $classIds = $options['classIds'];

        $builder
            ->add('deadline', DateTimeType::class, ['widget' => 'single_text', 'format' => 'yyyy-mm-dd'])
            ->add('timeLimit')
            ->add('expectation')
            ->add('test')
            ->add('student', EntityType::class, [
                'class' => User::class,
                'required' => false,
                'query_builder' => static function (EntityRepository $repository) {
                    return $repository
                        ->createQueryBuilder('item')
                        ->where('item.type = :userType')
                        ->setParameter('userType', User::STUDENT);
                }
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $formEvent) use ($entityManager, $isEdit, $existStudent, $classIds) {

            /** @var AssignTest $assignedTest */
            $assignedTest = $formEvent->getData();

            if (!$isEdit) {

                /** @var Form $form */
                $form = $formEvent->getForm();

                /** @var Test $test */
                $test = $assignedTest->getTest();

                /** @var $students */
                $students = $entityManager->getRepository(User::class)->getStudentsByClass($classIds, $test->getId());
                $assignedTest->studentArray = $students;

                $studentIds = array_map(static function (User $item) {
                    return $item->getId();
                }, $students);

                // check if test already assign to current students
                $isTestExist = $entityManager->getRepository(AssignTest::class)->findByTestAndStudent($test->getId(), $studentIds);

                if ($isTestExist) {
                    $form->get('test')->addError(new FormError('Duplicate test for students!'));
                } else {
                    // create assign test for each student
                    foreach ($students as $student) {
                        $newAssignTest = clone $assignedTest;
                        $newAssignTest->setStudent($student);
                        $entityManager->persist($newAssignTest);
                    }
                }

            } else {
                $assignedTest->setStudent($existStudent);
            }
        });
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        // set parameters for form
        $resolver->setRequired('entity_manager');
        $resolver->setRequired('is_edit');
        $resolver->setRequired('exist_student');
        $resolver->setRequired('classIds');

        $resolver->setDefaults([
            'data_class' => AssignTest::class,
            'allow_extra_fields' => true,
            'csrf_protection' => false
        ]);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'assign_test';
    }
}
