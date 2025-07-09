<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\AuthService;
use App\Repository\QuoteRepository;
use OpenApi\Attributes as OA;

#[OA\Tag("Say The Line")]
class QuoteController extends AbstractController
{
    private $repository;

    public function __construct(QuoteRepository $repository)
    {
        $this->repository = $repository;
    }
    
    #[Route('/saytheline/quotes', name: 'saytheline_quotes', methods: ['GET'])]
    #[OA\Parameter(
        name: 'category',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
        example: '1'
    )]
    #[OA\Parameter(
        name: 'type',
        in: 'query',
        schema: new OA\Schema(type: 'integer'),
        example: "1"
    )]
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
                        new OA\Property(property: "username", type: "string"),
                        new OA\Property(property: "category", type: "integer"),
                        new OA\Property(property: "quote", type: "string"),
                        new OA\Property(property: "oeuvre", type: "string"),
                        new OA\Property(property: "type", type: "integer"),
                        new OA\Property(property: "characters", type: "string"),
                        new OA\Property(property: "episode", type: "string"),
                        new OA\Property(property: "release", type: "integer"),
                        new OA\Property(property: "answer", type: "string")
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
    public function getQuotes(Request $request): JsonResponse
    {
        $authorizationHeader = $request->headers->get("authorization");
        $res = AuthService::auth($authorizationHeader);

        if (!$res["userInfos"]) {
            return new JsonResponse(['message' => $res["message"]], 401);
        }

        $res = $res["userInfos"];

        $category = $request->query->get("category");
        $rawtype = $request->query->get("type");
        $type = $rawtype === null ? null : $rawtype == 1;
        
        if (isset($res['roles']) && in_array("ROLE_API", $res["roles"])) {
            $quotes = $this->repository->findWithCategoryAndType($category, $type);
            $res = [];
            foreach($quotes as $quote) {
                $res[] = [
                    "username" => $quote->getUser() ? $quote->getUser()->getFullname() : 'N/A',
                    'category' => $quote->getCategory(),
                    'quote' => $quote->getQuote(),
                    'oeuvre' => $quote->getOeuvre(),
                    'type' => $quote->isWhoSaid(),
                    'characters' => $quote->getCharacters(),
                    'episode' => $quote->getEpisode(),
                    'release' => $quote->getRelease(),
                    'answer' => $quote->getAnswer(),
                ];
            }
            return new JsonResponse($res);
        }

        return new JsonResponse(['message' => 'Unauthorized'], 401);
    }
}