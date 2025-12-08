<?php

namespace App\Form;

use App\Entity\Availability;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AvailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dayOfWeek', ChoiceType::class, [
                'choices' => [
                    'Monday' => 'Monday',
                    'Tuesday' => 'Tuesday',
                    'Wednesday' => 'Wednesday',
                    'Thursday' => 'Thursday',
                    'Friday' => 'Friday',
                    'Saturday' => 'Saturday',
                    'Sunday' => 'Sunday',
                ],
                'required' => false,
                'placeholder' => 'Select day (for recurring)',
            ])
            ->add('startTime', TimeType::class, ['widget' => 'single_text'])
            ->add('endTime', TimeType::class, ['widget' => 'single_text'])
            ->add('recurring', CheckboxType::class, ['required' => false, 'label' => 'Recurring weekly?'])
            ->add('date', DateType::class, ['widget' => 'single_text', 'required' => false, 'label' => 'Specific Date (for non-recurring/vacation)'])
            ->add('isAvailable', CheckboxType::class, ['required' => false, 'label' => 'Available? (Uncheck for unavailable/blocked)'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Availability::class,
        ]);
    }
}