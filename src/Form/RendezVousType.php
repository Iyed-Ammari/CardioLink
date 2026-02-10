<?php

namespace App\Form;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class RendezVousType extends AbstractType
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEditByDoctor = $options['edit_by_doctor'] ?? false;

        $builder
            ->add('medecin', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn(User $user) => $user->getNom() . ' ' . $user->getPrenom(),
                'choices' => $this->userRepository->findMedecins(),
                'label' => 'Choisir un médecin',
                'placeholder' => 'Sélectionnez un médecin',
                'disabled' => $isEditByDoctor, // Désactiver si c'est un médecin qui édite
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un médecin.'])
                ]
            ])
            ->add('dateHeure', null, [
                'widget' => 'single_text',
                'label' => 'Date et Heure souhaitées',
                'constraints' => [
                    new NotBlank(['message' => 'La date et l\'heure sont obligatoires.'])
                ]
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Consultation au cabinet' => 'Présentiel',
                    'Vidéo Consultation' => 'Télémédecine',
                ],
                'label' => 'Type de consultation',
                'placeholder' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un type de consultation.'])
                ]
            ])
            ->add('remarques', TextareaType::class, [
                'required' => false,
                'label' => 'Motif / Remarques (optionnel)',
                'attr' => ['placeholder' => 'Ex: Douleurs thoraciques depuis 2 jours...']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
            'edit_by_doctor' => false, // Option par défaut
        ]);
    }
}
