<?php

namespace App\Form;

use App\Entity\Tag;
use App\Entity\User;
use App\Entity\Photo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class UploadablePhotoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', TextType::class, [
            'label' => 'Url :',
            'attr' => ['class' => 'w-full md:w-full px-3 mb-6 md:mb-0 shadow-sm'],
            ])
            ->add('tags', EntityType::class, [
            'class' => Tag::class,
            'choice_label' => 'id',
            'multiple' => true,
            'attr' => ['class' => 'form-multiselect'],
            ])
            ->add('Photos', FileType::class, [
            'label' => 'Photo (JPEG, PNG, GIF, TIFF, BMP, WEBP, SVG, RAW, HEIC, HEIF, AVIF)',
            'mapped' => false,
            'required' => false,
            'multiple' => true,
            'attr' => ['class' => 'form-input'],
            ])
            ->add('submit', SubmitType::class, [
            'label' => 'Submit',
            'attr' => ['class' => 'form-submit'],
            ]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Photo::class,
        ]);
    }
}
