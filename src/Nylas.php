<?php

namespace Nylas;

use GuzzleHttp\Client as GuzzleClient;
use Nylas\Models;
use Nylas\Models\Calendar;
use Nylas\Models\Contact;
use Nylas\Models\Draft;
use Nylas\Models\Event;
use Nylas\Models\File;
use Nylas\Models\Message;
use Nylas\Models\Tag;
use Nylas\Models\Thread;

class Nylas
{
    protected $apiServer = 'https://api.nylas.com';
    protected $apiClient;
    protected $apiToken;
    public $apiRoot = 'n';

    public function __construct($appID, $appSecret, $token = null, $apiServer = null)
    {
        $this->appID = $appID;
        $this->appSecret = $appSecret;
        $this->apiToken = $token;
        $this->apiClient = $this->createApiClient();

        if ($apiServer) {
            $this->apiServer = $apiServer;
        }
    }

    protected function createHeaders()
    {
        $token = 'Basic ' . base64_encode($this->apiToken . ':');
        $headers = [
            'headers' => [
                'Authorization'       => $token,
                'X-Nylas-API-Wrapper' => 'php',
            ],
        ];

        return $headers;
    }

    private function createApiClient()
    {
        return new GuzzleClient(['base_uri' => $this->apiServer]);
    }

    public function createAuthURL($redirect_uri, $login_hint = null)
    {
        $args = [
            "client_id"     => $this->appID,
            "redirect_uri"  => $redirect_uri,
            "response_type" => "code",
            "scope"         => "email",
            "login_hint"    => $login_hint,
            "state"         => $this->generateId(),
        ];

        return $this->apiServer . '/oauth/authorize?' . http_build_query($args);
    }

    public function getAuthToken($code)
    {
        $args = [
            "client_id"     => $this->appID,
            "client_secret" => $this->appSecret,
            "grant_type"    => "authorization_code",
            "code"          => $code,
        ];

        $url = $this->apiServer . '/oauth/token';
        $payload = [];
        $payload['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        $payload['headers']['Accept'] = 'text/plain';
        $payload['form_params'] = $args;

        $responseJson = $this->apiClient->post($url, $payload)->getBody()->getContents();
        $response = json_decode($responseJson, true);

        if (array_key_exists('access_token', $response)) {
            $this->apiToken = $response['access_token'];
        }

        return $this->apiToken;
    }

    public function account()
    {
        $account = new Models\Account($this, null);

        return $this->getResource(null, $account, null, []);
    }

    public function messages()
    {
        $msgObj = new Message($this, null);

        return new NylasModelCollection($msgObj, $this, null, [], 0, []);
    }

    public function threads()
    {
        $msgObj = new Thread($this, null);

        return new NylasModelCollection($msgObj, $this, null, [], 0, []);
    }

    public function drafts()
    {
        $msgObj = new Draft($this, null);

        return new NylasModelCollection($msgObj, $this, null, [], 0, []);
    }

    public function tags()
    {
        $msgObj = new Tag($this, null);

        return new NylasModelCollection($msgObj, $this, null, [], 0, []);
    }

    public function files()
    {
        $msgObj = new File($this, null);

        return new NylasModelCollection($msgObj, $this, null, [], 0, []);
    }

    public function contacts()
    {
        $msgObj = new Contact($this, null);

        return new NylasModelCollection($msgObj, $this, null, [], 0, []);
    }

    public function calendars()
    {
        $msgObj = new Calendar($this, null);

        return new NylasModelCollection($msgObj, $this, null, [], 0, []);
    }

    public function events()
    {
        $msgObj = new Event($this, null);

        return new NylasModelCollection($msgObj, $this, null, [], 0, []);
    }

    /**
     * @param string|null $namespace
     * @param NylasAPIObject $klass
     * @param array $filter
     * @return array
     */
    public function getResources($namespace, $klass, $filter)
    {
        // TODO: $filter should be $filters
        $suffix = ($namespace) ? '/' . $klass->apiRoot . '/' . $namespace : '';
        $url = $this->apiServer . $suffix . '/' . $klass->collectionName;
        $url = $url . '?' . http_build_query($filter);
        $dataJson = $this->apiClient->get($url, $this->createHeaders())->getBody()->getContents();
        $data = json_decode($dataJson, true);

        $mapped = [];
        foreach ($data as $i) {
            $mapped[] = clone $klass->_createObject($this, $namespace, $i);
        }

        return $mapped;
    }

    /**
     * @param string|null $namespace
     * @param NylasAPIObject $klass
     * @param string $id
     * @param array $filters
     * @return mixed
     */
    public function getResource($namespace, $klass, $id, $filters)
    {
        $extra = '';
        if (array_key_exists('extra', $filters)) {
            $extra = $filters['extra'];
            unset($filters['extra']);
        }
        $response = $this->getResourceRaw($namespace, $klass, $id, $filters);

        return $klass->_createObject($this, $namespace, $response);
    }

    public function getResourceRaw($namespace, $klass, $id, $filters)
    {
        $extra = '';
        if (array_key_exists('extra', $filters)) {
            $extra = $filters['extra'];
            unset($filters['extra']);
        }
        $prefix = ($namespace) ? '/' . $klass->apiRoot . '/' . $namespace : '';
        $postfix = ($extra) ? '/' . $extra : '';
        $url = $this->apiServer . $prefix . '/' . $klass->collectionName . '/' . $id . $postfix;
        $url = $url . '?' . http_build_query($filters);
        $dataJson = $this->apiClient->get($url, $this->createHeaders())->getBody()->getContents();
        $data = json_decode($dataJson, true);

        return $data;
    }

    public function getResourceData($namespace, $klass, $id, $filters)
    {
        $extra = '';
        if (array_key_exists('extra', $filters)) {
            $extra = $filters['extra'];
            unset($filters['extra']);
        }
        $prefix = ($namespace) ? '/' . $klass->apiRoot . '/' . $namespace : '';
        $postfix = ($extra) ? '/' . $extra : '';
        $url = $this->apiServer . $prefix . '/' . $klass->collectionName . '/' . $id . $postfix;
        $url = $url . '?' . http_build_query($filters);
        $data = $this->apiClient->get($url, $this->createHeaders())->getBody();

        return $data;
    }

    /**
     * @param string|null $namespace
     * @param NylasAPIObject $klass
     * @param mixed $data
     * @return mixed
     */
    public function _createResource($namespace, $klass, $data)
    {
        $prefix = ($namespace) ? '/' . $klass->apiRoot . '/' . $namespace : '';
        $url = $this->apiServer . $prefix . '/' . $klass->collectionName;

        $payload = $this->createHeaders();
        if ($klass->collectionName == 'files') {
            $payload['headers']['Content-Type'] = 'multipart/form-data';
            $payload['body'] = $data;
        } else {
            $payload['headers']['Content-Type'] = 'application/json';
            $payload['json'] = $data;
        }

        $responseJson = $this->apiClient->post($url, $payload)->getBody()->getContents();
        $response = json_decode($responseJson, true);

        return $klass->_createObject($this, $namespace, $response);
    }

    /**
     * @param string|null $namespace
     * @param NylasAPIObject $klass
     * @param string $id
     * @param mixed $data
     * @return mixed
     */
    public function _updateResource($namespace, $klass, $id, $data)
    {
        $prefix = ($namespace) ? '/' . $klass->apiRoot . '/' . $namespace : '';
        $url = $this->apiServer . $prefix . '/' . $klass->collectionName . '/' . $id;

        if ($klass->collectionName == 'files') {
            $payload['headers']['Content-Type'] = 'multipart/form-data';
            $payload['body'] = $data;
        } else {
            $payload = $this->createHeaders();
            $payload['json'] = $data;
            $responseJson = $this->apiClient->put($url, $payload)->getBody()->getContents();
            $response = json_decode($responseJson, true);

            return $klass->_createObject($this, $namespace, $response);
        }
    }

    /**
     * @param string|null $namespace
     * @param NylasAPIObject $klass
     * @param string $id
     * @return mixed
     */
    public function _deleteResource($namespace, $klass, $id)
    {
        $prefix = ($namespace) ? '/' . $klass->apiRoot . '/' . $namespace : '';
        $url = $this->apiServer . $prefix . '/' . $klass->collectionName . '/' . $id;

        $payload = $this->createHeaders();
        $responseJson = $this->apiClient->delete($url, $payload)->getBody()->getContents();
        $response = json_decode($responseJson, true);

        return $response;
    }

    private function generateId()
    {
        // Generates unique UUID
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

}