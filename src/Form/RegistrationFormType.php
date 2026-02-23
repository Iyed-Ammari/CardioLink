<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add('nom')        // Ajouté
            ->add('prenom')     // Ajouté
            ->add('tel')        // Ajouté
            ->add('adresse')    // Ajouté
            ->add('cabinet', TextType::class, [
                'label' => 'Adresse du cabinet (optionnel - pour les médecins)',
                'required' => false,
                'mapped' => true,
            ])
            ->add('groupeSanguin', ChoiceType::class, [
                'mapped' => false,
                'choices' => [
                    'Select your blood type' => '',
                    'A+' => 'A+',
                    'A-' => 'A-',
                    'B+' => 'B+',
                    'B-' => 'B-',
                    'AB+' => 'AB+',
                    'AB-' => 'AB-',
                    'O+' => 'O+',
                    'O-' => 'O-',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select your blood type',
                    ]),
                ],
            ])
            ->add('antecedents', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'List any medical conditions or surgeries (optional)',
                    'rows' => 4,
                ],
            ])
            ->add('allergies', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'List any known allergies (optional)',
                    'rows' => 4,
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'You should agree to our terms.',
                    ]),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('imageFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Profile Picture (optional)',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
