<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Form;

use App\CandidateApplication\Domain\CandidateApplicationStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The list's search and filters. Only rendered, never submitted: the browser reads the fields and
 * queries GET /api/applications with the same, unprefixed names (?q=jane&status=received&page=2),
 * and the API validates them (Controller\Api\ListCandidateApplicationsRequest).
 */
final class CandidateApplicationFiltersType extends AbstractType
{
    /** Labels as the UI shows them => enrichment status. */
    public const array ENRICHMENT_CHOICES = ['Processed' => 'processed', 'Pending' => 'pending', 'Failed' => 'failed'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('q', SearchType::class, [
                'label' => 'Search',
                'attr' => ['placeholder' => 'Search name, surname or email…', 'maxlength' => 200],
            ])
            ->add('position', ChoiceType::class, [
                'label' => 'Position',
                'placeholder' => 'All',
                'choices' => $options['positions'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'placeholder' => 'All',
                'choices' => array_combine(
                    array_map(static fn (CandidateApplicationStatus $s): string => ucfirst($s->value), CandidateApplicationStatus::cases()),
                    array_map(static fn (CandidateApplicationStatus $s): string => $s->value, CandidateApplicationStatus::cases()),
                ),
            ])
            ->add('enrichment', ChoiceType::class, [
                'label' => 'Enrichment',
                'placeholder' => 'All',
                'choices' => self::ENRICHMENT_CHOICES,
            ])
            ->add('minScore', IntegerType::class, [
                'label' => 'Min score',
                'attr' => ['min' => 0, 'max' => 100],
            ])
            ->add('from', DateType::class, [
                'label' => 'Applied from',
                'widget' => 'single_text',
                'input' => 'string',
            ])
            ->add('to', DateType::class, [
                'label' => 'Applied to',
                'widget' => 'single_text',
                'input' => 'string',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
            'required' => false,
            'positions' => [],
        ]);
        $resolver->setAllowedTypes('positions', 'array');
        $resolver->setInfo('positions', 'Position title => position id, for the position filter.');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
