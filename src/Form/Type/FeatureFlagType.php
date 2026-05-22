<?php

namespace Em411\SyliusFeatureFlagPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class FeatureFlagType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'em411_sylius_feature_flag.form.code',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
            ->add('value', TextareaType::class, [
                'label' => 'em411_sylius_feature_flag.form.value',
                'required' => false,
            ])
            ->add('description', TextType::class, [
                'label' => 'em411_sylius_feature_flag.form.description',
                'required' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'em411_sylius_feature_flag_feature_flag';
    }
}
