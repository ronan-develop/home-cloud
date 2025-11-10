<?php

namespace App\Form;

use App\Entity\Photo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PhotoUploadType extends AbstractType
{
    /**
     * Construit le formulaire d'upload de photo.
     *
     * Champs :
     *  - file : fichier image/RAW à uploader (obligatoire, non mappé)
     *  - title : titre optionnel
     *  - description : description optionnelle
     *  - isFavorite : favori ou non
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
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('isFavorite', CheckboxType::class, [
                'label' => 'Favori',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Uploader la photo',
                'attr' => ['class' => 'btn btn-blue w-full mt-4'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Photo::class,
        ]);
    }
}
