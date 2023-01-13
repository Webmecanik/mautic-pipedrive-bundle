<?php

declare(strict_types=1);

namespace MauticPlugin\PipedriveBundle\Sync\Sync\CompanyRelation;

use Mautic\IntegrationsBundle\Entity\ObjectMappingRepository;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\InputOptionsDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\ObjectIdsDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Order\ObjectChangeDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Order\OrderDAO;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\Internal\Object\Company;
use Mautic\IntegrationsBundle\Sync\SyncService\SyncService;
use Mautic\LeadBundle\Model\CompanyModel;

class CompanyRelationSyncToIntegration
{
    /**
     * @var CompanyModel
     */
    private $companyModel;

    /**
     * @var SyncService
     */
    private $syncService;

    /**
     * @param ObjectChangeDAO[] $objects
     */
    private $objects = [];

    /**
     * @var ObjectMappingRepository
     */
    private $objectMappingRepository;

    /**
     * @var array
     */
    private $companiesForContactId;

    /**
     * CompanyRelationSync constructor.
     */
    public function __construct(CompanyModel $companyModel, SyncService $syncService, ObjectMappingRepository $objectMappingRepository)
    {
        $this->companyModel            = $companyModel;
        $this->syncService             = $syncService;
        $this->objectMappingRepository = $objectMappingRepository;
    }

    public function sync(ObjectChangeDAO $objectChangeDAO, string $integrationCompanyObject, OrderDAO $orderDAO = null, array $options = [])
    {
        $this->fetchCompaniesFromObject($objectChangeDAO);

        $contactId = $objectChangeDAO->getMappedObjectId();

        if (!isset($this->companiesForContactId[$contactId])) {
            return null;
        }

        $primaryCompany = $this->getPrimaryCompany($this->companiesForContactId[$contactId]);
        if (!isset($primaryCompany['id'])) {
            return null;
        }

        $internalObjectId = $primaryCompany['id'];

        $internalObject = $this->objectMappingRepository->getIntegrationObject(
            $objectChangeDAO->getIntegration(),
            Company::NAME,
            $internalObjectId,
            $integrationCompanyObject
        );

        if (!empty($internalObject)) {
            return $internalObject['integration_object_id'];
        }

        $mauticObjectIds = new ObjectIdsDAO();
        $mauticObjectIds->addObjectId(Company::NAME, (string) $internalObjectId);

        $inputOptions = new InputOptionsDAO(
            [
                'integration'      => $objectChangeDAO->getIntegration(),
                'disable-pull'     => true,
                'mautic-object-id' => $mauticObjectIds,
                'options'          => $options,
            ]
        );

        $this->syncService->processIntegrationSync($inputOptions);

        // take it If it was synced
        if ($orderDAO) {
            foreach ($orderDAO->getObjectMappings() as $objectMapping) {
                if ($objectMapping->getIntegrationObjectName() === $integrationCompanyObject && $objectMapping->getInternalObjectId() == $internalObjectId) {
                    return $objectMapping->getIntegrationObjectId();
                }
            }
        }

        $internalObject = $this->objectMappingRepository->getIntegrationObject(
            $objectChangeDAO->getIntegration(),
            Company::NAME,
            $primaryCompany['id'],
            $integrationCompanyObject
        );

        if (!empty($internalObject)) {
            return $internalObject['integration_object_id'];
        }

        return null;
    }

    private function fetchCompaniesFromObject(ObjectChangeDAO $object)
    {
        $this->fetchCompaniesFromObjects([$object]);
    }

    /**
     * @param ObjectChangeDAO[] $objects
     */
    public function fetchCompaniesFromObjects(array $objects)
    {
        $this->objects = $objects;

        $contactIds       = $this->getContactIdsToFetchCompanies();

        if (!empty($contactIds)) {
            $contactCompanies = $this->companyModel->getRepository()->getCompaniesForContacts($contactIds);
            foreach ($contactIds as $contactId) {
                if (!isset($contactCompanies[$contactId])) {
                    $this->companiesForContactId[$contactId] = [];
                } else {
                    $this->companiesForContactId[$contactId] = $contactCompanies[$contactId];
                }
            }
        }
    }

    private function getContactIdsToFetchCompanies(): array
    {
        $companiesForContacts = $this->companiesForContactId;

        return array_filter(
            $this->getContactIds(),
            function ($contactId) use ($companiesForContacts) {
                if (!isset($companiesForContacts[$contactId])) {
                    return $contactId;
                }
            }
        );
    }

    private function getContactIds(): array
    {
        return array_map(
            function (ObjectChangeDAO $object) {
                return $object->getMappedObjectId();
            },
            $this->objects
        );
    }

    private function getPrimaryCompany(array $contactCompanies)
    {
        $primaryCompany = null;
        $company        = null;

        foreach ($contactCompanies as $company) {
            if (isset($company['is_primary']) && 1 == $company['is_primary']) {
                $primaryCompany = $company;
            }
        }

        if (!empty($primaryCompany)) {
            $primaryCompany = $contactCompanies[0];
        }

        return $primaryCompany;
    }

    public function getCompanyIntegrationId(string $companyName)
    {
        $company = $this->companyModel->getRepository()->findOneBy(['name' => $companyName]);
        if (!$company) {
            return null;
        }

        $mappedObject = $this->objectMappingRepository->findOneBy(['internalObjectId' => $company->getId()]);
        if (!$mappedObject) {
            return null;
        }

        return $mappedObject->getIntegrationObjectId();
    }
}
