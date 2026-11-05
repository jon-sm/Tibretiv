<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Controller\Api;

use App\CandidateApplication\Application\Read\CandidateApplicationsPage;
use App\CandidateApplication\Application\Read\CandidateApplicationView;
use App\Shared\Application\Bus\Query\QueryBus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[Route('/api')]
final readonly class CandidateApplicationsApiController
{
    public function __construct(
        private QueryBus $queries,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * Applications, newest first, 20 per page, filtered by the query string (all optional):
     * q, position (id), status, enrichment (processed | pending | failed), minScore (0–100),
     * from / to (Y-m-d, Europe/Madrid days, inclusive), page (past the last one, the last one).
     *
     * 200: {"items": [{"id", "status", "candidate": {"name", "surname"}, "position": {"id", "title"} | null,
     *       "enrichment": {"status": "pending|completed|failed", "score": int|null}, "appliedAt": ISO 8601 UTC}],
     *       "pagination": {"page", "pages", "perPage", "total"}}
     * 422: {"errors": {"<parameter>": "<message>"}}, the first problem of each invalid parameter.
     */
    #[Route('/applications', name: 'api_applications', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $parameters = ListCandidateApplicationsRequest::fromQuery($request->query->all());

        $violations = $this->validator->validate($parameters);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
            }

            return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $page = $this->queries->ask($parameters->toQuery());
        \assert($page instanceof CandidateApplicationsPage);

        return new JsonResponse([
            'items' => array_map(self::item(...), $page->items),
            'pagination' => [
                'page' => $page->page,
                'pages' => $page->pages,
                'perPage' => $page->perPage,
                'total' => $page->total,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private static function item(CandidateApplicationView $application): array
    {
        return [
            'id' => $application->id,
            'status' => $application->status,
            'candidate' => ['name' => $application->name, 'surname' => $application->surname],
            'position' => null === $application->position
                ? null
                : ['id' => $application->position->id, 'title' => $application->position->title],
            'enrichment' => ['status' => $application->enrichmentStatus, 'score' => $application->score],
            'appliedAt' => $application->appliedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
