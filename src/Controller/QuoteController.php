<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\AuthService;
use App\Repository\QuoteRepository;

class QuoteController extends AbstractController
{
    private $repository;

    public function __construct(QuoteRepository $repository)
    {
        $this->repository = $repository;
    }
    
    #[Route('/saytheline/quotes', name: 'saytheline_quotes', methods: ['GET'])]
    public function getQuotes(Request $request): JsonResponse
    {
        $authorizationHeader = $request->headers->get("authorization");
        $res = AuthService::auth($authorizationHeader);

        if (!$res["userInfos"]) {
            return new JsonResponse(['message' => $res["message"]], 401);
        }

        $res = $res["userInfos"];

        $category = $request->query->get("category");
        
        if (isset($res['roles']) && in_array("ROLE_API", $res["roles"])) {
            $quotes = $this->repository->findAll();
            $res = [];
            foreach($quotes as $quote) {
                $res[] = [
                    "username" => $quote->getUser() ? $quote->getUser()->getFullname() : 'N/A',
                    'category' => $quote->getCategory(),
                    'quote_text' => $quote->getQuote(),
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