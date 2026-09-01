<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\DirectMessageInput;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Le champ recipient exclut les comptes invités : un invité est présent à
 * l'invitation d'un user propriétaire, pas de l'admin de l'instance, qui
 * n'a pas à connaître son identité (email) via ce formulaire (cf. #391,
 * même principe que #389 pour /admin/users).
 */
final class DirectMessageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipient', EntityType::class, [
                'label'        => 'Destinataire',
                'class'        => User::class,
                'choice_label' => 'email',
                'query_builder' => static fn (UserRepository $repository): QueryBuilder => $repository
                    ->createQueryBuilder('u')
                    ->andWhere('u.accountType = :accountType')
                    ->setParameter('accountType', User::ACCOUNT_TYPE_FULL),
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
