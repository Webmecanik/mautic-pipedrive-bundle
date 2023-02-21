Pipedrive integration based on new [Mautic integration framework](https://github.com/mautic/plugin-helloworld).
Once installed, you can find it in Plugins configuration under, named as Pipedrive 2.
It use [OAuth 2.0](https://oauth.net/2/) authorization.
_____

# Overview

Sync. your data between Pipedrive and Mautic without having to do anything manual! Using the Mautic integration with Pipedrive, you will take advantage of:

Bidirectional sync.: data are shared in real time between Pipedrive and Mautic.
Native and Custom fields mapping: easy configuration with UI fields mapping.
Behavioral data sync.: collected events from Mautic are added to your contact timelines in your Pipedrive CRM.

## Detailed features
* Contacts sync.
* Companies sync.
* Fields mapping
* Automated real time sync. of all data
* Targeted triggered sync. from forms, campaigns, points actions
* Contact and Company owner sync.
* Reciprocity deletion handling
* Pipedrive labels (hot, warm, cold) sync.
* Mautic Contact history sync. to Pipedrive

# Installation

You can install this plugin using **composer** using command:
`composer require webmecanik/mautic-pipedrive-bundle`

Or manually from GitHub:
https://github.com/webmecanik/mautic-pipedrive-bundle

# Setup
## Authentication

1. Ask Pipedrive support (marketplace.devs@pipedrive.com) to enable Marketplace Manager on your account. [More info here](https://pipedrive.readme.io/docs/marketplace-manager).
2. Create unlisted app in Tools and Apps > Marketplace manager > Call it "Mautic for {company}". Replace {company} by your company name

![image](https://user-images.githubusercontent.com/462477/197609058-90473cf4-fd90-4538-ab34-a42c4079a9a3.png)

3. Set Callback URL from URL from your plugin settings (you should have another value instead of http://localhost:8084/index_dev.php, do not use this here).

![image](https://user-images.githubusercontent.com/462477/197610243-1df5d450-3383-4388-b056-db77e62a8c78.png)

![image](https://user-images.githubusercontent.com/462477/197610369-ff2bbd9d-5ebd-458c-8ae6-14ca1ef582b9.png)

4. Set these OAuth & Access scopes

![image](https://user-images.githubusercontent.com/462477/198949919-e92cd7a9-f618-4f61-b836-220a7a2a0f8e.png)

5. Copy Client ID and Client Secret from Pipedrive

![image](https://user-images.githubusercontent.com/14075239/211548366-20515a2f-db05-42b5-8881-0b34af38877b.png)

6. Go to Pipedrive 2 plugin in Mautic. Paste the Client ID and Client Secret from Pipedrive and set the subdomain of your Pipedrive account.

![image](https://user-images.githubusercontent.com/14075239/211548449-77cbfe3f-5a4c-4644-91de-17cff4204e78.png)

7. Save your plugin configuration and click Authorize App.

## Features settings
### Features tab
Check your wished features.

If you want to have a full synchronisation (automated), you will need to enable special command in your CRONjob (see hereunder).

#### Commands

Run sync every 20 minutes

`php bin/console mautic:integrations:sync Pipedrive2 --start-datetime="-20 minutes"`

First time sync for data from last year

`php bin/console mautic:integrations:sync Pipedrive2 -f --start-datetime="-1 year"`

### Mapping tab
Map you contact ans company fields according to your data exchange expectations. Be sure to respect field format and field constraint values.

### Delete contacts reciprocity
By enabling this feature, anytime you delete a contact in Mautic or in Pipedrive, the deletion will be applied in the other application.

**⚠️ Be extremely careful using this feature.**

### Sync. owners
You can enable the synchronisation of contacts and companies owners.

**⚠️ You need to have users (owners) existing in both applications using the same email address.**

### Sync. contact activities
You can send Mautic contact activities in Pipedrive contact history. Then your sales team is able to have an overview of the contact activities (form submission, page hits, emails open, etc.).

* In the feature tab, select the type of even you want to sync.
* **⚠️ You need to create all the custom activities you'll sync. from the plugin tab.** Go to Pipedrive > Settings > Company Settings > Activities > all activity events select in plugins settings. All activities types are matched by activity name (see hereunder).
* Start using your app!

# Requirements
This plugin needs the Company merge event merged in Mautic 5. You can cherrypick it from: https://github.com/mautic/mautic/pull/11748
It also needs Disable activity push from Mautic repository: https://github.com/mautic/mautic/pull/11255

# Troubleshooting
## First sync. can be taking all time contacts & companies?
Yes it can. See dedicated command above.

## Be sure to have strictly the same field value for constraint format
* In a number field you can have only numbers
* In a select field you should have the same list of values between the 2 apps

## Contact not synced?
1. Check your plugin feature tab, be sure to have checked the expected features
2. Check that your authentication is still working by entering again your credentials
3. If the user that authenticated the plugin doesn't have Mautic or Pipedrive access, the sync. will be interrupted according to the right loss.

## Owner sync. not working
Be sure to have the owner existing as users in both application with same email address. If the user is not existing, the user cannot be assigned as owner.

## Contact not deleted
Once you delete your contact or company in Mautic, it can take several minutes to be applied in Pipedrive, it is not instant deletion.
Same for deletion in Pipedrive, this could take few minutes.

## Event type name
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
* point.gained
* asset.download
* stage.changed
* lead.utmtagsadded
* sms_reply
* sms.sent
* page.hit
