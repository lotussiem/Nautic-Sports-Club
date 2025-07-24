<?php

namespace App\Form;

use App\Entity\Activity;
use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Vich\UploaderBundle\Form\Type\VichFileType;

class ActivityFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Name is required.']),
                    new Length([
                        'min' => 5,
                        'max' => 30,
                        'minMessage' => 'Name must be at least {{ limit }} characters long.',
                        'maxMessage' => 'Name cannot be longer than {{ limit }} characters.',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
                'label' => '<i class="fa fa-user text-primary mr-2"></i>Name',
                'label_html' => true,
            ])
            ->add('place', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Place is required.']),
                ],
                'attr' => ['class' => 'form-control'],
                'label' => '<i class="fa fa-map-marker-alt text-primary mr-2"></i>Place',
                'label_html' => true,
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Description is required.']),
                    new Length([
                        'min' => 50,
                        'minMessage' => 'Description must be at least {{ limit }} characters long.',
                    ]),
                ],
                'attr' => ['class' => 'form-control', 'rows' => 3],
                'label' => 'Description',
            ])
            ->add('difficulty', NumberType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Difficulty is required.']),
                    new Range([
                        'min' => 1,
                        'max' => 5,
                        'notInRangeMessage' => 'Difficulty must be between {{ min }} and {{ max }}.',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
                'label' => '<i class="fa fa-star text-primary mr-2"></i>Difficulty (1-5)',
                'label_html' => true,
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a category',
                'constraints' => [
                    new NotBlank(['message' => 'Category is required.']),
                ],
                'attr' => ['class' => 'form-control'],
                'label' => 'Category',
            ])
            ->add('imageFile', VichFileType::class, [
                'required' => false,
                'allow_delete' => true,
                'download_uri' => true,
                'label' => 'Image (optional)',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('date', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activity::class,
        ]);
    }
}