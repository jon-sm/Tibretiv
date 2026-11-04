<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Controller;

use App\CandidateApplication\Application\ChangeCandidateApplicationStatus\ChangeCandidateApplicationStatus;
use App\CandidateApplication\Application\GetCandidateApplication\GetCandidateApplication;
use App\CandidateApplication\Application\GetEnrichmentStates\GetEnrichmentStates;
use App\CandidateApplication\Application\ListCandidateApplications\ListCandidateApplications;
use App\CandidateApplication\Application\Read\CandidateApplicationsPage;
use App\CandidateApplication\Application\Read\CandidateApplicationView;
use App\CandidateApplication\Application\Read\EnrichmentState;
use App\CandidateApplication\Domain\CandidateApplicationNotFound;
use App\CandidateApplication\Domain\CandidateApplicationStatus;
use App\CandidateApplication\Infrastructure\Form\CandidateApplicationFiltersType;
use App\CandidateApplication\Infrastructure\Form\ChangeStatusType;
use App\JobPosition\Application\ListJobPositions\ListJobPositions;
use App\JobPosition\Application\Read\JobPositionView;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/backoffice')]
final class CandidateApplicationsController extends AbstractController
{
    public const string STATUS_UPDATED_FLASH = 'success';

    /** URL value of the enrichment filter => stored enrichment status */
    private const array ENRICHMENT_FILTER = ['processed' => 'completed', 'pending' => 'pending', 'failed' => 'failed'];

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly FormFactoryInterface $forms,
    ) {
    }

    /**
     * Template variables:
     *  - page: CandidateApplicationsPage (items: list<CandidateApplicationView> with position, total, page, pages, perPage,
     *    hasPendingEnrichment() to decide whether to poll)
     *  - filters: FormView of CandidateApplicationFiltersType (GET, unprefixed names: q, position, status,
     *    enrichment, minScore, from, to)
     *  - hasFilters: bool, whether to show "Clear filters"
     *  - query: array<string, string>, the current filters without the page, to build page links
     *
     * An XMLHttpRequest (X-Requested-With header) gets only the template's "results" block, so the
     * table can be refreshed without reloading the page. Invalid filter values are ignored.
     */
    #[Route('', name: 'backoffice_applications', methods: ['GET'])]
    public function list(Request $request, #[MapQueryParameter] int $page = 1): Response
    {
        $positions = $this->queries->ask(new ListJobPositions());
        \assert(\is_array($positions));

        $filters = $this->forms->createNamed('', CandidateApplicationFiltersType::class, null, [
            'positions' => array_column(array_map(static fn (JobPositionView $p): array => ['title' => $p->title, 'id' => $p->id], $positions), 'id', 'title'),
        ]);
        $filters->handleRequest($request);
        $values = self::validValues($filters);

        $result = $this->queries->ask(new ListCandidateApplications(
            search: $values['q'] ?? null,
            positionId: $values['position'] ?? null,
            status: $values['status'] ?? null,
            enrichmentStatus: self::ENRICHMENT_FILTER[$values['enrichment'] ?? ''] ?? null,
            minScore: $values['minScore'] ?? null,
            appliedFrom: $values['from'] ?? null,
            appliedTo: $values['to'] ?? null,
            page: max(1, $page),
        ));
        \assert($result instanceof CandidateApplicationsPage);

        $query = array_filter(
            array_intersect_key($request->query->all(), $filters->all()),
            static fn (mixed $value): bool => \is_string($value) && '' !== $value,
        );

        $parameters = [
            'page' => $result,
            'filters' => $filters->createView(),
            'hasFilters' => [] !== $values,
            'query' => $query,
        ];

        $template = 'candidate_application/backoffice/applications/list.html.twig';

        return $request->isXmlHttpRequest()
            ? $this->renderBlock($template, 'results', $parameters)
            : $this->render($template, $parameters);
    }

    /**
     * Template variables:
     *  - application: CandidateApplicationView with position
     *  - statusForm: FormView of ChangeStatusType, posting to backoffice_application_status. Submit it
     *    as soon as a value is picked; the query string is kept, so pass the list's filters through it
     *  - back: array<string, string>, the query string to rebuild the list link ("← Applications")
     *
     * A "success" flash ("Status updated") follows a status change.
     */
    #[Route('/{id}', name: 'backoffice_application', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $id, Request $request): Response
    {
        $application = $this->application($id);

        return $this->render('candidate_application/backoffice/applications/show.html.twig', [
            'application' => $application,
            'statusForm' => $this->statusForm($application, $request)->createView(),
            'back' => self::stringQuery($request),
        ]);
    }

    /**
     * Saves the hiring status. Redirects back to the detail, or answers {"status": "..."} as JSON
     * when the request accepts JSON (for a fetch() from the dropdown).
     */
    #[Route('/{id}/status', name: 'backoffice_application_status', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function changeStatus(string $id, Request $request): Response
    {
        $application = $this->application($id);
        $form = $this->statusForm($application, $request);
        $form->handleRequest($request);
        $wantsJson = 'json' === $request->getPreferredFormat();

        if (!$form->isSubmitted() || !$form->isValid()) {
            if ($wantsJson) {
                return $this->json(['errors' => array_map(
                    static fn ($error): string => $error->getMessage(),
                    iterator_to_array($form->getErrors(true)),
                )], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return $this->render('candidate_application/backoffice/applications/show.html.twig', [
                'application' => $application,
                'statusForm' => $form->createView(),
                'back' => self::stringQuery($request),
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $status = $form->get('status')->getData();
        \assert($status instanceof CandidateApplicationStatus);

        $this->commands->dispatch(new ChangeCandidateApplicationStatus($id, $status->value));

        if ($wantsJson) {
            return $this->json(['status' => $status->value]);
        }

        $this->addFlash(self::STATUS_UPDATED_FLASH, 'Status updated');

        return $this->redirectToRoute('backoffice_application', ['id' => $id] + self::stringQuery($request));
    }

    /**
     * Polling: GET ?ids[]=…&ids[]=… answers
     * [{"id", "status": "pending|completed|failed", "score", "summary", "enrichedAt": ISO 8601|null}].
     *
     * @param array<mixed> $ids
     */
    #[Route('/enrichment', name: 'backoffice_enrichment_states', methods: ['GET'])]
    public function enrichmentStates(#[MapQueryParameter] array $ids = []): JsonResponse
    {
        $states = $this->queries->ask(new GetEnrichmentStates(array_values(array_filter($ids, \is_string(...)))));
        \assert(\is_array($states));

        return $this->json(array_map(static fn (EnrichmentState $state): array => [
            'id' => $state->applicationId,
            'status' => $state->status,
            'score' => $state->score,
            'summary' => $state->summary,
            'enrichedAt' => $state->enrichedAt?->format(\DATE_ATOM),
        ], $states));
    }

    private function application(string $id): CandidateApplicationView
    {
        try {
            $application = $this->queries->ask(new GetCandidateApplication($id));
        } catch (CandidateApplicationNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        \assert($application instanceof CandidateApplicationView);

        return $application;
    }

    private function statusForm(CandidateApplicationView $application, Request $request): FormInterface
    {
        return $this->createForm(ChangeStatusType::class, ['status' => CandidateApplicationStatus::from($application->status)], [
            'action' => $this->generateUrl('backoffice_application_status', ['id' => $application->id] + self::stringQuery($request)),
        ]);
    }

    /**
     * The filters that were submitted and are valid; invalid ones are ignored.
     *
     * @return array<string, mixed>
     */
    private static function validValues(FormInterface $filters): array
    {
        if (!$filters->isSubmitted()) {
            return [];
        }

        $values = [];
        foreach ($filters->all() as $name => $field) {
            $value = $field->getData();

            if ($field->isValid() && null !== $value && '' !== $value) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /** @return array<string, string> */
    private static function stringQuery(Request $request): array
    {
        return array_filter($request->query->all(), static fn (mixed $value): bool => \is_string($value) && '' !== $value);
    }
}
