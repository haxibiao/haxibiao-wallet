<?php

namespace Haxibiao\Wallet\Helpers;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;

class JDJRHelper
{
    private static $instance = null;

    private $did;
    private $client;
    private $config;

    const NEW_USER_CODE = 0;
    const OLD_USER_CODE = 1;

    private function __construct()
    {
        //
    }

    private function init($did)
    {
        $this->did    = $did;
        $this->config = config('jd.jr');
        $this->client = is_null($this->client) ? new Client([
            'time_out' => 5,
        ]) : $this->client;
    }

    public static function setDid($did)
    {
        if (!isset(self::$instance)) {
            self::$instance = new static($did);
        }
        self::$instance->init($did);
        return self::$instance;
    }

    public function isNewUser(): bool
    {
        $isNewUser = false;
        $response  = $this->client->request('POST', 'http://ci.liuxueabc.cn/query', [
            'http_errors' => false,
            'headers'     => [
                'Authorization' => $this->config['token'],
            ],
            'json'        => [
                'channel' => $this->config['channel'],
                'mid'     => $this->config['mid'],
                'did'     => $this->did,
            ],
        ]);

        $responseResult = $response->getBody()->getContents();
        if (!empty($responseResult)) {
            $result    = json_decode($responseResult, true);
            $isNewUser = Arr::get($result, 'data.result', JDJRHelper::OLD_USER_CODE) == JDJRHelper::NEW_USER_CODE;
        }

        return $isNewUser;
    }

    public function report(string $callbackUrl)
    {
        $response = $this->client->request('POST', 'http://ci.liuxueabc.cn/report', [
            'http_errors' => false,
            'headers'     => [
                'Authorization' => $this->config['token'],
            ],
            'json'        => [
                'channel' => $this->config['channel'],
                'mid'     => $this->config['mid'],
                'did'     => $this->did,
                'reserve' => $callbackUrl,
            ],
        ]);

        $result = $response->getBody()->getContents();

        return empty($result) ? [] : json_decode($result, true);
    }
}
