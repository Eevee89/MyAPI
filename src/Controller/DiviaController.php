<?php

declare(strict_types=1);

namespace App\Controller;

use App\Manager\DiviaManager;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[OA\Tag(name: 'Divia', description: 'Prochains passages, lignes et arrêts du réseau DiviaMobilités (Dijon)')]
#[Route('/api/divia', name: 'api_divia_')]
final class DiviaController extends AbstractController
{
    public function __construct(
        private readonly DiviaManager $diviaManager,
    ) {}

    #[OA\Get(
        path: '/api/divia/lines',
        summary: 'Lister les lignes Divia par mode de transport',
        description: 'Retourne la liste des lignes de tramway ou de bus du réseau DiviaMobilités.',
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'mode',
                in: 'query',
                required: false,
                description: 'Mode de transport ("bus" ou "tramway").',
                schema: new OA\Schema(type: 'string', enum: ['bus', 'tramway'], default: 'tramway'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des lignes récupérée avec succès.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: 'line:CGD:T1'),
                            new OA\Property(property: 'name', type: 'string', example: 'DIJON Gare <> QUETIGNY Centre La Parenthèse'),
                            new OA\Property(property: 'code', type: 'string', example: 'T1'),
                            new OA\Property(property: 'color', type: 'string', example: 'AB0672'),
                            new OA\Property(property: 'text_color', type: 'string', example: 'FFFFFF'),
                            new OA\Property(property: 'opening_time', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'closing_time', type: 'string', format: 'date-time', nullable: true),
                        ],
                    ),
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Paramètre "mode" invalide.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Le paramètre "mode" doit être "bus" ou "tramway".'),
                    ],
                ),
            ),
            new OA\Response(
                response: 502,
                description: 'Erreur lors de l\'appel à l\'API distante Divia / Navitia.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string'),
                    ],
                ),
            ),
        ],
    )]
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

    #[OA\Get(
        path: '/api/divia/stops',
        summary: 'Lister les arrêts d\'une ligne Divia',
        description: 'Retourne les zones d\'arrêt (stop_areas) desservies par la ligne spécifiée.',
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'lineId',
                in: 'query',
                required: true,
                description: 'Identifiant Navitia de la ligne.',
                schema: new OA\Schema(type: 'string', example: 'line:CGD:T1'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des arrêts de la ligne.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: 'stop_area:CGD:SA:4-1477'),
                            new OA\Property(property: 'name', type: 'string', example: 'République'),
                            new OA\Property(property: 'label', type: 'string', example: 'République (Dijon)'),
                            new OA\Property(property: 'timezone', type: 'string', example: 'Europe/Paris'),
                            new OA\Property(
                                property: 'coord',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'lon', type: 'number', format: 'float', example: 5.044204),
                                    new OA\Property(property: 'lat', type: 'number', format: 'float', example: 47.326866),
                                ],
                            ),
                        ],
                    ),
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Paramètre "lineId" manquant.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Le paramètre "lineId" est requis.'),
                    ],
                ),
            ),
            new OA\Response(
                response: 502,
                description: 'Erreur lors de l\'appel à l\'API distante Divia / Navitia.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string'),
                    ],
                ),
            ),
        ],
    )]
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

    #[OA\Get(
        path: '/api/divia/schedules',
        summary: 'Récupérer les prochains passages par identifiants de ligne et d\'arrêt',
        description: 'Retourne les horaires temps réel / théoriques pour un couple lineId + stopAreaId.',
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'lineId',
                in: 'query',
                required: true,
                description: 'Identifiant Navitia de la ligne.',
                schema: new OA\Schema(type: 'string', example: 'line:CGD:T1'),
            ),
            new OA\Parameter(
                name: 'stopAreaId',
                in: 'query',
                required: true,
                description: 'Identifiant Navitia de la zone d\'arrêt.',
                schema: new OA\Schema(type: 'string', example: 'stop_area:CGD:SA:4-1477'),
            ),
            new OA\Parameter(
                name: 'direction',
                in: 'query',
                required: false,
                description: 'Préfixe du nom de direction ou headsign (insensible à la casse).',
                schema: new OA\Schema(type: 'string', example: 'QUETIGNY Centre'),
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Nombre maximum de passages retournés par direction.',
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 3),
            ),
            new OA\Parameter(
                name: 'compact',
                in: 'query',
                required: false,
                description: 'Retourne un format JSON allégé si true / 1.',
                schema: new OA\Schema(type: 'boolean', default: false),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des passages (format complet ou allégé selon "compact").',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'object'),
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Paramètres "lineId" ou "stopAreaId" manquants.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Les paramètres "lineId" et "stopAreaId" sont requis.'),
                    ],
                ),
            ),
            new OA\Response(
                response: 502,
                description: 'Erreur lors de l\'appel à l\'API distante Divia / Navitia.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string'),
                    ],
                ),
            ),
        ],
    )]
    #[Route('/schedules', name: 'schedules', methods: ['GET'])]
    public function schedules(Request $request): JsonResponse
    {
        $lineId = trim($request->query->getString('lineId', ''));
        $stopAreaId = trim($request->query->getString('stopAreaId', ''));
        $direction = $request->query->get('direction');
        $limit = $request->query->getInt('limit', 0);
        $compact = $request->query->getBoolean('compact', false);

        if ($lineId === '' || $stopAreaId === '') {
            return $this->json([
                'error' => 'Les paramètres "lineId" et "stopAreaId" sont requis.',
            ], 400);
        }

        try {
            $data = $this->diviaManager->getSchedules(
                lineId: $lineId,
                stopAreaId: $stopAreaId,
                directionPrefix: is_string($direction) && $direction !== '' ? $direction : null,
                limitPerDirection: $limit > 0 ? $limit : null,
            );

            return $this->json($compact ? $this->diviaManager->simplifySchedules($data) : $data);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }
    }

    #[OA\Get(
        path: '/api/divia/departures',
        summary: 'Récupérer directement les prochains passages par code de ligne et nom d\'arrêt',
        description: 'Résout automatiquement la ligne et l\'arrêt, met en cache brièvement le résultat et retourne les prochains passages.',
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'mode',
                in: 'query',
                required: false,
                description: 'Mode de transport ("bus" ou "tramway").',
                schema: new OA\Schema(type: 'string', enum: ['bus', 'tramway'], default: 'tramway'),
            ),
            new OA\Parameter(
                name: 'line',
                in: 'query',
                required: false,
                description: 'Code commercial de la ligne (ex: T1, T2, L3, B10).',
                schema: new OA\Schema(type: 'string', default: 'T1', example: 'T1'),
            ),
            new OA\Parameter(
                name: 'stop',
                in: 'query',
                required: false,
                description: 'Nom exact de l\'arrêt (ex: République, Darcy, Gare SNCF).',
                schema: new OA\Schema(type: 'string', default: 'République', example: 'République'),
            ),
            new OA\Parameter(
                name: 'direction',
                in: 'query',
                required: false,
                description: 'Préfixe du nom de direction ou headsign (insensible à la casse).',
                schema: new OA\Schema(type: 'string', example: 'QUETIGNY Centre'),
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Nombre maximum de prochains passages par direction.',
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 3, example: 3),
            ),
            new OA\Parameter(
                name: 'compact',
                in: 'query',
                required: false,
                description: 'Retourne le format allégé optimisé pour le front mobile/web.',
                schema: new OA\Schema(type: 'boolean', default: false, example: true),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Prochains passages récupérés avec succès.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'line_code', type: 'string', example: 'T1'),
                            new OA\Property(property: 'color', type: 'string', example: 'AB0672'),
                            new OA\Property(property: 'text_color', type: 'string', example: 'FFFFFF'),
                            new OA\Property(property: 'stop_name', type: 'string', example: 'République'),
                            new OA\Property(property: 'stop_point_id', type: 'string', example: 'stop_point:CGD:SP:4-1560'),
                            new OA\Property(property: 'direction', type: 'string', example: 'QUETIGNY Centre La Parenthèse (Quetigny)'),
                            new OA\Property(property: 'headsign', type: 'string', example: 'QUETIGNY Centre'),
                            new OA\Property(
                                property: 'departures',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'date_time', type: 'string', format: 'date-time', example: '2026-11-21T19:26:24+01:00'),
                                        new OA\Property(property: 'base_date_time', type: 'string', format: 'date-time', example: '2026-11-21T19:26:24+01:00'),
                                        new OA\Property(property: 'data_freshness', type: 'string', enum: ['realtime', 'base_schedule'], example: 'realtime'),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Paramètre "mode" invalide.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Le paramètre "mode" doit être "bus" ou "tramway".'),
                    ],
                ),
            ),
            new OA\Response(
                response: 502,
                description: 'Ligne/arrêt introuvable ou erreur API distante.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Arrêt "Inconnu" introuvable sur la ligne "T1".'),
                    ],
                ),
            ),
        ],
    )]
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