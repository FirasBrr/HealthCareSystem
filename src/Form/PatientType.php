<?php

namespace App\Form;

use App\Entity\Patient;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PatientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // User fields (these will be mapped manually in the controller)
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'mapped' => false, // This field is not mapped to the Patient entity
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Enter first name'
                ],
                'required' => true
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Enter last name'
                ],
                'required' => true
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Enter email address'
                ],
                'required' => true
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Enter password'
                ],
                'required' => !$options['is_edit'] // Not required when editing
            ])
            // Patient-specific fields (these are mapped to the Patient entity)
            ->add('phone', TelType::class, [
                'label' => 'Phone Number',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => '+1 (555) 123-4567'
                ],
                'required' => true
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Address',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Enter full address...'
                ],
                'required' => true
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Patient::class,
            'is_edit' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}