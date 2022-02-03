<?php

declare(strict_types=1);

namespace AdyenPayment\Subscriber;

use Doctrine\ORM\EntityRepository;
use Enlight\Event\SubscriberInterface;
use Enlight_Components_Session_Namespace;

final class EnrichUserPreferenceSubscriber implements SubscriberInterface
{
    private Enlight_Components_Session_Namespace $session;
    private EntityRepository $userPreferenceRepository;

    public function __construct(
        Enlight_Components_Session_Namespace $session,
        EntityRepository $userPreferenceRepository
    ) {
        $this->session = $session;
        $this->userPreferenceRepository = $userPreferenceRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // inject in the view as early as possible to get the info in the other subscribers
            'Enlight_Controller_Action_PostDispatch_Frontend_Account' => ['__invoke', -99999],
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => ['__invoke', -99999],
        ];
    }

    public function __invoke(\Enlight_Controller_ActionEventArgs $args): void
    {
        $userId = $this->session->get('sUserId');
        if (null === $userId) {
            return;
        }

        $userPreference = $this->userPreferenceRepository->findOneBy(['userId' => $userId]);
        if (null === $userPreference) {
            return;
        }

        $args->getSubject()->View()->assign('adyenUserPreference', $userPreference->toArray());
    }
}
