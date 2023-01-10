Pipedrive2 integration based on new integration framework. You can find it in plugins section under Pipedrive 2 icon.

It use [OAuth 2.0](https://oauth.net/2/) authorization.

Pipedrive integration sync persons and companies.

## Installation

By composer

`composer require webmecanik/mautic-pipedrive-bundle`

Manually from GitHub

https://github.com/webmecanik/mautic-pipedrive-bundle

## Setup

1. Create unlisted app in Tools and Apps > Marketplace manager

![image](https://user-images.githubusercontent.com/462477/197609058-90473cf4-fd90-4538-ab34-a42c4079a9a3.png)

2. Set Callback URL from URL from plugin settings

![image](https://user-images.githubusercontent.com/462477/197610243-1df5d450-3383-4388-b056-db77e62a8c78.png)

![image](https://user-images.githubusercontent.com/462477/197610369-ff2bbd9d-5ebd-458c-8ae6-14ca1ef582b9.png)

3. Set these OAuth & Access scopes

![image](https://user-images.githubusercontent.com/462477/198949919-e92cd7a9-f618-4f61-b836-220a7a2a0f8e.png)

## Sync options

Plugins use several sync options and you can manage it under the features tab of plugin settings.

![image](https://user-images.githubusercontent.com/462477/211466918-37b26c63-944f-4055-8a02-f2c10f18755d.png)

### Sync owners

Owners are synced in both directions. You need to create the same owner on both sides and all owners are matched by email address.

### Delete contact's on both sides

If you enable this option, the contact will be deleted on the other side as well.

### Sync contact's company assignment to integration/from integration

Enable sync of contact's company relationship.

### Disable push/pull

Disable push or pull data from integration.

### Sync activities

Pipedrive integration is able to sync person activities.

Enable activity sync and choose activity events to sync in plugin settings

![image](https://user-images.githubusercontent.com/462477/211466823-85c7bd66-e178-4065-8ac9-f113d22232cd.png)

Then create in Pipedrive > Settings > Company Settings > Activities all activity events selected in the plugin settings. All activity types are matched by activity name (event name).

The event names:

* campaign.event
* segment_membership
* campaign_membership
* lead.source.created
* lead.source.identified
* dynamic.content.sent
* email.sent
* email.read
* email.replied
* email.failed
* campaign.event.scheduled
* page.videohit
* form.submitted
* lead.imported
* integration_sync_issues
* message.queue
* lead.donotcontact
* owner.changed
* point.gained
* asset.download
* stage.changed
* lead.utmtagsadded
* sms_reply
* sms.sent
* page.hit

## Commands

Run sync every 20 minutes

`php bin/console mautic:integrations:sync Pipedrive2 --start-datetime="-20 minutes"`

First time sync for data from last year

`php bin/console mautic:integrations:sync Pipedrive2 -f --start-datetime="-1 year"`

