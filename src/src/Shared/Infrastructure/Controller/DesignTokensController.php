<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Living style guide: renders every design token defined in assets/styles/tokens.css.
 */
final class DesignTokensController extends AbstractController
{
    #[Route('/design-tokens', name: 'design_tokens', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('design_tokens/index.html.twig');
    }
}
