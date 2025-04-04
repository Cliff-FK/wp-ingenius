<?php

namespace Duplicator\Addons\DropboxAddon\Utils;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use VendorDuplicator\GrahamCampbell\GuzzleFactory\GuzzleFactory;
use VendorDuplicator\GuzzleHttp\Client as GuzzleClient;
use VendorDuplicator\GuzzleHttp\Psr7\Response;
use VendorDuplicator\Spatie\Dropbox\Client;

class DropboxClient extends Client
{
    const OAUTH2_URL = 'https://www.dropbox.com/oauth2/';

    /**
     * Class contructor
     *
     * @param string            $accessToken           The access token
     * @param GuzzleClient|null $client                The HTTP client
     * @param int               $maxChunkSize          The maximum size of a chunk
     * @param int               $maxUploadChunkRetries The maximum number of times to retry a chunk upload
     * @param bool              $sslVerify             If true, use SSL
     * @param string            $sslCert               If empty use server cert
     * @param bool              $ipv4Only              If true, use IPv4 only
     */
    public function __construct(
        $accessToken,
        ?GuzzleClient $client = null,
        $maxChunkSize = self::MAX_CHUNK_SIZE,
        $maxUploadChunkRetries = 0,
        $sslVerify = true,
        $sslCert = '',
        $ipv4Only = false
    ) {
        if (!$client) {
            $options = [];
            if ($sslVerify === false) {
                $verify = false;
            } elseif (strlen($sslCert) === 0) {
                $verify = true;
            } else {
                $verify = $sslCert;
            }
            $options['verify'] = $verify;
            if ($ipv4Only) {
                $options['force_ip_resolve'] = 'v4';
            }
            $options['handler'] = GuzzleFactory::handler();
            $client             = new GuzzleClient($options);
        }

        parent::__construct($accessToken, $client, $maxChunkSize, $maxUploadChunkRetries);
    }

    /**
     * Exchange the oauth1 token for an oauth2 token
     *
     * @see https://www.dropbox.com/developers/documentation/http/documentation#auth-token-from_oauth1
     *
     * @param array{t: string, s: string}                $token      The oauth1 token
     * @param array{app_key: string, app_secret: string} $app_config The app config
     *
     * @return string|false
     */
    public static function accessTokenFromOauth1($token, $app_config)
    {
        $url = "https://api.dropboxapi.com/2/auth/token/from_oauth1";

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($app_config['app_key'] . ':' . $app_config['app_secret']),
            ],
            'body'    => [
                'oauth1_token'        => $token['t'],
                'oauth1_token_secret' => $token['s'],
            ],
        ]);

        if (is_wp_error($response)) {
            DUP_PRO_Log::traceObject("[DropboxClient] Something wrong with while trying to get v2_access_token with oauth1 token.", $response);
            return false;
        }


        $ret_obj = json_decode($response['body']);

        return $ret_obj->oauth2_token ?? false;
    }

    /**
     * Use the app config to authenticate and get the access token
     *
     * @param string                                     $auth_code  The authorization code
     * @param array{app_key: string, app_secret: string} $app_config The app config
     *
     * @return bool
     */
    public function authenticate($auth_code, $app_config)
    {
        $url  = self::OAUTH2_URL . 'token';
        $args = $this->injectExtraReqArgs([
            'timeout' => 30,
            'body'    => [
                'client_id'     => $app_config['app_key'],
                'client_secret' => $app_config['app_secret'],
                'code'          => $auth_code,
                'grant_type'    => 'authorization_code',
            ],
        ]);

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            DUP_PRO_Log::traceObject("Something wrong with while trying to get v2_access_token with code", $response);
            return false;
        }

        DUP_PRO_Log::traceObject("Got v2 access_token", $response);
        $ret_obj = json_decode($response['body']);

        return $ret_obj->access_token ?? false;
    }

    /**
     * Get the account's usage quota information.
     *
     * @return array{used: int, allocation: array{allocated: int}}|false
     */
    public function getQuota()
    {
        try {
            return $this->rpcEndpointRequest('users/get_space_usage');
        } catch (\Exception $e) {
            \DUP_PRO_Log::trace('[DropboxClient] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set the timeout for the client
     *
     * @param int $timeout The timeout in seconds
     *
     * @return void
     */
    public function setTimeout($timeout)
    {
        if ($timeout > 0) {
            $this->client = new GuzzleClient(['handler' => GuzzleFactory::handler(), 'timeout' => $timeout]);
        } else {
            $this->client = new GuzzleClient(['handler' => GuzzleFactory::handler()]);
        }
    }

    /**
     * Download a file from a user's Dropbox.
     *
     * @param string $path   The path to download
     * @param int    $start  The byte to start from
     * @param int    $length The number of bytes to download
     *
     * @return string
     *
     * @link https://www.dropbox.com/developers/documentation/http/documentation#files-download
     */
    public function downloadPartial($path, $start = 0, $length = 1024 * 1024)
    {
        $arguments = ['path' => $this->normalizePath($path)];
        $headers   = ['Range' => "bytes=$start-" . ($start + $length - 1)];
        /** @var Response $response */
        $response = $this->contentEndpointRequestWithHeaders('files/download', $arguments, '', $headers);
        return $response->getBody()->getContents();
    }

    /**
     * Inject extra request arguments
     *
     * @param array<string, mixed> $opts The request options
     *
     * @return array<string, mixed>
     */
    private function injectExtraReqArgs(array $opts)
    {
        $global            = DUP_PRO_Global_Entity::getInstance();
        $opts['sslverify'] = !$global->ssl_disableverify;
        if (!$global->ssl_useservercerts) {
            $opts['sslcertificates'] = DUPLICATOR_PRO_CERT_PATH;
        }
        return $opts;
    }

    /**
     * Content endpoint request with custom headers
     *
     * @param string                $endpoint  The endpoint to send the request to
     * @param array<string, mixed>  $arguments The params to send.
     * @param string                $body      The body of the request
     * @param array<string, string> $headers   Custom headers to add.
     *
     * @return \VendorDuplicator\Psr\Http\Message\ResponseInterface
     *
     * @throws \Exception
     */
    public function contentEndpointRequestWithHeaders($endpoint, array $arguments, $body = '', $headers = [])
    {
        $headers['Dropbox-API-Arg'] = \json_encode($arguments);
        if ($body !== '') {
            $headers['Content-Type'] = 'application/octet-stream';
        }
        return $this->client->post($this->getEndpointUrl('content', $endpoint), ['headers' => $this->getHeaders($headers), 'body' => $body]);
    }
}
