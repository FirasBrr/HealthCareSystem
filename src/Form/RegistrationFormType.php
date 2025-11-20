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
                'attr' => [
                    'placeholder' => 'Enter your email address',
                    'class' => 'form-control form-control-custom'
                ]
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => [
                    'placeholder' => 'Enter your first name',
                    'class' => 'form-control form-control-custom'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your first name',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => [
                    'placeholder' => 'Enter your last name',
                    'class' => 'form-control form-control-custom'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your last name',
                    ]),
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'I want to register as:',
                'choices' => [
                    'Patient' => 'ROLE_PATIENT',
                    'Doctor' => 'ROLE_DOCTOR',
                ],
                'attr' => [
                    'class' => 'form-control form-control-custom'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select your account type',
                    ]),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'Create a secure password',
                    'class' => 'form-control form-control-custom'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;

        // Add dynamic fields based on selected role
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            // Add phone field for both roles
            $form->add('phone', TextType::class, [
                'label' => 'Phone Number',
                'mapped' => false, // This field is not mapped to the User entity
                'attr' => [
                    'placeholder' => 'Enter your phone number',
                    'class' => 'form-control form-control-custom'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your phone number',
                    ]),
                ],
            ]);
        });

        // Add role-specific fields after role is selected
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            $role = $data['role'] ?? null;

            if ($role === 'ROLE_PATIENT') {
                $form->add('address', TextareaType::class, [
                    'label' => 'Address',
                    'mapped' => false,
                    'attr' => [
                        'placeholder' => 'Enter your complete address',
                        'class' => 'form-control form-control-custom',
                        'rows' => 3
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Please enter your address',
                        ]),
                    ],
                ]);
            } elseif ($role === 'ROLE_DOCTOR') {
                $form->add('specialty', TextType::class, [
                    'label' => 'Medical Specialty',
                    'mapped' => false,
                    'attr' => [
                        'placeholder' => 'e.g., Cardiology, Pediatrics, etc.',
                        'class' => 'form-control form-control-custom'
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Please enter your medical specialty',
                        ]),
                    ],
                ])->add('bio', TextareaType::class, [
                    'label' => 'Professional Bio',
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Tell patients about your experience and qualifications...',
                        'class' => 'form-control form-control-custom',
                        'rows' => 4
                    ],
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