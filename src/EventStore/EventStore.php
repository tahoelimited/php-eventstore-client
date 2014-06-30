<?php

namespace EventStore;

use EventStore\Exception\ConnectionFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\Request;

final class EventStore
{
    private $url;
    private $httpClient;
    private $lastResponse;

    public function __construct($url)
    {
        $this->url = $url;

        $this->httpClient = new Client();
        $this->checkConnection();
    }

    public function deleteStream($stream_name, StreamDeletion $mode)
    {
        $request = $this->httpClient->createRequest('DELETE', $this->getStreamUrl($stream_name));

        if ($mode == StreamDeletion::HARD) {
            $request->addHeader('ES-HardDelete', 'true');
        }

        $this->sendRequest($request);
    }

    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    public function navigateStreamFeed(StreamFeed $stream_feed, StreamFeedLinkRelation $relation)
    {
        $url        = $stream_feed->getLinkUrl($relation);
        $streamFeed = $this->readStreamFeed($url, $stream_feed->getEventEmbedMode());

        return $streamFeed;
    }

    public function openStreamFeed($stream_name, EventEmbedMode $embed_mode = null)
    {
        $url        = $this->getStreamUrl($stream_name);
        $streamFeed = $this->readStreamFeed($url, $embed_mode);

        return $streamFeed;
    }

    public function writeToStream($stream_name, WritableToStream $events)
    {
        if ($events instanceof WritableEvent) {
            $events = new WritableEventCollection([$events]);
        }

        $request = $this->httpClient->createRequest('POST', $this->getStreamUrl($stream_name), ['json' => $events->toStreamData()]);
        $this->sendRequest($request);
    }

    private function getStreamUrl($stream_name)
    {
        return sprintf('%s/streams/%s', $this->url, $stream_name);
    }

    private function sendRequest(Request $request)
    {
        try {
            $this->lastResponse = $this->httpClient->send($request);
        } catch (ClientException $e) {

            $this->lastResponse = $e->getResponse();
        }
    }

    private function checkConnection()
    {
        try {
            $request = $this->httpClient->createRequest('GET', $this->url);
            $this->sendRequest($request);
        } catch (RequestException $e) {
            throw new ConnectionFailedException($e->getMessage());
        }
    }

    private function readStreamFeed($stream_url, EventEmbedMode $embed_mode = null)
    {
        $request = $this->httpClient->createRequest('GET', $stream_url);
        $request->addHeader('Accept', 'application/json');

        if ($embed_mode != null && $embed_mode != EventEmbedMode::NONE()) {
            $request->getQuery()->add('embed', $embed_mode->toNative());
        }

        $this->sendRequest($request);

        $jsonResponse = $this->lastResponse->json();
        $streamFeed = new StreamFeed($jsonResponse, $embed_mode);

        return $streamFeed;
    }

}