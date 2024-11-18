<?php

namespace MauticPlugin\PipedriveBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\IntegrationsBundle\Entity\ObjectMappingRepository;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\Internal\Object\Company;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\Internal\Object\Contact;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\PipedriveBundle\Integration\Config;
use MauticPlugin\PipedriveBundle\Integration\Pipedrive2Integration;
use MauticPlugin\PipedriveBundle\Sync\Mapping\Manual\MappingManualFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PipedriveController extends CommonController
{
    const INTEGRATION_NAME  = 'Pipedrive2';
    const LEAD_DELETE_EVENT = 'deleted.person';

    const COMPANY_DELETE_EVENT = 'deleted.organization';

    public function __construct(private Config $config, private ObjectMappingRepository $objectMappingRepository, private LeadModel $leadModel, private CompanyModel $companyModel)
    {
    }

    /**
     * @return JsonResponse
     */
    public function webhookAction(Request $request)
    {
        if (!$this->config->isPublished() || !$this->config->isConfigured() || !$this->config->shouldDelete()) {
            return new JsonResponse([
                'error' => 'Pipedrive integration is not published or configured',
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$this->validCredential($request, $this->config->getWebhookUsername(), $this->config->getWebhookPassword())) {
            return new JsonResponse([
                'error' => 'Webhook authorization not set properly',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $params   = json_decode($request->getContent(), true);
        $message  = 'ok';
        $code     = Response::HTTP_OK;
        try {
            switch ($params['event']) {
                case self::LEAD_DELETE_EVENT:
                    if (!$this->config->isEnabledSync(MappingManualFactory::CONTACT_OBJECT)) {
                        $message = 'contact sync disabled';
                        $code    = Response::HTTP_FORBIDDEN;
                        break;
                    }

                    $internalObject = $this->objectMappingRepository->getInternalObject(
                        Pipedrive2Integration::NAME,
                        MappingManualFactory::CONTACT_OBJECT,
                        $params['previous']['id'],
                        Contact::NAME
                    );

                    if ($internalObject) {
                        $internalObjectId   = $internalObject['internal_object_id'];
                        if ($lead = $this->leadModel->getEntity($internalObjectId)) {
                            $this->leadModel->deleteEntity($lead);
                        }
                    }

                    break;
                case self::COMPANY_DELETE_EVENT:
                    if (!$this->config->isEnabledSync(MappingManualFactory::COMPANY_OBJECT)) {
                        $message = 'company sync disabled';
                        $code    = Response::HTTP_FORBIDDEN;
                        break;
                    }
                    $internalObject = $this->objectMappingRepository->getInternalObject(
                        Pipedrive2Integration::NAME,
                        MappingManualFactory::COMPANY_OBJECT,
                        $params['previous']['id'],
                        Company::NAME
                    );

                    if ($internalObject) {
                        $internalObjectId   = $internalObject['internal_object_id'];
                        if ($lead = $this->companyModel->getEntity($internalObjectId)) {
                            $this->companyModel->deleteEntity($lead);
                        }
                    }
                    break;
                default:
                    $message = 'unsupported event';
                    $code    = Response::HTTP_NOT_FOUND;
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $code    = $e->getCode();
        }

        $key = Response::HTTP_OK == $code ? 'status' : 'error';

        return new JsonResponse([$key => $message], $code);
    }

    private function validCredential(Request $request, ?string $webhookUsername, ?string $webhookPassword): bool
    {
        $headers = $request->headers->all();
        if (!isset($headers['authorization']) || !$webhookUsername || !$webhookPassword) {
            return false;
        }

        $basicAuthBase64       = explode(' ', $headers['authorization'][0]);
        $decodedBasicAuth      = base64_decode($basicAuthBase64[1]);
        [$user, $password]     = explode(':', $decodedBasicAuth);

        if ($webhookUsername == $user && $webhookPassword == $password) {
            return true;
        }

        return false;
    }
}
