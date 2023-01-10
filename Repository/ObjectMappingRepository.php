<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;

class ObjectMappingRepository
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getIntegrationIdsToDelete(string $objectName): array
    {
        $qb = $this->entityManager->getConnection()->createQueryBuilder();
        $qb->select('sm.integration_object_id, sm.internal_object_id')
            ->from(MAUTIC_TABLE_PREFIX.'sync_object_mapping', 'sm')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('sm.integration', ':integration'),
                    $qb->expr()->eq('sm.integration_object_name', ':integrationObjectName'),
                    $qb->expr()->eq('sm.is_deleted', 1)
                )
            )
            ->setParameter('integration', Pipedrive2Integration::NAME)
            ->setParameter('integrationObjectName', $objectName);

        return $qb->execute()->fetchAllAssociative();
    }

    public function getInternalIdsToDelete(string $objectName, array $allIntegrationIds): ?array
    {
        $qb = $this->entityManager->getConnection()->createQueryBuilder();
        $qb->select('sm.internal_object_id, sm.integration_object_id')
            ->from(MAUTIC_TABLE_PREFIX.'sync_object_mapping', 'sm')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('sm.integration', ':integration'),
                    $qb->expr()->eq('sm.integration_object_name', ':integrationObjectName'),
                )
            )
            ->setParameter('integration', Pipedrive2Integration::NAME)
            ->setParameter('integrationObjectName', $objectName);

        if (!empty($allIntegrationIds)) {
            $qb->andWhere(
                $qb->expr()->notIn('sm.integration_object_id', ':integrationObjectIds')
            )
                ->setParameter('integrationObjectIds', $allIntegrationIds, Connection::PARAM_INT_ARRAY);
        }

        return $qb->execute()->fetchAllAssociative();
    }

    public function deleteObjectMappingForObject(array $objectIds, string $objectName): void
    {
        $qb = $this->entityManager->getConnection()->createQueryBuilder();
        $qb
            ->delete(MAUTIC_TABLE_PREFIX.'sync_object_mapping');
        $qb->andWhere(
            $qb->expr()->eq('integration', ':integration'),
            $qb->expr()->eq('integration_object_name', ':objectName'),
            $qb->expr()->in('internal_object_id', ':objectIds'),
        );
        $qb->setParameter('integration', Pipedrive2Integration::NAME)
            ->setParameter('objectName', $objectName)
            ->setParameter('objectIds', $objectIds, Connection::PARAM_INT_ARRAY);

        $qb
            ->execute();
    }

    public function markAsDeleted(string $objectName, $objectIds): int
    {
        $qb = $this->entityManager->getConnection()->createQueryBuilder();

        $qb->update(MAUTIC_TABLE_PREFIX.'sync_object_mapping', 'm')
            ->set('is_deleted', 1)
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('m.integration', ':integration'),
                    $qb->expr()->eq('m.internal_object_name', ':objectName')
                )
            )
            ->setParameter('integration', Pipedrive2Integration::NAME)
            ->setParameter('objectName', $objectName);

        if (is_array($objectIds)) {
            $qb->setParameter('objectId', $objectIds, Connection::PARAM_STR_ARRAY);
            $qb->andWhere($qb->expr()->in('m.internal_object_id', ':objectId'));
        } else {
            $qb->setParameter('objectId', $objectIds);
            $qb->andWhere($qb->expr()->eq('m.internal_object_id', ':objectId'));
        }

        return $qb->execute();
    }

    public function getAllContacts(): array
    {
        $qb = $this->entityManager->getConnection()->createQueryBuilder();
        $qb->select('DISTINCT sm.internal_object_id, sm.integration_object_id')
            ->from(MAUTIC_TABLE_PREFIX.'sync_object_mapping', 'sm')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('sm.integration', ':integration'),
                    $qb->expr()->eq('sm.integration_object_name', ':integrationObjectName'),
                )
            )
            ->setParameter('integration', Pipedrive2Integration::NAME)
            ->setParameter('integrationObjectName', MappingManualFactory::CONTACT_OBJECT);

        return $qb->execute()->fetchAllAssociative();
    }
}
