<?php

namespace MauticPlugin\PipedriveBundle\EventListener;

use Mautic\IntegrationsBundle\Entity\FieldChangeRepository;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\Event\CompanyMergeEvent;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\Event\LeadMergeEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Repository\ObjectMappingRepository;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LeadSubscriber implements EventSubscriberInterface
{
    private Config $config;

    private FieldChangeRepository $fieldChangeRepo;

    private ObjectMappingRepository $objectMappingRepository;

    public function __construct(FieldChangeRepository $fieldChangeRepo, ObjectMappingRepository $objectMappingRepository, Config $config)
    {
        $this->fieldChangeRepo         = $fieldChangeRepo;
        $this->objectMappingRepository = $objectMappingRepository;
        $this->config                  = $config;
    }

    public static function getSubscribedEvents()
    {
        $events = [
            LeadEvents::LEAD_POST_DELETE    => ['onLeadPostDelete', 256],
            LeadEvents::COMPANY_POST_DELETE => ['onCompanyPostDelete', 256],
            LeadEvents::LEAD_POST_MERGE     => ['onLeadMerge', 0],
        ];

        if (defined('LeadEvents::COMPANY_POST_MERGE')) {
            $events[LeadEvents::COMPANY_POST_MERGE] = ['onCompanyMerge', 0];
        }

        return $events;
    }

    public function onLeadMerge(LeadMergeEvent $event)
    {
        $this->objectMappingRepository->deleteObjectMappingForObject([$event->getLoser()->getId()], MappingManualFactory::CONTACT_OBJECT);
    }

    public function onCompanyMerge(CompanyMergeEvent $event)
    {
        $this->objectMappingRepository->deleteObjectMappingForObject([$event->getLoser()->getId()], MappingManualFactory::COMPANY_OBJECT);
    }

    public function onLeadPostDelete(LeadEvent $event): void
    {
        if (!$this->config->shouldDelete()) {
            return;
        }

        if ($event->getLead()->isAnonymous()) {
            return;
        }

        $deleteId = (int) $event->getLead()->deletedId;
        $this->fieldChangeRepo->deleteEntitiesForObject($deleteId, Lead::class);
        $this->objectMappingRepository->markAsDeleted(MauticSyncDataExchange::OBJECT_CONTACT,
            $deleteId
        );

        $event->stopPropagation();
    }

    public function onCompanyPostDelete(CompanyEvent $event): void
    {
        if (!$this->config->shouldDelete()) {
            return;
        }

        $deleteId = (int) $event->getCompany()->deletedId;
        $this->fieldChangeRepo->deleteEntitiesForObject($deleteId, Company::class);

        $this->objectMappingRepository->markAsDeleted(MauticSyncDataExchange::OBJECT_COMPANY,
            $deleteId
        );

        $event->stopPropagation();
    }
}
