<?php

namespace Drupal\openy_gc_zoom_sync\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\openy_gc_zoom_sync\ZoomSyncClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @MigrateSource(
 *   id = "openy_gc_zoom_sync_category_source"
 * )
 */
class ZoomSyncCategorySource extends SourcePluginBase implements ContainerFactoryPluginInterface {

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
      'name' => $this->t('Name'),
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
    $categories = $this->zoomSyncClient->getCategories();

    $data[0] = [
      'id' => 'zc_1',
      'name' => 'Custom category',
    ];

    foreach ($categories as $category) {
      $id = str_replace(' ', '_', strtolower($category));

      $data[] = [
        'id' => 'zc_' . $id,
        'name' => $category,
      ];
    }

    return new \ArrayIterator($data);
  }
}
