# Zoom API to Virtual Y Meeting sync tool

Purpose of this module is to perform auto sync from Zoom API of all available events to Virtual Y Virtual Meeting entity.

## Initial setup of the module

Please enable this module and set your credentials on the admin interface (`/admin/openy/integrations/openy_gc_zoom_sync/settings`).

## How to run sync script

We use Drupal core migration api for this feature.

Please, run `drush mim openy_gc_zoom_sync_virtual_meetings --sync` to begin sync process.
You could add this command to your crontab and run the script according to your schedule.

## How to modify fields

Feel free to modify migration config here: https://github.com/open-y-subprojects/openy_gc_zoom_sync/blob/main/migrations/migrate_plus.migration.openy_gc_zoom_sync_virtual_meetings.yml
If you have custom entities fro Virtual Y.

## How I can get help?

Feel free to ask any questions in the #developers channel of the Open Y slack or you could write any questions to anatoliy@imagexmedia.com


