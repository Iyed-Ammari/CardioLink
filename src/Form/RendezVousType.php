<?php

namespace App\Form;

use App\Entity\Lieu;
use App\Entity\RendezVous;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class RendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateHeure', null, [
                'label' => 'Date et Heure souhaitées',
            ])

           ->add('medecin', EntityType::class, [
    'class' => User::class,
    'choice_label' => function(User $user) {
        return $user->getNom() . ' ' . $user->getPrenom();
    },
    'label' => 'Choisir un médecin',
    'placeholder' => 'Sélectionnez un médecin...',
    'query_builder' => function (EntityRepository $er) {
        return $er->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_MEDECIN"%')
            ->orderBy('u.nom', 'ASC');
    },
])


            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Consultation au cabinet' => 'Présentiel',
                    'Vidéo Consultation' => 'Télémédecine',
                ],
                'label' => 'Type de consultation',
            ])

            // ->add('lieu', EntityType::class, [
            //     'class' => Lieu::class,
            //     'choice_label' => 'nom',
            //     'placeholder' => 'Choisir un cabinet...',
            //     'required' => false,
            // ])

            ->add('remarques', TextareaType::class, [
                'label' => 'Motif de la consultation',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
