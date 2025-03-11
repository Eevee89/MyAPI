<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\AuthService;
use App\Repository\MonsterRepository;

class MonsterController extends AbstractController
{
    private $repository;

    public function __construct(MonsterRepository $repository)
    {
        $this->repository = $repository;
    }
    
    #[Route('/api/monsters', name: 'api_cities', methods: ['GET'])]
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
        
        if (isset($res['roles']) && in_array("ROLE_USER", $res["roles"])) {
            $monsters = $this->repository->findByGame($game, $rank);
            $names = array_map(fn($monster) => $monster->getName(), $monsters);
            return new JsonResponse($names);
        }

        return new JsonResponse(['message' => 'Unauthorized'], 401);
    }
}