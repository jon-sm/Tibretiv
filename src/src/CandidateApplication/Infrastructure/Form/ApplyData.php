<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Form;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the apply form binds to. Validated here; the domain still guards its own invariants.
 */
final class ApplyData
{
    #[Assert\NotBlank(message: 'Enter your name.')]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Enter your surname.')]
    #[Assert\Length(max: 100)]
    public ?string $surname = null;

    #[Assert\NotBlank(message: 'Enter your email.')]
    #[Assert\Email(message: 'Enter a valid email address.')]
    #[Assert\Length(max: 254)]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Enter your phone number.')]
    #[Assert\Regex(pattern: '/^\+?[0-9][0-9 ().\-]{5,24}$/', message: 'Enter a valid phone number.')]
    public ?string $phone = null;

    #[Assert\NotBlank(message: 'Paste your CV.')]
    #[Assert\Length(max: 50000)]
    public ?string $cv = null;

    #[Assert\Length(max: 2000)]
    public ?string $notes = null;
}
