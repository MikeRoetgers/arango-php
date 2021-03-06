<?php

namespace MikeRoetgers\ArangoPHP\Document;

use MikeRoetgers\ArangoPHP\Collection\Exception\UnknownCollectionException;
use MikeRoetgers\ArangoPHP\HTTP\Client\Client;
use MikeRoetgers\ArangoPHP\HTTP\Client\Exception\InvalidRequestException;
use MikeRoetgers\ArangoPHP\HTTP\Client\Exception\UnexpectedStatusCodeException;
use MikeRoetgers\ArangoPHP\HTTP\Request;
use MikeRoetgers\ArangoPHP\HTTP\Response;
use MikeRoetgers\ArangoPHP\HTTP\ResponseHandler;

class SimpleQueryManager
{
    /**
     * @var DocumentManager
     */
    private $documentManager;

    /**
     * @var Client
     */
    private $client;

    /**
     * @param Client $client
     * @param DocumentManager $documentManager
     */
    public function __construct(Client $client, DocumentManager $documentManager)
    {
        $this->client = $client;
        $this->documentManager = $documentManager;
    }

    /**
     * @param string $collectionName
     * @param int $skip
     * @param int $limit
     * @return mixed
     */
    public function findAll($collectionName, $skip = 0, $limit = 1000)
    {
        $request = new Request('/_api/simple/all', Request::METHOD_PUT);

        $body = array(
            'collection' => $collectionName,
            'skip' => $skip,
            'limit' => $limit
        );
        $request->setBody(json_encode($body));

        $handler = $this->getResponseHandlerForFindingLists($collectionName);
        return $handler->handle($this->client->sendRequest($request));
    }

    /**
     * @param string $collectionName
     * @param array $example
     * @param int $skip
     * @param int $limit
     * @return mixed
     */
    public function findByExample($collectionName, array $example, $skip = 0, $limit = 1000)
    {
        $request = new Request('/_api/simple/by-example');
        $request->setMethod(Request::METHOD_PUT);

        $body = array(
            'collection' => $collectionName,
            'example' => $example,
            'skip' => $skip,
            'limit' => $limit
        );
        $request->setBody(json_encode($body));

        return $this->getResponseHandlerForFindingLists($collectionName)->handle($this->client->sendRequest($request));
    }

    /**
     * @param string $collectionName
     * @param array $example
     * @return mixed
     */
    public function findFirstByExample($collectionName, array $example)
    {
        $request = new Request('/_api/simple/first-example');
        $request->setMethod(Request::METHOD_PUT);

        $body = array(
            'collection' => $collectionName,
            'example' => $example
        );
        $request->setBody(json_encode($body));

        $handler = new ResponseHandler();
        $handler->onStatusCode(200)->execute(function(Response $response) use ($collectionName) {
            if ($this->documentManager->hasMapper($collectionName)) {
                $mapper = $this->documentManager->getMapper($collectionName);
                return $mapper->mapDocument($response->getBodyAsArray()['document']);
            }
            return $response->getBodyAsArray()['document'];
        });
        $handler->onStatusCode(400)->throwInvalidRequestException();
        $handler->onStatusCode(404)->execute(function(Response $response){
            return null;
        });
        $handler->onEverythingElse()->throwUnexpectedStatusCodeException();
        return $handler->handle($this->client->sendRequest($request));
    }

    /**
     * @param string $collectionName
     * @param array $example
     * @param bool $waitForSync
     * @return mixed
     */
    public function removeByExample($collectionName, array $example, $waitForSync = false)
    {
        $request = new Request('/_api/simple/remove-by-example');
        $request->setMethod(Request::METHOD_PUT);

        $body = array(
            'collection' => $collectionName,
            'example' => $example
        );
        $request->setBody(json_encode($body));

        $handler = new ResponseHandler();
        $handler->onStatusCode(200)->execute(function(){
            return true;
        });
        $handler->onStatusCode(400)->throwInvalidRequestException();
        $handler->onStatusCode(404)->throwUnknownCollectionException();
        $handler->onEverythingElse()->throwUnexpectedStatusCodeException();
        return $handler->handle($this->client->sendRequest($request));
    }

    /**
     * @param string $collectionName
     * @param array $example
     * @param array $newValues
     * @param bool $keepNull
     * @param bool $waitForSync
     * @return int
     */
    public function updateByExample($collectionName, array $example, array $newValues, $keepNull = false, $waitForSync = false)
    {
        $body = [
            'collection' => $collectionName,
            'example' => $example,
            'newValue' => $newValues,
            'keepNull' => $keepNull,
            'waitForSync' => $waitForSync
        ];

        $request = new Request('/_api/simple/update-by-example', Request::METHOD_PUT);
        $request->setBody(json_encode($body));
        $handler = new ResponseHandler();
        $handler->onStatusCode(200)->execute(function(Response $response){
            return $response->getBodyAsArray()['updated'];
        });
        $handler->onStatusCode(400)->throwInvalidRequestException();
        $handler->onStatusCode(404)->throwUnknownCollectionException();
        $handler->onEverythingElse()->throwUnexpectedStatusCodeException();
        return $handler->handle($this->client->sendRequest($request));
    }

    /**
     * @param $collectionName
     * @return ResponseHandler
     */
    private function getResponseHandlerForFindingLists($collectionName)
    {
        $handler = new ResponseHandler();
        $handler->onStatusCode(201)->execute(function(Response $response) use ($collectionName) {
            if ($this->documentManager->hasMapper($collectionName)) {
                $mapper = $this->documentManager->getMapper($collectionName);
                return $mapper->mapDocuments($response->getBodyAsArray()['result']);
            }
            return $response->getBodyAsArray()['result'];
        });
        $handler->onStatusCode(400)->throwInvalidRequestException();
        $handler->onStatusCode(404)->throwUnknownCollectionException();
        $handler->onEverythingElse()->throwUnexpectedStatusCodeException();
        return $handler;
    }
}