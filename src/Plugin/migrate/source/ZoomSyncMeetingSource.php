<?php

namespace Drupal\zoom_sync\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\zoom_sync\ZoomSyncClientInterface;
use Drush\Drush;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @MigrateSource(
 *   id = "zoom_sync_meeting_source"
 * )
 */
class ZoomSyncMeetingSource extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * Name of the daily recurring date.
   */
  const ZOOM_SYNC_DAILY_TYPE = 'daily_recurring_date';

  /**
   * Name of the weekly recurring date.
   */
  const ZOOM_SYNC_WEEKLY_TYPE = 'weekly_recurring_date';

  /**
   * Name of the monthly recurring date.
   */
  const ZOOM_SYNC_MONTHLY_TYPE = 'monthly_recurring_date';

  /**
   * Name of the custom date.
   */
  const ZOOM_SYNC_CUSTOM_TYPE = 'custom';

  /**
   * The entity type definition.
   *
   * @var \Drupal\zoom_sync\ZoomSyncClientInterface
   */
  protected $zoomSyncClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, ZoomSyncClientInterface $zoomSyncClient) {
    $this->zoomSyncClient = $zoomSyncClient;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('zoom_sync.client')
    );
  }

  public function fields() {
    return [
      'id' => $this->t('Id'),
      'title' => $this->t('Title'),
      'body' => $this->t('Body'),
      'level' => $this->t('Level'),
      'category' => $this->t('Category'),
      'image' => $this->t('Image'),
      'meeting_link' => $this->t('Meeting link'),
      'host_name' => $this->t('Host name'),
      'info_text' => $this->t('Meeting information'),
      'equipment' => $this->t('Equipment'),
      'recurrence_type' => $this->t('Recurrence type'),
      'excluded_dates' => $this->t('Excluded dates'),
      'included_dates' => $this->t('Included dates'),
    ];
  }

  /**
   * @return string
   */
  public function __toString() {
    return '';
  }

  /**
   * @return \string[][]
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * @return \ArrayIterator
   */
  protected function initializeIterator() {
    $data = [];
    $custom_date = [];
    $weekly_days = [
      1 => 'sunday',
      2 => 'monday',
      3 => 'tuesday',
      4 => 'wednesday',
      5 => 'thursday',
      6 => 'friday',
      7 => 'saturday',
    ];
    $monthly_day = [
      1 => 'first',
      2 => 'second',
      3 => 'third',
      4 => 'fourth',
      -1 => 'last',
    ];

    $meetings = $this->zoomSyncClient->getMappedMeetingList();

    foreach ($meetings as $id => $meeting) {
      $recurring_date = [];
      $recurrence = $meeting['recurrence'] ?? NULL;
      $occurrences = $meeting['occurrences'] ?? NULL;

      if (isset($occurrences)) {
        $start_time = str_replace('Z', '', $occurrences[0]['start_time']);
        $duration = $occurrences[0]['duration'];

        if (count($occurrences) > 1) {
          $end_time = str_replace('Z', '', end($occurrences)['start_time']);
        } else {
          $end_time = strtotime($start_time) + $duration * 60;
        }

        $recurring_date = [
          'value' => date('Y-m-d\TH:i:s', $start_time),
          'end_value' => date('Y-m-d\TH:i:s', $end_time),
          'time' => date('g:i a', strtotime($start_time)),
          'duration' => $duration * 60
        ];
      }

      switch ($recurrence['type']) {
        case 1:
          $r_type = self::ZOOM_SYNC_DAILY_TYPE;
          $daily_rd[0] = $recurring_date;
          break;
        case 2:
          $r_type = self::ZOOM_SYNC_WEEKLY_TYPE;
          $weekly_rd[0] = $recurring_date;
          $weekly_rd[0]['days'] = $weekly_days[$recurrence['weekly_days'] + 1];
          break;
        case 3:
          $r_type = self::ZOOM_SYNC_MONTHLY_TYPE;
          $monthly_rd[0] = $recurring_date;
          $monthly_rd[0]['days'] = $weekly_days[$recurrence['monthly_weekly_day'] + 1];
          $monthly_rd[0]['type'] = isset($recurrence['monthly_week']) ? 'weekday' : 'monthday';
          $monthly_rd[0]['day_occurrence'] = $monthly_day[$recurrence['monthly_week']];
          $monthly_rd[0]['day_of_month'] = $recurrence['monthly_day'];
          break;
        case NULL:
          $r_type = self::ZOOM_SYNC_CUSTOM_TYPE;
          $start_time = $meeting['start_time'];

          $custom_date[0][0] = [
            'value' => str_replace('Z', '', $start_time),
            'end_value' => date('Y-m-d\TH:i:s', strtotime($start_time) + $meeting['duration'] * 60),
          ];
          break;
      }

      $data[] = [
        'id' => 'zm_' . $id,
        'topic' => $meeting['topic'] . ' zm_' . $id,
        'body' => $meeting['agenda'],
        'category' => 3,
        'level' => 3,
        'vm_link_uri' => $meeting['join_url'] ?? $meeting['start_url'],
        'r_type' => $r_type,
        'daily_rd' => $r_type == self::ZOOM_SYNC_DAILY_TYPE ? $daily_rd : [],
        'weekly_rd' => $r_type == self::ZOOM_SYNC_WEEKLY_TYPE ? $weekly_rd : [],
        'monthly_rd' => $r_type == self::ZOOM_SYNC_MONTHLY_TYPE ? $monthly_rd : [],
        'custom_date' => $r_type == self::ZOOM_SYNC_CUSTOM_TYPE ? $custom_date : []
      ];
    }

    return new \ArrayIterator($data);
  }
}
