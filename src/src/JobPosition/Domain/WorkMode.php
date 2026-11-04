<?php

declare(strict_types=1);

namespace App\JobPosition\Domain;

enum WorkMode: string
{
    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case Onsite = 'onsite';
}
