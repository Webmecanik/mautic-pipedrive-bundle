<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

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
    const ON_CAMPAIGN_ACTION_PUSH_CONTACT = 'pipedrive.on_campaign_action_push_data';

    /**
     * The pipedrive.on_form_action_push_data event is dispatched when the form action push data to integration is executed.
     *
     * The event listener receives a Mautic\FormBundle\Event\SubmissionEvent
     *
     * @var string
     */
    const ON_FORM_ACTION_PUSH_CONTACT = 'pipedrive.on_form_action_push_data';

    /**
     * The pipedrive.pipedrive.on_point_trigger_push_data event is dispatched when the point trigger is executed.
     *
     * The event listener receives a Mautic\PointBundle\Event\TriggerEvent
     *
     * @var string
     */
    const ON_POINT_TRIGGER_PUSH_CONTACT = 'pipedrive.on_point_trigger_push_data';
}
