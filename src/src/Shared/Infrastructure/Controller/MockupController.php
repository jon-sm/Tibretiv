<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Static, high-fidelity mockups of every view, with fake data written in the templates.
 * Nothing is wired to the domain: these become the real templates once the backend exists.
 */
final class MockupController extends AbstractController
{
    /** Page => the states it can show, the first being the default. */
    public const array PAGES = [
        'index' => ['default'],
        'positions' => ['default', 'empty'],
        'apply' => ['default', 'errors', 'duplicate', 'submitting', 'closed'],
        'confirmation' => ['default', 'expired'],
        'applications' => ['default', 'filtered', 'empty', 'no-results'],
        'application' => ['completed', 'pending', 'failed'],
    ];

    #[Route('/mockups/{page}', name: 'mockup', requirements: ['page' => 'index|positions|apply|confirmation|applications|application'], defaults: ['page' => 'index'], methods: ['GET'])]
    public function __invoke(string $page, #[MapQueryParameter] ?string $state = null): Response
    {
        $states = self::PAGES[$page];

        return $this->render(\sprintf('mockups/%s.html.twig', $page), [
            'state' => \in_array($state, $states, true) ? $state : $states[0],
            'states' => $states,
            'page' => $page,
            'pages' => self::PAGES,
        ]);
    }
}
