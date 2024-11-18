<?php

namespace MauticPlugin\PipedriveBundle\Enum;

final class SettingsEnum
{
    public const ADD_TIME            = 'add_time';

    /**
     * Auth endpoints.
     */
    public const PIPEDRIVE_INSTANCE_NAME_FIELD    = 'instance_name';
    public const PIPEDRIVE_AUTH_URL               = 'https://oauth.pipedrive.com/oauth/authorize';
    public const PIPEDRIVE_TOKEN_EXCHANGE_URL     = 'https://oauth.pipedrive.com/oauth/token';

    /**
     * Persons endpoints.
     */
    public const PIPEDRIVE_PERSON_FIELD_ENDPOINT = 'personFields';
    public const PIPEDRIVE_PERSON_ENDPOINT       = 'persons';

    /**
     * Organization endpoints.
     */
    public const PIPEDRIVE_ORGANIZATION_FIELD_ENDPOINT = 'organizationFields';
    public const PIPEDRIVE_ORGANIZATION_ENDPOINT       = 'organizations';

    /**
     * Persons primary fields.
     */
    public const PIPEDRIVE_PERSON_PRIMARY_ID_KEY   = 'id';

    /**
     * Persons required field.
     */
    public const PIPEDRIVE_PERSON_REQUIRED_FIELDS = [
        'email',
    ];

    /**
     * Persons required field.
     */
    public const PIPEDRIVE_ORGANIZATION_REQUIRED_FIELDS = [
        'name',
    ];

    /**
     * Pipedrive's relation field key.
     */
    public const PIPEDRIVE_RELATION_PICTURE_FIELD_KEY      = 'picture_id';

    public const UPDATE_TIME                        = 'update_time';
}
