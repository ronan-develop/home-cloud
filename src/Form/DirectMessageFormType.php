<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\DirectMessageInput;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DirectMessageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipient', EntityType::class, [
                'label'        => 'Destinataire',
                'class'        => User::class,
                'choice_label' => 'email',
            ])
            ->add('subject', TextType::class, ['label' => 'Sujet', 'empty_data' => ''])
            ->add('body', TextareaType::class, ['label' => 'Message', 'empty_data' => ''])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DirectMessageInput::class]);
    }
}
