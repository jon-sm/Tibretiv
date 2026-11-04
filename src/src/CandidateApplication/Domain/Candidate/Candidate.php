<?php

declare(strict_types=1);

namespace App\CandidateApplication\Domain\Candidate;

/**
 * The person applying. Embedded in the application.
 */
final readonly class Candidate
{
    public string $name;
    public string $surname;
    /** As typed, trimmed and lowercased: shown and used to contact the candidate. */
    public string $email;
    /** Provider-normalised mailbox, for the duplicate check and search. Never shown. */
    public string $emailCanonical;
    public string $phone;

    public function __construct(string $name, string $surname, EmailAddress $email, string $phone)
    {
        foreach (['name' => $name, 'surname' => $surname, 'phone' => $phone] as $field => $value) {
            if ('' === trim($value)) {
                throw new \InvalidArgumentException(\sprintf('A candidate needs a %s.', $field));
            }
        }

        $this->name = trim($name);
        $this->surname = trim($surname);
        $this->email = $email->value;
        $this->emailCanonical = $email->canonical;
        $this->phone = trim($phone);
    }

    public function fullName(): string
    {
        return $this->name.' '.$this->surname;
    }
}
