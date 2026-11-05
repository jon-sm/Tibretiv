<?php

declare(strict_types=1);

namespace App\CandidateApplication\Infrastructure\Controller;

use App\CandidateApplication\Application\SubmitCandidateApplication\SubmitCandidateApplication;
use App\CandidateApplication\Domain\AlreadyApplied;
use App\CandidateApplication\Domain\JobPositionNotAcceptingApplications;
use App\CandidateApplication\Infrastructure\Form\ApplyData;
use App\CandidateApplication\Infrastructure\Form\ApplyType;
use App\JobPosition\Application\GetJobPosition\GetJobPosition;
use App\JobPosition\Application\Read\JobPositionView;
use App\JobPosition\Domain\JobPositionNotFound;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class CandidateApplicationSubmissionController extends AbstractController
{
    public const string CONFIRMATION_FLASH = 'application_received';
    public const string ALREADY_APPLIED_MESSAGE = 'You have already applied to this position with this email.';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    /**
     * Template variables:
     *  - position: JobPositionView. When position.isOpen is false, show "Applications closed" instead of the form
     *  - form: FormView of ApplyType. "Already applied" is a root-level form error (form.vars.errors)
     *
     * An invalid submission re-renders with status 422 and the typed values kept.
     */
    #[Route('/positions/{id}', name: 'position_apply', requirements: ['id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function apply(string $id, Request $request): Response
    {
        $position = $this->position($id);
        $data = new ApplyData();
        $form = $this->createForm(ApplyType::class, $data);
        $form->handleRequest($request);

        if ($position->isOpen() && $form->isSubmitted() && $form->isValid()) {
            try {
                $this->commands->dispatch(new SubmitCandidateApplication(
                    positionId: $position->id,
                    name: (string) $data->name,
                    surname: (string) $data->surname,
                    email: (string) $data->email,
                    phone: (string) $data->phone,
                    cv: (string) $data->cv,
                    notes: $data->notes,
                ));

                $this->addFlash(self::CONFIRMATION_FLASH, [
                    'name' => trim((string) $data->name),
                    'positionTitle' => $position->title,
                    'email' => trim((string) $data->email),
                ]);

                return $this->redirectToRoute('application_received');
            } catch (AlreadyApplied) {
                $form->addError(new FormError(self::ALREADY_APPLIED_MESSAGE));
            } catch (JobPositionNotAcceptingApplications) {
                // Closed between loading the page and submitting it.
                $position = $this->position($id);
            }
        }

        return $this->render('candidate_application/apply/apply.html.twig', [
            'position' => $position,
            'form' => $form,
        ]);
    }

    /**
     * Template variables:
     *  - confirmation: {name, positionTitle, email} right after submitting, or null when the
     *    page is refreshed or opened directly (show a link back to the positions instead)
     *
     * The application id is deliberately not in the URL, so the link reveals nothing.
     */
    #[Route('/application-received', name: 'application_received', methods: ['GET'])]
    public function received(Request $request): Response
    {
        $session = $request->getSession();
        \assert($session instanceof \Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface);
        $flashes = $session->getFlashBag()->get(self::CONFIRMATION_FLASH);

        return $this->render('candidate_application/apply/received.html.twig', [
            'confirmation' => $flashes[0] ?? null,
        ]);
    }

    private function position(string $id): JobPositionView
    {
        try {
            $position = $this->queries->ask(new GetJobPosition($id));
        } catch (JobPositionNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        \assert($position instanceof JobPositionView);

        return $position;
    }
}
