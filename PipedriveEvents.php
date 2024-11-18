<?php

namespace MauticPlugin\PipedriveBundle;

final class PipedriveEvents
{
    /**
     * The pipedrive.on_campaign_action_push_data event is dispatched when the campaign action push data to integration is executed.
     *
     * The event listener receives a Mautic\CampaignBundle\Event\PendingEvent
     *
     * @var string
     */
    public const ON_CAMPAIGN_ACTION_PUSH_CONTACT = 'pipedrive.on_campaign_action_push_data';

    /**
     * The pipedrive.on_form_action_push_data event is dispatched when the form action push data to integration is executed.
     *
     * The event listener receives a Mautic\FormBundle\Event\SubmissionEvent
     *
     * @var string
     */
    public const ON_FORM_ACTION_PUSH_CONTACT = 'pipedrive.on_form_action_push_data';

    /**
     * The pipedrive.pipedrive.on_point_trigger_push_data event is dispatched when the point trigger is executed.
     *
     * The event listener receives a Mautic\PointBundle\Event\TriggerEvent
     *
     * @var string
     */
    public const ON_POINT_TRIGGER_PUSH_CONTACT = 'pipedrive.on_point_trigger_push_data';
}
