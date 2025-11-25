<?php
// src/Form/DoctorType.php

namespace App\Form;

use App\Entity\Doctor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class DoctorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // USER FIELDS (unmapped)
            ->add('firstName', TextType::class, [
                'mapped' => false,
                'label' => 'First Name',
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'John'],
                'constraints' => [new NotBlank(['message' => 'First name required'])],
            ])
            ->add('lastName', TextType::class, [
                'mapped' => false,
                'label' => 'Last Name',
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Doe'],
                'constraints' => [new NotBlank(['message' => 'Last name required'])],
            ])
            ->add('email', EmailType::class, [
                'mapped' => false,
                'label' => 'Email Address',
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'doctor@example.com'],
                'constraints' => [new NotBlank(['message' => 'Email required'])],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Password',
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Create password'],
                'constraints' => [
                    new NotBlank(['message' => 'Password required']),
                    new Length(['min' => 6, 'minMessage' => 'Min 6 characters']),
                ],
            ])

            // DOCTOR FIELDS
            ->add('specialty', TextType::class, [
                'label' => 'Medical Specialty',
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => 'Cardiology'],
                'constraints' => [new NotBlank(['message' => 'Specialty required'])],
            ])
            ->add('phone', TextType::class, [
                'required' => false,
                'label' => 'Phone Number',
                'attr' => ['class' => 'form-control form-control-lg', 'placeholder' => '+1 (555) 123-4567'],
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
                'label' => 'Professional Bio',
                'attr' => ['class' => 'form-control', 'rows' => 5],
            ])
            ->add('rating', NumberType::class, [
                'required' => false,
                'label' => 'Initial Rating',
                'attr' => ['class' => 'form-control form-control-lg', 'step' => '0.1', 'min' => '0', 'max' => '5'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Doctor::class,
        ]);
    }
}