<?php
declare(strict_types = 1);

namespace App\Action\Account\Meetup;

use App\Entity\Meetup;
use App\Service\Location\FindLocationByUuidInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\RedirectResponse;
use Zend\Expressive\Helper\UrlHelper;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Form\FormInterface;
use Zend\Stratigility\MiddlewareInterface;

final class AddMeetupAction implements MiddlewareInterface
{
    /**
     * @var TemplateRendererInterface
     */
    private $templateRenderer;

    /**
     * @var FormInterface
     */
    private $form;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var FindLocationByUuidInterface
     */
    private $findLocationByUuid;

    /**
     * @var UrlHelper
     */
    private $urlHelper;

    public function __construct(
        TemplateRendererInterface $templateRenderer,
        FormInterface $form,
        EntityManagerInterface $entityManager,
        FindLocationByUuidInterface $findLocationByUuid,
        UrlHelper $urlHelper
    ) {
        $this->templateRenderer = $templateRenderer;
        $this->form = $form;
        $this->entityManager = $entityManager;
        $this->findLocationByUuid = $findLocationByUuid;
        $this->urlHelper = $urlHelper;
    }

    public function __invoke(Request $request, Response $response, callable $next = null) : Response
    {
        if ('POST' === strtoupper($request->getMethod())) {
            $parsedBody = $request->getParsedBody();
            $this->form->setData($parsedBody);

            if ($this->form->isValid()) {
                $data = $this->form->getData();
                $meetup = $this->entityManager->transactional(function () use ($data) {
                    $meetup = Meetup::fromStandardMeetup(
                        new \DateTimeImmutable($data['from']),
                        new \DateTimeImmutable($data['to']),
                        $this->findLocationByUuid->__invoke(Uuid::fromString($data['location']))
                    );
                    $this->entityManager->persist($meetup);
                    return $meetup;
                });
                return new RedirectResponse($this->urlHelper->generate('account-meetup-view', [
                    'uuid' => $meetup->getId(),
                ]));
            }
        }
        return new HtmlResponse($this->templateRenderer->render('account::meetup/edit', [
            'title' => 'Add a new meetup',
            'form' => $this->form,
        ]));
    }
}
