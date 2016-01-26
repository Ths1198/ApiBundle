<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\ApiBundle\Controller;

use Elasticsearch\Common\Exceptions\NoDocumentsToGetException;
use ONGR\ElasticsearchBundle\Service\Repository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD implementation for Api Controller.
 */
class RestController extends AbstractRestController implements
    RestControllerInterface
{

    /**
     * {@inheritdoc}
     */
    public function getAction(Request $request, $documentId)
    {
        $repository = $this->getRequestRepository($request);

        try {
            $document = $this->getCrudService()->read($repository, $documentId);

            if ($document === null) {
                return $this->renderError($request, 'Document does not exist', Response::HTTP_NOT_FOUND);
            }
        } catch(\Exception $e) {
            return $this->renderError($request, $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->renderRest($request, $document, Response::HTTP_OK);
    }

    /**
     * {@inheritdoc}
     */
    public function postAction(Request $request, $documentId = null)
    {
        $repository = $this->getRequestRepository($request);

        $data = $this->get('ongr_api.request_serializer')->deserializeRequest($request);

        if (!empty($documentId)) {
            $data['_id'] = $documentId;
        }

        // TODO: validate data

        try {
            $this->getCrudService()->create($repository, $data);
            $response = $this->getCrudService()->commit($repository);
        } catch (\RuntimeException $e) {
            return $this->renderError($request, $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
            return $this->renderError($request, $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if ($response['errors']) {
            // TODO: 406 validation error
        }

        $documentId = $response['items'][0]['create']['_id'];
        $row = $this->getCrudService()->read($repository, $documentId);
        return $this->renderRest($request, $row, Response::HTTP_CREATED);
    }

    /**
     * {@inheritdoc}
     */
    public function putAction(Request $request, $documentId)
    {
        $repository = $this->getRequestRepository($request);

        $data = $this->get('ongr_api.request_serializer')->deserializeRequest($request);

        // TODO: check validation

        try {
            $this->getCrudService()->update($repository, $documentId, $data);
            $response = $this->getCrudService()->commit($repository);
        } catch (\RuntimeException $e) {
            return $this->renderError($request, $e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (NoDocumentsToGetException $e) {
            return $this->renderError($request, $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->renderError($request, $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if ($response['errors']) {
            // TODO: 406 validation error
        }

        $documentId = $response['items'][0]['update']['_id'];
        $row = $this->getCrudService()->read($repository, $documentId);

        return $this->renderRest($request, $row, Response::HTTP_NO_CONTENT);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAction(Request $request, $documentId)
    {
        $repository = $this->getRequestRepository($request);

        try {
            $this->getCrudService()->delete($repository, $documentId);
            $response = $this->getCrudService()->commit($repository);
        } catch (\RuntimeException $e) {
            return $this->renderError($request, $e->getMessage(), Response::HTTP_BAD_REQUEST); // Missing _id
        } catch (NoDocumentsToGetException $e) {
            return $this->renderError($request, $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->renderError($request, $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->renderRest($request, $response, Response::HTTP_NO_CONTENT);
    }
}
