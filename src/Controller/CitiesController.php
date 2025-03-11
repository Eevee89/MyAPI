<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\AuthService;

class CitiesController extends AbstractController
{
    #[Route('/api/cities', name: 'api_cities', methods: ['GET'])]
    public function getCities(Request $request): JsonResponse
    {
        $authorizationHeader = $request->headers->get("authorization");
        $res = AuthService::auth($authorizationHeader);

        if (!$res["userInfos"]) {
            return new JsonResponse(['message' => $res["message"]], 401);
        }

        $res = $res["userInfos"];
        
        if (isset($res['roles']) && in_array("ROLE_USER", $res["roles"])) {
            $cities = ['Paris', 'Lyon', 'Marseille'];
            return new JsonResponse($cities);
        }

        return new JsonResponse(['message' => 'Unauthorized'], 401);
    }
}