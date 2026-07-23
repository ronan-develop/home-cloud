<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\BroadcastMessageInput;
use App\Interface\BroadcastTargetProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BroadcastMessageFormType extends AbstractType
{
    public function __construct(
        private readonly BroadcastTargetProviderInterface $targetProvider,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $instanceChoices = ['Toutes les instances' => null];
        foreach (array_keys($this->targetProvider->getAllTargets()) as $instance) {
            $instanceChoices[$instance] = $instance;
        }

        $builder
            ->add('subject', TextType::class, ['label' => 'Sujet'])
            ->add('body', TextareaType::class, ['label' => 'Message'])
            ->add('targetInstance', ChoiceType::class, [
                'label'    => 'Cible',
                'choices'  => $instanceChoices,
                'required' => false,
            ])
            ->add('dryRun', CheckboxType::class, [
                'label'    => "Essai à blanc (aucun email réel envoyé)",
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => BroadcastMessageInput::class]);
    }
}
