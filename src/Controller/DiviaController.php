<?php

declare(strict_types=1);

namespace App\Controller;

use App\Manager\DiviaManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route('/divia', name: 'api_divia_')]
final class DiviaController extends AbstractController
{
    public function __construct(
        private readonly DiviaManager $diviaManager,
    ) {}

    #[Route('/lines', name: 'lines', methods: ['GET'])]
    public function lines(Request $request): JsonResponse
    {
        $mode = strtolower($request->query->getString('mode', 'tramway'));
        if (!in_array($mode, ['bus', 'tramway'], true)) {
            return $this->json(['error' => 'Le paramètre "mode" doit être "bus" ou "tramway".'], 400);
        }

        try {
            /** @var 'bus'|'tramway' $mode */
            return $this->json($this->diviaManager->listLines($mode));
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }
    }

    #[Route('/stops', name: 'stops', methods: ['GET'])]
    public function stops(Request $request): JsonResponse
    {
        $lineId = trim($request->query->getString('lineId', ''));
        if ($lineId === '') {
            return $this->json(['error' => 'Le paramètre "lineId" est requis.'], 400);
        }

        try {
            return $this->json($this->diviaManager->listStops($lineId));
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }
    }

    #[Route('/schedules', name: 'schedules', methods: ['GET'])]
    public function schedules(Request $request): JsonResponse
    {
        $lineId = trim($request->query->getString('lineId', ''));
        $stopAreaId = trim($request->query->getString('stopAreaId', ''));
        $direction = $request->query->get('direction');
        $limit = $request->query->getInt('limit', 0);

        if ($lineId === '' || $stopAreaId === '') {
            return $this->json([
                'error' => 'Les paramètres "lineId" et "stopAreaId" sont requis.',
            ], 400);
        }

        try {
            return $this->json($this->diviaManager->getSchedules(
                lineId: $lineId,
                stopAreaId: $stopAreaId,
                directionPrefix: is_string($direction) && $direction !== '' ? $direction : null,
                limitPerDirection: $limit > 0 ? $limit : null,
            ));
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }
    }

    #[Route('/departures', name: 'departures', methods: ['GET'])]
    public function departures(Request $request): JsonResponse
    {
        $mode = strtolower($request->query->getString('mode', 'tramway'));
        if (!in_array($mode, ['bus', 'tramway'], true)) {
            return $this->json(['error' => 'Le paramètre "mode" doit être "bus" ou "tramway".'], 400);
        }

        $lineCode = trim($request->query->getString('line', 'T1'));
        $stopName = trim($request->query->getString('stop', 'République'));
        $direction = $request->query->get('direction');
        $limit = max(1, $request->query->getInt('limit', 3));
        $compact = $request->query->getBoolean('compact', false);

        try {
            /** @var 'bus'|'tramway' $mode */
            $data = $this->diviaManager->getUpcomingDepartures(
                mode: $mode,
                lineCode: $lineCode,
                stopName: $stopName,
                directionPrefix: is_string($direction) && $direction !== '' ? $direction : null,
                limit: $limit,
            );

            return $this->json($compact ? $this->diviaManager->simplifySchedules($data) : $data);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }
    }
}
