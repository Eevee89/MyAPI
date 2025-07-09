<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\AuthService;
use App\Repository\MonsterRepository;
use App\Repository\GameRepository;
use OpenApi\Attributes as OA;

#[OA\Tag("Spinner MH")]
class MonsterController extends AbstractController
{
    private $monsterRepository;
    private $gameRepository;

    public function __construct(MonsterRepository $monsterRepository, GameRepository $gameRepository)
    {
        $this->monsterRepository = $monsterRepository;
        $this->gameRepository = $gameRepository;
    }
    
    #[Route('/spinner_mh/monsters', name: 'spinner_mh_monsters', methods: ['GET'])]
    #[OA\Parameter(
        name: 'game',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string'),
        example: "MHW"
    )]
    #[OA\Parameter(
        name: 'rank',
        in: 'query',
        required: true,
        schema: new OA\Schema(type: 'string'),
        example: "HR"
    )]
    #[OA\Response(
        response: 200,
        description: 'OK',
        content: new OA\MediaType(
            mediaType: "application/json",
            schema: new OA\Schema(
                type: "array",
                items: new OA\Items(type: 'string', example: "Grand Jagras")
            )
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function getMonsters(Request $request): JsonResponse
    {
        $authorizationHeader = $request->headers->get("authorization");
        $res = AuthService::auth($authorizationHeader);

        if (!$res["userInfos"]) {
            return new JsonResponse(['message' => $res["message"]], 401);
        }

        $res = $res["userInfos"];

        $game = $request->query->get("game");
        $rank = $request->query->get("rank");

        if (!$game || !$rank) {
            return new JsonResponse(['message' => 'Bad request'], 400);
        }
        
        if (isset($res['roles']) && in_array("ROLE_API", $res["roles"])) {
            $monsters = $this->monsterRepository->findByGame($game, $rank);
            $names = array_map(fn($monster) => $monster->getName(), $monsters);
            return new JsonResponse($names);
        }

        return new JsonResponse(['message' => 'Unauthorized'], 401);
    }

    #[Route('/spinner_mh/games', name: 'spinner_mh_games', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'OK',
        content: new OA\MediaType(
            mediaType: "application/json",
            schema: new OA\Schema(
                type: "array",
                items: new OA\Items(
                    type: "object",
                    properties: [
                        new OA\Property(property: "abrev", type: "string", example: "MHW"),
                        new OA\Property(property: "name", type: "string", example: "Monster Hunter World")
                    ]
                )
            )
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function getGames(Request $request): JsonResponse
    {
        $authorizationHeader = $request->headers->get("authorization");
        $res = AuthService::auth($authorizationHeader);

        if (!$res["userInfos"]) {
            return new JsonResponse(['message' => $res["message"]], 401);
        }

        $res = $res["userInfos"];
        
        if (isset($res['roles']) && in_array("ROLE_API", $res["roles"])) {
            $games = $this->gameRepository->findAll();
            $res = [];
            foreach ($games as $game) {
                $res[] = [
                    "abrev" => $game->getAbrev(),
                    "name" => $game->getName()
                ];
            }
            return new JsonResponse($res);
        }

        return new JsonResponse(['message' => 'Unauthorized'], 401);
    }
}