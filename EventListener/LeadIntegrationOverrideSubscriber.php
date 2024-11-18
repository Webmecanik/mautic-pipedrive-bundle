<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\EventListener;

use Mautic\IntegrationsBundle\Entity\FieldChangeRepository;
use Mautic\IntegrationsBundle\Entity\ObjectMappingRepository;
use Mautic\IntegrationsBundle\EventListener\LeadSubscriber;
use Mautic\IntegrationsBundle\Helper\SyncIntegrationsHelper;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange;
use Mautic\IntegrationsBundle\Sync\VariableExpresser\VariableExpresserHelperInterface;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\PipedriveBundle\Integration\Config;

class LeadIntegrationOverrideSubscriber extends LeadSubscriber
{
    public function __construct(
        private FieldChangeRepository $fieldChangeRepo,
        private ObjectMappingRepository $objectMappingRepository,
        private VariableExpresserHelperInterface $variableExpressor,
        private SyncIntegrationsHelper $syncIntegrationsHelper,
        private Config $config,
        private \MauticPlugin\PipedriveBundle\Repository\ObjectMappingRepository $pipedriveObjectMappingRepository
    ) {
        parent::__construct($fieldChangeRepo, $objectMappingRepository, $variableExpressor, $syncIntegrationsHelper);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LEAD_POST_SAVE      => ['onLeadPostSave', 0],
            LeadEvents::LEAD_POST_DELETE    => ['onLeadPostDelete', 255],
            LeadEvents::COMPANY_POST_SAVE   => ['onCompanyPostSave', 0],
            LeadEvents::COMPANY_POST_DELETE => ['onCompanyPostDelete', 255],
        ];
    }

    public function onLeadPostDelete(LeadEvent $event): void
    {
        if ($event->getLead()->isAnonymous()) {
            return;
        }

        if (!$this->config->shouldDelete()) {
            parent::onLeadPostDelete($event);

            return;
        }

        $deleteId =  (int) $event->getLead()->deletedId;
        $this->fieldChangeRepo->deleteEntitiesForObject((int) $deleteId, Lead::class);
        $this->pipedriveObjectMappingRepository->markAsDeleted(MauticSyncDataExchange::OBJECT_CONTACT,
            $deleteId
        );
    }

    public function onCompanyPostDelete(CompanyEvent $event): void
    {
        if (!$this->config->shouldDelete()) {
            parent::onCompanyPostDelete($event);

            return;
        }

        $deleteId = $event->getCompany()->deletedId;
        $this->fieldChangeRepo->deleteEntitiesForObject((int) $deleteId, Company::class);

        $this->pipedriveObjectMappingRepository->markAsDeleted(MauticSyncDataExchange::OBJECT_COMPANY,
            $deleteId
        );
    }
}
