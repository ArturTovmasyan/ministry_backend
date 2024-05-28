<?php

namespace App\Form;

use App\Entity\AssignTest;
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
 * Class SelfAssignTestType
 * @package App\Form
 */
class SelfAssignTestType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var EntityManager $entityManager */
        $entityManager = $options['entity_manager'];

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

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $formEvent) use ($entityManager) {

            /** @var AssignTest $assignedTest */
            $assignedTest = $formEvent->getData();

            /** @var Form $form */
            $form = $formEvent->getForm();

            $testId = $assignedTest->getTest() ? $assignedTest->getTest()->getId() : null;
            $studentId = $assignedTest->getStudent() ? $assignedTest->getStudent()->getId() : null;

            /** @var AssignTest $assignTest */
            $assignTest = $entityManager->getRepository(AssignTest::class)->findOneBy(['student' => $studentId, 'test' => $testId, 'status' => AssignTest::STARTED]);

            if ($assignTest) {
                $form->get('test')->addError(new FormError('This test already assigned and not finished from current student.'));
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
        return 'self_assign_test';
    }
}
