<?php

namespace App\Form;

use App\Entity\Answer;
use App\Entity\Question;
use App\Entity\TestFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class QuestionType
 * @package App\Form
 */
class QuestionType extends AbstractType
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
            ->add('name')
            ->add('explanation')
            ->add('filters', EntityType::class, [
                'class' => TestFilter::class,
                'multiple' => true
            ])
            ->add('answer', CollectionType::class, [
                'entry_type' => AnswerType::class,
                'allow_add' => true,
                'allow_delete' => true
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $formEvent) use ($entityManager) {

            /** @var Question $question */
            $question = $formEvent->getData();

            /** @var ArrayCollection $answers */
            $answers = $question->getAnswer();

            /** @var Form $form */
            $form = $formEvent->getForm();

            if (\count($answers) === 0) {
                $form->get('answer')->addError(new FormError('Question must have an answers!'));
            }

            /** @var Question $lastQuestion */
            $lastQuestion = $entityManager->getRepository(Question::class)->findLast();
            $lastNumber = $lastQuestion ? $lastQuestion->getNumber() : 0;
            $question->setNumber(++$lastNumber);

            if ($answers instanceof PersistentCollection) {

                // get diff for remove
                $removeAnswers = $answers->getDeleteDiff();

                /** @var Answer $removeAnswer */
                foreach ($removeAnswers as $removeAnswer) {
                    $entityManager->remove($removeAnswer);
                }

                // get new insertion
                $newAnswers = $answers->getInsertDiff();
                $answers = $newAnswers;
            }

            /** @var Answer $answer */
            foreach ($answers as $answer) {
                $answer->setQuestion($question);
            }
        });
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        // set entity manager
        $resolver->setRequired('entity_manager');
        $resolver->setDefaults([
            'data_class' => Question::class,
            'allow_extra_fields' => true,
            'csrf_protection' => false
        ]);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'question';
    }
}
