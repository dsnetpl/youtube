<?php

namespace AppBundle\Form;

use AppBundle\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VideoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('category', EntityType::class, [
                'label' => 'Kategoria',
                'class' => Category::class,
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'class' => 'selectpicker',
                ]
            ])
            ->add('process', SubmitType::class, [
                'label' => 'Dalej',
                'attr' => [
                    'class' => 'btn btn-lg btn-primary btn-block'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
    }
}
