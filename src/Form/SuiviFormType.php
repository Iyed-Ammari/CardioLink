<?php

namespace App\Form;

use App\Entity\Suivi;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SuiviFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeDonnee', ChoiceType::class, [
                'label' => 'Type de donnée',
                'choices' => [
                    'Fréquence Cardiaque' => 'Fréquence Cardiaque',
                    'Tension' => 'Tension',
                    'SpO2' => 'SpO2',
                    'Température' => 'Température',
                    'Glycémie' => 'Glycémie',
                ],
                'placeholder' => 'Sélectionnez le type de donnée',
                'attr' => [
                    'class' => 'form-control',
                ]
            ])
            ->add('valeur', NumberType::class, [
                'label' => 'Valeur mesurée',
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 120.5',
                    'step' => '0.01',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La valeur est obligatoire.']),
                    new Assert\Type(['type' => 'float', 'message' => 'La valeur doit être un nombre.']),
                    new Assert\Positive(['message' => 'La valeur doit être positive.']),
                ],
            ])
            ->addEventListener(FormEvents::PRE_SUBMIT, function(FormEvent $event) {
                $data = $event->getData();
                
                if (is_array($data) && isset($data['typeDonnee'])) {
                    // Remplir automatiquement l'unité avant la validation
                    $unites = [
                        'Fréquence Cardiaque' => 'bpm',
                        'Tension' => 'mmHg',
                        'SpO2' => '%',
                        'Température' => '°C',
                        'Glycémie' => 'mg/dL',
                    ];
                    
                    if (isset($unites[$data['typeDonnee']])) {
                        $data['unite'] = $unites[$data['typeDonnee']];
                        $event->setData($data);
                    }
                }
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Suivi::class,
            'allow_extra_fields' => true,
        ]);
    }
}
