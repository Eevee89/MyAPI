<?php

namespace App\Controller;

use App\Entity\Quote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\AuthService;
use App\Repository\QuoteRepository;
use OpenApi\Attributes as OA;

#[OA\Tag("Say The Line")]
#[Route('/saytheline')]
class QuoteController
{
    private $repository;
    private EntityManagerInterface $entityManager;

    public function __construct(QuoteRepository $repository, EntityManagerInterface $entityManager)
    {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
    }
    
    #[Route('/quotes', name: 'saytheline_quotes', methods: ['GET'])]
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
                        new OA\Property(property: "id", type: "integer"),
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
                    "id" => $quote->getId(),
                    "username" => $quote->getUser(),
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


    #[Route('/quotes-by-user', name: 'saytheline_quotes_user', methods: ['GET'])]
    #[OA\Parameter(
        name: 'username',
        in: 'query',
        schema: new OA\Schema(type: 'string'),
        example: 'saytheline'
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
                        new OA\Property(property: "id", type: "integer"),
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
    public function getQuotesByUser(Request $request): JsonResponse
    {
        $authorizationHeader = $request->headers->get("authorization");
        $res = AuthService::auth($authorizationHeader);

        if (!$res["userInfos"]) {
            return new JsonResponse(['message' => $res["message"]], 401);
        }

        $res = $res["userInfos"];

        $username = $request->query->get("username");
        
        if (isset($res['roles']) && in_array("ROLE_API", $res["roles"])) {
            if (!$username) {
                return new JsonResponse(['message' => 'Bad request'], 400);
            }

            $quotes = $this->repository->findByUser($username);
            
            $res = [];
            foreach($quotes as $quote) {
                $res[] = [
                    "id" => $quote->getId(),
                    "username" => $username,
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


    #[Route('/quote', name: 'saytheline_add_quote', methods: ['POST'])]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: "application/json",
            schema: new OA\Schema(
                type: "object",
                properties: [
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
        ),
        required: true
    )]
    #[OA\Response(
        response: 201,
        description: 'Created',
        content: new OA\MediaType(
            mediaType: "application/json",
            schema: new OA\Schema(
                type: "object",
                properties: [
                    new OA\Property(property: "id", type: "integer")
                ]
            )
        ),
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function addQuote(Request $request): JsonResponse
    {
        $authorizationHeader = $request->headers->get("authorization");
        $res = AuthService::auth($authorizationHeader);

        if (!$res["userInfos"]) {
            return new JsonResponse(['message' => $res["message"]], 401);
        }

        $res = $res["userInfos"];
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (isset($res['roles']) && in_array("ROLE_API", $res["roles"])) {
            $expectedProperties = [
                'category',
                'quote',
                'oeuvre',
                'type',
                'characters',
                'episode',
                'release',
                'answer'
            ];

            $missingFields = [];

            foreach ($expectedProperties as $property) {
                if (!isset($data[$property])) {
                    return new JsonResponse(['message' => 'Bad request'], 400);
                }
            }

            if ($data['type'] === 0 && $data['characters'] === null) {
                return new JsonResponse(['message' => 'Bad request'], 400);
            }

            if (($data['category'] === 2 || $data['category'] === 3) && $data['episode'] === null) {
                return new JsonResponse(['message' => 'Bad request'], 400);
            }

            $quoteEntity = new Quote();

            $quoteEntity->setCategory($data['category']);
            $quoteEntity->setUser($res["username"]);
            $quoteEntity->setQuote($data['quote']);
            $quoteEntity->setOeuvre($data['oeuvre']);
            $quoteEntity->setType($data['type']);
            $quoteEntity->setCharacters($data['characters']);
            $quoteEntity->setEpisode($data['episode']);
            $quoteEntity->setRelease($data['release']);
            $quoteEntity->setAnswer($data['answer']);

            $this->entityManager->persist($quoteEntity);
            $this->entityManager->flush();

            return new JsonResponse(["id" => $quoteEntity->getId()], 201);
        }

        return new JsonResponse(['message' => 'Unauthorized'], 401);
    }

    #[Route('/quote', name: 'saytheline_edit_quote', methods: ['PUT'])]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: "application/json",
            schema: new OA\Schema(
                type: "object",
                properties: [
                    new OA\Property(property: "id", type: "integer"),
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
        ),
    )]
    #[OA\Response(
        response: 204,
        description: 'No content'
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function editQuote(Request $request): JsonResponse
    {
        $authorizationHeader = $request->headers->get("authorization");
        $res = AuthService::auth($authorizationHeader);

        if (!$res["userInfos"]) {
            return new JsonResponse(['message' => $res["message"]], 401);
        }

        $res = $res["userInfos"];
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (isset($res['roles']) && in_array("ROLE_API", $res["roles"])) {
            if ($data['id'] === null) {
                return new JsonResponse(['message' => 'Bad request'], 400);
            }

            $quoteEntity = $this->repository->find($data['id']);

            $quoteEntity->setCategory($data['category']);
            $quoteEntity->setQuote($data['quote']);
            $quoteEntity->setOeuvre($data['oeuvre']);
            $quoteEntity->setType($data['type']);
            $quoteEntity->setCharacters($data['characters']);
            $quoteEntity->setEpisode($data['episode']);
            $quoteEntity->setRelease($data['release']);
            $quoteEntity->setAnswer($data['answer']);

            $this->entityManager->persist($quoteEntity);
            $this->entityManager->flush();

            return new JsonResponse(null, 204);
        }

        return new JsonResponse(['message' => 'Unauthorized'], 401);
    }
}