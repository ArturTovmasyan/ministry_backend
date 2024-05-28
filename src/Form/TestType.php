<?php

namespace App\Form;

use App\Entity\Question;
use App\Entity\Test;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class TestType
 * @package App\Form
 */
class TestType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('question')
            ->add('instructor');

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $formEvent) {

            /** @var Test $test */
            $test = $formEvent->getData();

            /** @var ArrayCollection $questions */
            $questions = $test->getQuestion();

            if ($questions instanceof PersistentCollection) {
                $removeQuestions = $questions->getDeleteDiff();

                /** @var Question $removeQuestion */
                foreach ($removeQuestions as $removeQuestion) {
                    $removeQuestion->removeTest($test);
                }

                // get new insertion
                $newQuestions = $questions->getInsertDiff();
                $questions = $newQuestions;
            }

            /** @var Question $question */
            foreach ($questions as $question) {
                $question->addTest($test);
            }
        });
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Test::class,
            'allow_extra_fields' => true,
            'csrf_protection' => false
        ]);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'test';
    }
}
