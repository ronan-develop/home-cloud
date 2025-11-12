<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class FileUploadType extends AbstractType
{
    /**
     * Construit le formulaire d'upload de fichier générique.
     *
     * Ajoute un champ de type fichier obligatoire, non mappé à une entité.
     *
     * @param FormBuilderInterface $builder Le builder de formulaire Symfony
     * @param array $options Les options du formulaire
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Fichier',
                'mapped' => false,
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
