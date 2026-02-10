<?php

namespace App\Form;

use App\Entity\Lieu;
use App\Entity\Ordonnance;
use App\Entity\RendezVous;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateHeure', null, [
                'widget' => 'single_text', // Affiche un calendrier HTML5
                'label' => 'Date et Heure souhaitées'
            ])
            ->add('statut')
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Consultation au cabinet' => 'Présentiel',
                    'Vidéo Consultation' => 'Télémédecine',
                ],
            ])
            ->add('lienVisio')
            ->add('remarques', TextareaType::class, [
                'required' => false,
                'label' => 'Motif de la consultation'
            ])
            ->add('patient', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
            ->add('medecin', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'nom', // Affiche le nom du médecin
                // Idéalement, filtre ici pour n'afficher que les utilisateurs avec ROLE_MEDECIN
                'label' => 'Choisir un médecin'
            ])
            ->add('lieu', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'nom',
                'required' => false,
                'label' => 'Lieu (si présentiel)',
                'placeholder' => 'Choisir un cabinet...'
            ])
            ->add('ordonnance', EntityType::class, [
                'class' => Ordonnance::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
