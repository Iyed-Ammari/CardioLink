<?php
// src/Form/ProduitType.php

namespace App\Form;

use App\Entity\Produit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

final class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Length(['max' => 120]),
                ],
            ])
            ->add('categorie', TextType::class, [
                'required' => false,
            ])
            ->add('description', TextType::class, [
                'required' => false,
            ])

            ->add('prix', NumberType::class, [
                'scale' => 2,
                'input' => 'string',
                'constraints' => [
                    new NotBlank(['message' => 'Le prix est obligatoire.']),
                    new PositiveOrZero(['message' => 'Le prix doit être positif.']),
                ],
                'attr' => [
                    'step' => '0.01',
                    'inputmode' => 'decimal',
                ],
            ])

            ->add('stock', IntegerType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Le stock est obligatoire.']),
                    new PositiveOrZero(['message' => 'Le stock doit être positif ou zéro.']),
                ],
                'attr' => [
                    'min' => 0,
                    'inputmode' => 'numeric',
                ],
            ])

            ->add('imageUrl', TextType::class, [
                'required' => false,
                'label' => false,
            ])

            ->add('imageFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '4M',
                        'mimeTypesMessage' => 'Veuillez choisir une image valide.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}
