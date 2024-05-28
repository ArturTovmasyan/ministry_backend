<?php

namespace App\Form;

use App\Entity\Answer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class AnswerType
 * @package App\Form
 */
class AnswerType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('isRight');
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Answer::class,
            'allow_extra_fields' => true,
            'csrf_protection' => false
        ]);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'answer';
    }
}
