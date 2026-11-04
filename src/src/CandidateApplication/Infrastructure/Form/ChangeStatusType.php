<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Form;

use App\CandidateApplication\Domain\CandidateApplicationStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangeStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('status', EnumType::class, [
            'label' => 'Status',
            'class' => CandidateApplicationStatus::class,
            'choice_label' => static fn (CandidateApplicationStatus $status): string => ucfirst($status->value),
            'constraints' => [new Assert\NotNull()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'application_status']);
    }
}
