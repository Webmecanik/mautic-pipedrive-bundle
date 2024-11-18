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
    public function __construct(FieldChangeRepository $fieldChangeRepo, private ObjectMappingRepository $objectMappingRepository, Config $config)
    {
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

    public function onLeadMerge(LeadMergeEvent $event): void
    {
        $this->objectMappingRepository->deleteObjectMappingForObject([$event->getLoser()->getId()], MappingManualFactory::CONTACT_OBJECT);
    }

    public function onCompanyMerge(CompanyMergeEvent $event): void
    {
        $this->objectMappingRepository->deleteObjectMappingForObject([$event->getLoser()->getId()], MappingManualFactory::COMPANY_OBJECT);
    }
}
