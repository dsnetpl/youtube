<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class VimeoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('address', UrlType::class, array(
                'label' => 'Wprowadź poniżej link do filmu na Vimeo:',
                'attr' => ['placeholder' => 'https://vimeo.com/269159472'],
                'constraints' => [
                    new Length(['max' => 100])
                ],
            ))
            ->add('Dalej', SubmitType::class, [
                'attr' => [
                    'class' => 'btn btn-lg btn-default btn-block'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
    }
}
