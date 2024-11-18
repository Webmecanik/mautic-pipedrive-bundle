<?php

namespace MauticPlugin\PipedriveBundle\EventListener;

use Mautic\IntegrationsBundle\Entity\FieldChangeRepository;
use Mautic\LeadBundle\Event\CompanyMergeEvent;
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

    public static function getSubscribedEvents(): array
    {
        $events = [
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
}
