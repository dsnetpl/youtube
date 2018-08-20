<?php

namespace AppBundle\Form;

use AppBundle\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class YoutubeType extends VideoType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('address', UrlType::class, array(
                'label' => 'Wprowadź poniżej link do filmu na youtube:',
                'attr'  => ['placeholder' => 'https://www.youtube.com/watch?v=...'],
                'constraints' => [
                    new Length(['max' => 100])
                ],
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
    }
}
