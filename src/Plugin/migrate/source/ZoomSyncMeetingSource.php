<?php

namespace Drupal\openy_gc_zoom_sync\Plugin\migrate\source;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
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
   * The DateFormatter definition.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, ZoomSyncClientInterface $zoomSyncClient, DateFormatterInterface $dateFormatter, ConfigFactoryInterface $configFactory) {
    $this->zoomSyncClient = $zoomSyncClient;
    $this->dateFormatter = $dateFormatter;
    $this->configFactory = $configFactory;
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
      $container->get('zoom_sync.client'),
      $container->get('date.formatter'),
      $container->get('config.factory')
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
    $wd = [];
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
        $wd[] = $weekly_days[$day];
      }
    }
    return implode(',', $wd);
  }

  /**
   * Method to get source.
   *
   * @throws \Exception
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
    $site_timezone = $this->configFactory->get('system.date')->get('timezone.default');

    foreach ($meetings as $id => $meeting) {
      $recurring_date = [];
      $r_type = '';
      $recurrence = $meeting['recurrence'] ?? NULL;
      $occurrences = $meeting['occurrences'] ?? NULL;

      if (isset($occurrences)) {
        $start = DateTimePlus::createFromFormat('Y-m-d\TH:i:s', str_replace('Z', '', $occurrences[0]['start_time']), $site_timezone);
        $start_time = $start->format('Y-m-d\TH:i:s');

        if (count($occurrences) > 1) {
          $end_time = str_replace('Z', '', end($occurrences)['start_time']);
        }
        else {
          $end_date_time = str_replace('Z', '', $recurrence['end_date_time']);

          if ($end_date_time > $start_time) {
            $end_time = $end_date_time;
          } else {
            $start_time = $start->modify('-1 day')->format('Y-m-d\TH:i:s');
            $end_time = $start->modify('+1 day')->format('Y-m-d\TH:i:s');
          }
        }

        $recurring_date[0] = [
          'start_date' => $start_time,
          'end_date' => $end_time,
          'time' => $start->format('h:i a'),
          'duration' => $occurrences[0]['duration'] * 60
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
          $start = DateTimePlus::createFromFormat('Y-m-d\TH:i:s', str_replace('Z', '', $meeting['start_time']), $site_timezone);
          $start_time = $start->format('Y-m-d\TH:i:s');

          $interval = $this->dateFormatter->formatInterval($meeting['duration'] * 60);
          unset($recurring_date);

          $recurring_date[0] = [
            'start_date' => $start_time,
            'end_date' => $start->modify('+' . $interval)->format('Y-m-d\TH:i:s')
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
      $category = '';
      $instructor = '';
      $r_type = $meeting['r_type'];
      $type = $r_type != 'custom' ? '_recurring_date' : '';

      if (isset($meeting['tracking_fields'])) {
        $tracked_fields = $meeting['tracking_fields'];
        $category = $tracked_fields['category'];
        $instructor = $tracked_fields['instructor'];
      }

      $data[] = [
        'id' => 'zm_' . $id,
        'topic' => $meeting['topic'],
        'description' => $meeting['agenda'],
        'category' => $category ?? '',
        'instructor' => $instructor ?? '',
        'vm_link_uri' => $meeting['join_url'] ?? $meeting['start_url'],
        'r_type' => $r_type . $type,
        $r_type . '_rd' => $meeting[$r_type . '_rd']
      ];
    }

    return new \ArrayIterator($data);
  }
}
