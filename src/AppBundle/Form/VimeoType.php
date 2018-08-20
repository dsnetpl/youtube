<?php

namespace AppBundle\Form;

use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class VimeoType extends VideoType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        parent::buildForm($builder, $options);

        $builder
            ->add('address', UrlType::class, array(
                'label' => 'Wprowadź poniżej link do filmu na Vimeo:',
                'attr' => ['placeholder' => 'https://vimeo.com/269159472'],
                'constraints' => [
                    new Length(['max' => 100])
                ],
            ));

    }

    public function configureOptions(OptionsResolver $resolver)
    {
    }
}
