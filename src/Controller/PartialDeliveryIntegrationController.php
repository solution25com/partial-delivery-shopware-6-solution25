<?php

declare(strict_types=1);

namespace PartialDelivery\Controller;

use PartialDelivery\Payment\PartialDeliveryCaptureRequest;
use PartialDelivery\Service\PartialDeliveryService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Generic, integration-friendly Admin API for partial deliveries.
 *
 * Stable surface for external systems (ERP/middleware such as Core/Exact Online)
 * and payment providers. The legacy /api/_action/partial-shipment-delivery routes
 * remain untouched for the admin UI.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class PartialDeliveryIntegrationController extends AbstractController
{
    public function __construct(private readonly PartialDeliveryService $partialDeliveryService)
    {
    }

    #[Route(
        path: '/api/_action/partial-delivery/shipments',
        name: 'api.action.partial_delivery.shipments.create',
        defaults: ['_acl' => ['partial_delivery:create']],
        methods: ['POST']
    )]
    public function createShipments(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data) || !isset($data['shipments']) || !\is_array($data['shipments'])) {
            return $this->error('Invalid request body. Expected a "shipments" array.', JsonResponse::HTTP_BAD_REQUEST);
        }

        $meta = [
            'source' => $data['source'] ?? null,
            'externalReference' => $data['externalReference'] ?? null,
        ];

        try {
            $result = $this->partialDeliveryService->createShipments($data['shipments'], $context, $meta);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $status = $result['created'] === []
            ? JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            : JsonResponse::HTTP_CREATED;

        return new JsonResponse($result, $status);
    }

    #[Route(
        path: '/api/_action/partial-delivery/shipments/{id}',
        name: 'api.action.partial_delivery.shipments.update',
        defaults: ['_acl' => ['partial_delivery:update']],
        methods: ['PATCH']
    )]
    public function updateShipment(string $id, Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->error('Invalid request body. Expected a JSON object.', JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->partialDeliveryService->updateShipment($id, $data, $context);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($result['success']) {
            return new JsonResponse($result);
        }

        $status = $result['reason'] === 'Partial delivery not found.'
            ? JsonResponse::HTTP_NOT_FOUND
            : JsonResponse::HTTP_UNPROCESSABLE_ENTITY;

        return new JsonResponse($result, $status);
    }

    #[Route(
        path: '/api/_action/partial-delivery/order/{orderId}',
        name: 'api.action.partial_delivery.order.list',
        defaults: ['_acl' => ['partial_delivery:read']],
        methods: ['GET']
    )]
    public function listByOrder(string $orderId, Context $context): JsonResponse
    {
        if (!Uuid::isValid($orderId)) {
            return $this->error('Invalid order id.', JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'orderId' => $orderId,
            'lineItems' => $this->partialDeliveryService->getShipmentsByOrder($orderId, $context),
        ]);
    }

    #[Route(
        path: '/api/_action/partial-delivery/capture',
        name: 'api.action.partial_delivery.capture',
        defaults: ['_acl' => ['partial_delivery:update']],
        methods: ['POST']
    )]
    public function capture(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $orderId = \is_array($data) ? ($data['orderId'] ?? null) : null;

        if (!\is_string($orderId) || !Uuid::isValid($orderId)) {
            return $this->error('A valid "orderId" is required.', JsonResponse::HTTP_BAD_REQUEST);
        }

        $captureRequest = new PartialDeliveryCaptureRequest(
            orderId: $orderId,
            partialDeliveryId: isset($data['partialDeliveryId']) ? (string) $data['partialDeliveryId'] : null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            currencyCode: isset($data['currency']) ? (string) $data['currency'] : null,
            externalReference: isset($data['externalReference']) ? (string) $data['externalReference'] : null,
            payload: \is_array($data['payload'] ?? null) ? $data['payload'] : []
        );

        try {
            $result = $this->partialDeliveryService->requestCapture($captureRequest, $context);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'status' => $result->status,
            'reference' => $result->reference,
            'message' => $result->message,
        ]);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['success' => false, 'error' => $message], $status);
    }
}
