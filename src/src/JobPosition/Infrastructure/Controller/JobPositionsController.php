<?php

declare(strict_types=1);

namespace App\JobPosition\Infrastructure\Controller;

use App\JobPosition\Application\ListJobPositions\ListJobPositions;
use App\Shared\Application\Bus\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class JobPositionsController extends AbstractController
{
    public function __construct(private readonly QueryBus $queries)
    {
    }

    /**
     * Template variables:
     *  - positions: list<JobPositionView>, open only, newest first
     */
    #[Route('/', name: 'positions_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('job_position/positions/index.html.twig', [
            'positions' => $this->queries->ask(new ListJobPositions(onlyOpen: true)),
        ]);
    }
}
