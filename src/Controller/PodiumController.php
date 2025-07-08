<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\AuthService;
use App\Repository\PodiumRepository;

class PodiumController extends AbstractController
{
    private $repository;

    public function __construct(PodiumRepository $repository)
    {
        $this->repository = $repository;
    }
    
    #[Route('/homesite/podium', name: 'homesite_podium', methods: ['GET'])]
    public function getPodium(Request $request): JsonResponse
    {
        $authorizationHeader = $request->headers->get("authorization");
        $res = AuthService::auth($authorizationHeader);

        if (!$res["userInfos"]) {
            return new JsonResponse(['message' => $res["message"]], 401);
        }

        $res = $res["userInfos"];
        
        if (isset($res['roles']) && in_array("ROLE_API", $res["roles"])) {
            $result = $this->repository->findAll();
            return new JsonResponse(array_map(fn($elt) => $elt->toArray(), $result));
        }

        return new JsonResponse(['message' => 'Unauthorized'], 401);
    }
}