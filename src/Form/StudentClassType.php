<?php

namespace App\Form;

use App\Entity\StudentClass;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class StudentClassType
 * @package App\Form
 */
class StudentClassType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('name')
            ->add('instructor')
            ->add('student', EntityType::class, [
                'class' => User::class,
                'multiple' => true,
                'required' => false,
                'query_builder' => static function (EntityRepository $repository) {
                    return $repository
                        ->createQueryBuilder('item')
                        ->where('item.type = :userType')
                        ->setParameter('userType', User::STUDENT);
                }
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $formEvent) use ($isEdit) {

            /** @var Form $form */
            $form = $formEvent->getForm();
            !$isEdit ? $form->remove('student') : null;
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $formEvent) {

            /** @var StudentClass $studentClass */
            $studentClass = $formEvent->getData();

            /** @var ArrayCollection $students */
            $students = $studentClass->getStudent();

            if ($students instanceof PersistentCollection) {

                $removedStudents = $students->getDeleteDiff();

                /** @var User $removedStudent */
                foreach ($removedStudents as $removedStudent) {
                    $removedStudent->setClass(null);
                }

                // get new insertion
                $newStudents = $students->getInsertDiff();
                $students = $newStudents;
            }

            /** @var User $student */
            foreach ($students as $student) {
                $student->setClass($studentClass);
            }
        });
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('is_edit');
        $resolver->setDefaults([
            'data_class' => StudentClass::class,
            'allow_extra_fields' => true,
            'csrf_protection' => false
        ]);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'student_class';
    }
}
