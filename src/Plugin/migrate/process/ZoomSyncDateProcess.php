<?php

namespace Drupal\openy_gc_zoom_sync\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * This plugin converts a date to needed format.
 *
 * Example:
 *
 * @code
 * process:
 *   field_name:
 *     -
 *       plugin: zoom_sync_date_proccess
 *       source: source_value
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "zoom_sync_date_proccess"
 * )
 */
class ZoomSyncDateProcess extends ProcessPluginBase {
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    if (!isset($value)) {
      $property = $this->configuration['source'];
      $source_list = $row->getSource();

      if (array_key_exists($property, $source_list)) {
        $value = $row->getSourceProperty($property);
      }
    }

    return $value;
  }
}
