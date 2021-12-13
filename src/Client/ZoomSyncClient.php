<?php

namespace Drupal\zoom_sync\Client;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\zoom_sync\ZoomSyncClientInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class ZoomSyncClient implements ZoomSyncClientInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Psr\Log\LoggerInterface definition.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Zoom API Secret.
   *
   * @var string
   */
  protected $apiSecret;

  /**
   * Zoom API base url.
   *
   * @var string
   */
  protected $baseUri;

  /**
   * ClientFactory constructor.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   Config.
   */
  public function __construct(
    ClientInterface $httpClient,
    ConfigFactoryInterface $config_factory,
    LoggerInterface $logger) {

    $this->httpClient = $httpClient;
    $this->logger = $logger;
    $this->configFactory = $config_factory->get('zoom_sync.settings');
    $this->apiSecret = $this->configFactory->get('data_service_token');
    $this->baseUri = $this->configFactory->get('data_service_url');
  }

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
   * @return mixed
   *   RequestException or \GuzzleHttp\Psr7\Response body
   */
  public function request(string $method, string $endpoint, array $query = [], array $body = []) {
    try {
      $response = $this->httpClient->{$method}(
        $this->baseUri . $endpoint,
        $this->buildOptions($query, $body)
      );
      // TODO: Add additional response options.
      $payload = Json::decode($response->getBody()->getContents());
      return $payload;
    }
    catch (RequestException $exception) {
      // Log Any exceptions.
      $this->logger->error('Failed to complete Zoom API Task "%error"', [
        '%error' => $exception->getMessage()
      ]);
      throw $exception;
    }
  }

  /**
   * @param $url
   * @param array $params
   *
   * @return mixed
   */
  public function get($url, array $params = []) {
    return $this->request('GET', $url, $params);
  }

  /**
   * @return array
   */
  private function getUsersList() {
    $start_page = 1;
    $users_list = [];
    $users = $this->get('/users');
    $page_count = $users['page_count'];
    $page_number = $users['page_number'];

    while ($page_number < $page_count + 1) {
      $result = $this->get('/users', ['page_number' => $start_page]);
      $users_list = array_merge($users_list, $result['users']);
      $page_number++;
    }

    return $users_list;
  }

  /**
   * @param $email
   * @param string $type
   *
   * @return mixed
   */
  private function getMeetings($email, string $type = 'upcoming') {
    return $this->get( '/users/' . $email . '/meetings', ['type' => $type]);
  }

  /**
   * @param $meeting_id
   *
   * @return mixed
   */
  private function getMeetingInfo($meeting_id) {
    return $this->get('/meetings/' . $meeting_id);
  }

  /**
   * Build options for the client.
   *
   * @param array $query
   *   An array of querystring params for guzzle.
   * @param array $body
   *   An array of items that guzzle with json_encode.
   *
   * @return array
   *   An array of options for guzzle.
   */
  private function buildOptions(array $query = [], array $body = []) {
    $options = [];
    $options['headers'] = [
      'Authorization' => 'Bearer ' . $this->apiSecret,
    ];
    if (!empty($body)) {
      // Json key converts array to json & adds appropriate content-type header.
      $options['json'] = $body;
    }
    if (!empty($query)) {
      $options['query'] = $query;
    }
    return $options;
  }

  private function mappedFields($meetings) {
    $mapped_meetings = [];
    foreach ($meetings as $id => $meeting) {
      // Process with meeting.
      if (!isset($meeting['start_time']) && !isset($meeting['occurrences'])) {
        $msg = 'Meeting "%id - %topic" have not field start_time and occurrences, continue.';
        $this->logger->info($msg, ['%id' => $id, '%topic' => $meeting['topic']]);
        continue;
      }

      $mapped_meetings[$id] = [
        'host_id' => $meeting['host_id'] ?? NULL,
        'host_email' => $meeting['host_email'] ?? NULL,
        'start_time' => $meeting['start_time'] ?? NULL,
        'duration' => $meeting['duration'] ?? NULL,
        'topic' => $meeting['topic'] ?? NULL,
        'status' => $meeting['status'] ?? NULL,
        'timezone' => $meeting['timezone'] ?? NULL,
        'agenda' => $meeting['agenda'] ?? NULL,
        'created_at' => $meeting['created_at'] ?? NULL,
        'start_url' => $meeting['start_url'] ?? NULL,
        'join_url' => $meeting['join_url'] ?? NULL,
        'occurrences' => $meeting['occurrences'] ?? NULL,
        'recurrence' => $meeting['recurrence'] ?? NULL,
      ];
    }
    $this->logger->notice('Finished enrich meetings. Count zoom meetings to save: %count', [
      '%count' => count($mapped_meetings)
    ]);

    return $mapped_meetings;
  }

  public function getMappedMeetingList() {
    $users_list = $this->getUsersList();

    foreach ($users_list as $user) {
      $meetingsList = $this->getMeetings($user['email']);

      // Get all user meetings data.
      foreach ($meetingsList['meetings'] as $meeting) {
        $meeting_id = $meeting['id'];
        if (isset($meetings[$meeting_id])) {
          continue;
        }
        $meetings[$meeting_id] = $this->getMeetingInfo($meeting_id);
      }
    }

    return $this->mappedFields($meetings);
  }
}
