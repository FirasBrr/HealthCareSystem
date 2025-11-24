<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr'  => ['placeholder' => 'you@example.com', 'class' => 'form-control'],
            ])
            ->add('firstName', TextType::class, [
                'label'       => 'First Name',
                'attr'        => ['placeholder' => 'John', 'class' => 'form-control'],
                'constraints' => [new NotBlank(['message' => 'First name required'])],
            ])
            ->add('lastName', TextType::class, [
                'label'       => 'Last Name',
                'attr'        => ['placeholder' => 'Doe', 'class' => 'form-control'],
                'constraints' => [new NotBlank(['message' => 'Last name required'])],
            ])

            ->add('roles', ChoiceType::class, [
                'label'    => 'I want to register as:',
                'choices'  => [
                    'Patient' => 'ROLE_PATIENT',
                    'Doctor'  => 'ROLE_DOCTOR',
                ],
                'expanded' => true,
                'multiple' => false,
                'mapped'   => false,

                'constraints' => [new NotBlank(['message' => 'Please select account type'])],
            ])

            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label'  => 'Password',
                'attr'   => ['class' => 'form-control', 'placeholder' => '••••••••'],
                'constraints' => [
                    new NotBlank(['message' => 'Password required']),
                    new Length(['min' => 6, 'minMessage' => 'Minimum 6 characters']),
                ],
            ]);

        // Common field: phone
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $event->getForm()->add('phone', TextType::class, [
                'mapped'      => false,
                'label'       => 'Phone Number',
                'attr'        => ['placeholder' => '+1234567890', 'class' => 'form-control'],
                'constraints' => [new NotBlank()],
            ]);
        });

        // Dynamic fields
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            $roleValue = $data['roles'] ?? null;
            $role = is_array($roleValue) ? ($roleValue[0] ?? null) : $roleValue;

            if ($role === 'ROLE_PATIENT') {
                $form->add('address', TextareaType::class, [
                    'mapped'      => false,
                    'required'    => true,
                    'label'       => 'Home Address',
                    'attr'        => ['rows' => 3, 'class' => 'form-control'],
                    'constraints' => [new NotBlank()],
                ]);
            }

            if ($role === 'ROLE_DOCTOR') {
                $form->add('specialty', TextType::class, [
                    'mapped'      => false,
                    'required'    => true,
                    'label'       => 'Medical Specialty',
                    'attr'        => ['placeholder' => 'e.g. Cardiology', 'class' => 'form-control'],
                    'constraints' => [new NotBlank()],
                ]);

                $form->add('bio', TextareaType::class, [
                    'mapped'   => false,
                    'required' => false,
                    'label'    => 'Professional Bio (optional)',
                    'attr'     => ['rows' => 5, 'class' => 'form-control'],
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}