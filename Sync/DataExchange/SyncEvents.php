<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Sync\DataExchange;

use Doctrine\ORM\EntityManager;
use Mautic\IntegrationsBundle\Sync\Logger\DebugLogger;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\PipedriveBundle\Connection\Activities;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use MauticPlugin\PipedriveBundle\Repository\ObjectMappingRepository;
use Psr\Log\LogLevel;

class SyncEvents
{
    private Activities $activities;

    private Config $config;

    private EntityManager $em;

    private LeadModel $leadModel;

    private ObjectMappingRepository $objectMappingRepository;

    public function __construct(ObjectMappingRepository $objectMappingRepository, LeadModel $leadModel, EntityManager $em, Config $config, Activities $activities)
    {
        $this->objectMappingRepository = $objectMappingRepository;
        $this->leadModel               = $leadModel;
        $this->em                      = $em;
        $this->config                  = $config;
        $this->activities              = $activities;
    }

    public function sync(?\DateTimeInterface $startDateTime, ?\DateTimeInterface $endDateTime)
    {
        $allContacts       = $this->objectMappingRepository->getAllContacts();
        $idsToSyncActivity = array_combine(
            array_column($allContacts, 'internal_object_id'),
            array_column($allContacts, 'integration_object_id')
        );
        $activitiesForContacts = $this->getContactsActivities($startDateTime, $endDateTime, $idsToSyncActivity);
        DebugLogger::log(
            Pipedrive2Integration::NAME,
            sprintf('Sync activities for %s contacts', count($activitiesForContacts)),
            __CLASS__.':'.__FUNCTION__,
            [],
            LogLevel::INFO
        );
        foreach ($activitiesForContacts as $integrationObjectId => $activitiesForContact) {
            if (empty($activitiesForContact)) {
                continue;
            }
            DebugLogger::log(
                Pipedrive2Integration::NAME,
                sprintf('Sync %s activities for integration ID %s', count($activitiesForContact), $integrationObjectId),
                __CLASS__.':'.__FUNCTION__,
                [],
                LogLevel::INFO
            );
            foreach ($activitiesForContact as $activityForContact) {
                $data              = [];
                $data['subject']   = $activityForContact['name'];
                $data['done']      = 1;
                $data['type']      = $activityForContact['event'];
                $data['person_id'] = $integrationObjectId;
                /** @var \DateTime $dateAdded */
                $dateAdded         = $activityForContact['dateAdded'];
                $dateAdded->setTimezone(new \DateTimeZone('UTC'));
                $data['due_date']  = $dateAdded->format('Y-m-d');
                $data['due_time']  = $dateAdded->format('H:i:s');
                $data['note']      = $activityForContact['description'];
                $this->activities->addActivity($data);
            }
        }
    }

    /**
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    protected function getContactsActivities(?\DateTimeInterface $startDateTime, ?\DateTimeInterface $endDateTime, array $ids): array
    {
        $filters = [
            'search'        => '',
            'includeEvents' => $this->config->getActivities(),
            'excludeEvents' => [],
        ];
        if ($startDateTime) {
            $filters['dateFrom'] = $startDateTime->format('Y-m-d H:i:s');
            if ($endDateTime) {
                $filters['dateTo']   = $endDateTime->format('Y-m-d H:i:s');
            }
        }
        $contactActivity = [];
        foreach ($ids as $internalId => $integrationId) {
            $contact = $this->leadModel->getEntity($internalId);
            if (!$contact) {
                continue;
            }
            $i        = 0;
            $activity = [];
            $page     = 1;
            while (true) {
                $engagements = $this->leadModel->getEngagements($contact, $filters, null, $page, 100, false);
                $events      = $engagements[0]['events'];
                if (empty($events)) {
                    break;
                }

                // inject lead into events
                foreach ($events as $event) {
                    $link  = '';
                    $label = (isset($event['eventLabel'])) ? $event['eventLabel'] : $event['eventType'];
                    if (is_array($label)) {
                        $link  = $label['href'];
                        $label = $label['label'];
                    }

                    $activity[$i]['event']       = $event['event'];
                    $activity[$i]['eventType']   = $event['eventType'];
                    $activity[$i]['name']        = $event['eventType'].' - '.$label;
                    $activity[$i]['description'] = $link;
                    $activity[$i]['dateAdded']   = $event['timestamp'];

                    // Just to keep congruent formatting with the three above
                    $id = str_replace(' ', '', ucwords(str_replace('.', ' ', $event['eventId'])));

                    $activity[$i]['id'] = $id;
                    ++$i;
                }

                ++$page;

                // Lots of entities will be loaded into memory while compiling these events so let's prevent memory overload by clearing the EM
                $entityToNotDetach = ['Mautic\PluginBundle\Entity\Integration', 'Mautic\PluginBundle\Entity\Plugin'];
                $loadedEntities    = $this->em->getUnitOfWork()->getIdentityMap();
                foreach ($loadedEntities as $name => $loadedEntity) {
                    if (!in_array($name, $entityToNotDetach)) {
                        $this->em->clear($name);
                    }
                }
            }

            if (empty($activity)) {
                continue;
            }

            $contactActivity[$integrationId] = $activity;

            unset($activity);
        }

        return $contactActivity;
    }
}
