<?php

namespace Drupal\openy_gc_zoom_sync\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\openy_gc_zoom_sync\ZoomSyncClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @MigrateSource(
 *   id = "openy_gc_zoom_sync_meeting_source"
 * )
 */
class ZoomSyncMeetingSource extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * Name of the daily recurring date.
   */
  const ZOOM_SYNC_DAILY_TYPE = 'daily';

  /**
   * Name of the weekly recurring date.
   */
  const ZOOM_SYNC_WEEKLY_TYPE = 'weekly';

  /**
   * Name of the monthly recurring date.
   */
  const ZOOM_SYNC_MONTHLY_TYPE = 'monthly';

  /**
   * Name of the custom date.
   */
  const ZOOM_SYNC_CUSTOM_TYPE = 'custom';

  /**
   * The entity type definition.
   *
   * @var \Drupal\openy_gc_zoom_sync\ZoomSyncClientInterface
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

  /**
   * @return array
   */
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
   * Method to get week days.
   */
  private function getWeekDays($week_days) {
    $wd = '';
    $weekly_days = [
      1 => 'sunday',
      2 => 'monday',
      3 => 'tuesday',
      4 => 'wednesday',
      5 => 'thursday',
      6 => 'friday',
      7 => 'saturday',
    ];

    if (isset($week_days)) {
      $week_days = explode(',', $week_days);
      foreach ($week_days as $day) {
        $wd .= $weekly_days[$day];
      }
    }
    return $wd;
  }

  /**
   * Method to get source.
   */
  private function getSource() {
    $data = [];
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
      $r_type = '';
      $recurrence = $meeting['recurrence'] ?? NULL;
      $occurrences = $meeting['occurrences'] ?? NULL;

      if (isset($occurrences)) {
        $start_time = str_replace('Z', '', $occurrences[0]['start_time']);
        $duration = $occurrences[0]['duration'];

        if (count($occurrences) > 1) {
          $end_time = date('Y-m-d\TH:i:s', strtotime(end($occurrences)['start_time']));
        }
        else {
          $end_date_time = str_replace('Z', '', $recurrence['end_date_time']);

          if ($end_date_time > $start_time) {
            $end_time = $end_date_time;
          } else {
            $start_time = date('Y-m-d\TH:i:s', strtotime($start_time . ' -1 day'));
            $end_time = date('Y-m-d\TH:i:s', strtotime($start_time . ' +1 day'));
          }
        }

        $recurring_date[0] = [
          'start_date' => $start_time,
          'end_date' => $end_time,
          'time' => date('g:i a', strtotime($start_time)),
          'duration' => $duration * 60
        ];
      }

      switch ($recurrence['type']) {
        case 1:
          $r_type = self::ZOOM_SYNC_DAILY_TYPE;
          break;
        case 2:
          $r_type = self::ZOOM_SYNC_WEEKLY_TYPE;

          $recurring_date[0]['days'] = $this->getWeekDays($recurrence['weekly_days']);
          break;
        case 3:
          $r_type = self::ZOOM_SYNC_MONTHLY_TYPE;

          $recurring_date[0]['days'] = $this->getWeekDays($recurrence['monthly_week_day']);
          $recurring_date[0]['type'] = isset($recurrence['monthly_week']) ? 'weekday' : 'monthday';
          $recurring_date[0]['day_occurrence'] = $monthly_day[$recurrence['monthly_week']];
          $recurring_date[0]['day_of_month'] = $recurrence['monthly_day'];
          break;
        case NULL:
          $r_type = self::ZOOM_SYNC_CUSTOM_TYPE;
          $start_time = str_replace('Z', '', $meeting['start_time']);
          unset($recurring_date);

          $recurring_date[0] = [
            'start_date' => $start_time,
            'end_date' => date('Y-m-d\TH:i:s', strtotime($start_time) + $meeting['duration'] * 60),
          ];
          break;
      }

      $data[$id] = [
        'topic' => $meeting['topic'],
        'agenda' => $meeting['agenda'],
        'join_url' => $meeting['join_url'],
        'start_url' => $meeting['start_url'],
        'r_type' => $r_type,
        $r_type . '_rd' => $recurring_date,
        'tracking_fields' => $meeting['tracking_fields']
      ];
    }
    return $data;
  }

  /**
   * @return \ArrayIterator
   */
  protected function initializeIterator() {
    $meetings = $this->getSource();

    foreach ($meetings as $id => $meeting) {
      $r_type = $meeting['r_type'];
      $type = $r_type != 'custom' ? '_recurring_date' : '';
      if (isset($meeting['tracking_fields'])) {
        $tracked_fields = $meeting['tracking_fields'];
        $category = str_replace(' ', '_', strtolower($tracked_fields['category']));
        $instructor = str_replace(' ', '_', strtolower($tracked_fields['instructor']));
      } else {
        $category = 1;
        $instructor = 1;
      }

      $data[] = [
        'id' => 'zm_' . $id,
        'topic' => $meeting['topic'] . ' zm_' . $id,
        'body' => $meeting['agenda'],
        'category' => 'zc_' . $category,
        'instructor' => 'zi_' . $instructor,
        'vm_link_uri' => $meeting['join_url'] ?? $meeting['start_url'],
        'r_type' => $r_type . $type,
        $r_type . '_rd' => $meeting[$r_type . '_rd']
      ];
    }

    return new \ArrayIterator($data);
  }
}
