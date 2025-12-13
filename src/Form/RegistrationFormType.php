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
                'constraints' => [new NotBlank(['message' => 'Email address is required'])],
            ])
            ->add('firstName', TextType::class, [
                'label'       => 'First Name',
                'attr'        => ['placeholder' => 'John', 'class' => 'form-control'],
                'constraints' => [new NotBlank(['message' => 'First name is required'])],
            ])
            ->add('lastName', TextType::class, [
                'label'       => 'Last Name',
                'attr'        => ['placeholder' => 'Doe', 'class' => 'form-control'],
                'constraints' => [new NotBlank(['message' => 'Last name is required'])],
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
                'constraints' => [new NotBlank(['message' => 'Please select an account type'])],
            ])
            ->add('phone', TextType::class, [
                'mapped' => false,
                'label' => 'Phone Number',
                'attr' => ['placeholder' => '+1234567890', 'class' => 'form-control form-control-custom'],
                'constraints' => [new NotBlank(['message' => 'Phone number is required'])],
            ])
            ->add('address', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Home Address',
                'attr' => [
                    'rows' => 3,
                    'class' => 'form-control form-control-custom',
                    'placeholder' => 'Enter your complete address'
                ],
            ])
            ->add('specialty', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Medical Specialty',
                'attr' => [
                    'placeholder' => 'e.g. Cardiology',
                    'class' => 'form-control form-control-custom',
                ],
            ])
            ->add('bio', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Professional Bio (optional)',
                'attr' => [
                    'rows' => 5,
                    'class' => 'form-control form-control-custom',
                    'placeholder' => 'Tell patients about your experience...'
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label'  => 'Password',
                'attr'   => ['class' => 'form-control form-control-custom', 'placeholder' => '••••••••'],
                'constraints' => [
                    new NotBlank(['message' => 'Password is required']),
                    new Length(['min' => 6, 'minMessage' => 'Password must be at least 6 characters']),
                ],
            ]);

        // Modify field requirements based on role in PRE_SUBMIT
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            $roleValue = $data['roles'] ?? null;
            $role = is_array($roleValue) ? ($roleValue[0] ?? null) : $roleValue;

            if ($role === 'ROLE_PATIENT') {
                // Add NotBlank constraint to address for patients
                $form->add('address', TextareaType::class, [
                    'mapped' => false,
                    'required' => true,
                    'label' => 'Home Address',
                    'attr' => ['rows' => 3, 'class' => 'form-control form-control-custom'],
                    'constraints' => [new NotBlank(['message' => 'Address is required for patients'])],
                ]);

                // Remove constraints from doctor fields
                $form->add('specialty', TextType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Medical Specialty',
                    'attr' => ['placeholder' => 'e.g. Cardiology', 'class' => 'form-control form-control-custom'],
                ]);
            }

            if ($role === 'ROLE_DOCTOR') {
                // Add NotBlank constraint to specialty for doctors
                $form->add('specialty', TextType::class, [
                    'mapped' => false,
                    'required' => true,
                    'label' => 'Medical Specialty',
                    'attr' => ['placeholder' => 'e.g. Cardiology', 'class' => 'form-control form-control-custom'],
                    'constraints' => [new NotBlank(['message' => 'Medical specialty is required for doctors'])],
                ]);

                // Bio remains optional
                $form->add('bio', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Professional Bio (optional)',
                    'attr' => ['rows' => 5, 'class' => 'form-control form-control-custom'],
                ]);

                // Remove constraint from address
                $form->add('address', TextareaType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Home Address',
                    'attr' => ['rows' => 3, 'class' => 'form-control form-control-custom'],
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'attr' => ['id' => 'registration-form'],
        ]);
    }
}