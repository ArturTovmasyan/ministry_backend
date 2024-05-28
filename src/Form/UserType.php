<?php

namespace App\Form;

use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class UserType
 * @package App\Form
 */
class UserType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('firstName', null, [
                'constraints' => new NotBlank(['message' => "First Name can't be blank."])
            ])
            ->add('lastName', null, [
                'constraints' => new NotBlank(['message' => "Last Name can't be blank."])
            ])
            ->add('email')
            ->add('type')
            ->add('country')
            ->add('school', EntityType::class, [
                'class' => School::class,
                'query_builder' => static function (EntityRepository $repository) {
                    return $repository
                        ->createQueryBuilder('item')
                        ->orderBy('item.name', 'ASC');
                },
                'required' => true,
                'multiple' => false,
                'expanded' => false
            ]);

        if (!$isEdit) {
            $passwordOption = ['constraints' => new NotBlank(['message' => 'Password can`t be blank.'])];
            $builder->add('plainPassword', RepeatedType::class, $passwordOption);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $formEvent) use ($isEdit) {

            /** @var Form $form */
            $form = $formEvent->getForm();

            // if edit action
            if ($isEdit) {
                $form->remove('type');
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
            'data_class' => User::class,
            'allow_extra_fields' => true,
            'csrf_protection' => false
        ]);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'user';
    }
}
