<?php

namespace Drupal\openy_gc_zoom_sync;

/**
 * Interface ZoomSyncClientInterface.
 */
interface ZoomSyncClientInterface {

  /**
   * Utilizes Drupal's httpClient to connect to the Zoom API.
   *
   * Info: https://marketplace.zoom.us/docs/api-reference/introduction
   * Currently just supports JWT authentication.
   *
   * @param string $method
   *   get, post, patch, delete, etc. See Guzzle documentation.
   * @param string $endpoint
   *   The Zoom API endpoint (ex. users)
   * @param array $query
   *   Any Query Parameters defined in the API spec.
   * @param array $body
   *   Array that will get converted to JSON for some requests.
   *
   * @return object
   *   \GuzzleHttp\Psr7\Response body
   */
  public function request(string $method, string $endpoint, array $query = [], array $body = []);

  /**
   * Get method to get content from Zoom API.
   * @param $url
   * @param array $params
   *
   * @return mixed
   */
  public function get($url, array $params = []);

  /**
   * Method to get mapped meeting list.
   */
  public function getMappedMeetingList();

  /**
   * Method to get categories list.
   */
  public function getCategories();

  /**
   * Method to get instructors list.
   */
  public function getInstructors();
}
