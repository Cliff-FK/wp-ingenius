<?php

namespace VendorDuplicator\Aws\S3\Crypto;

use VendorDuplicator\Aws\AwsClientInterface;
use VendorDuplicator\Aws\Middleware;
use VendorDuplicator\Psr\Http\Message\RequestInterface;
trait UserAgentTrait
{
    private function appendUserAgent(AwsClientInterface $client, $agentString)
    {
        $list = $client->getHandlerList();
        $list->appendBuild(Middleware::mapRequest(function (RequestInterface $req) use ($agentString) {
            if (!empty($req->getHeader('User-Agent')) && !empty($req->getHeader('User-Agent')[0])) {
                $userAgent = $req->getHeader('User-Agent')[0];
                if (strpos($userAgent, $agentString) === \false) {
                    $userAgent .= " {$agentString}";
                }
            } else {
                $userAgent = $agentString;
            }
            $req = $req->withHeader('User-Agent', $userAgent);
            return $req;
        }));
    }
}
