<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Repository\UserRepository;
use App\Manager\UserManager;
use App\Security\Jwt\JwtEncoder;
use App\Serializer\MySerializer;
use OpenApi\Attributes as OA;

#[OA\Tag("Login")]
class APIController
{
    private $jwtEncoder;
    private $repository;

    public function __construct(UserRepository $repository)
    {
        $this->jwtEncoder = new JwtEncoder(new MySerializer());
        $this->repository = $repository;
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: "application/json",
            schema: new OA\Schema(
                type: "object",
                properties: [
                    new OA\Property(property: "username", type: "string"),
                    new OA\Property(property: "password", type: "string")
                ],
                required: ["username", "password"]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Login Successful',
        content: new OA\MediaType(
            mediaType: "application/json",
            schema: new OA\Schema(
                type: "object",
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "token", type: "string")
                ]
            )
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request'
    )]
    public function login(Request $request): JsonResponse
    {
        $donnees = json_decode($request->getContent(), true);

        $user = $this->repository->findByFullName($donnees["username"]);

        if (!$user) {
            return new JsonResponse(['message' => 'Bad request'], 400);
        }

        if (!password_verify($donnees["password"], $user->getPassword())) {
            return new JsonResponse(['message' => 'Bad request'], 400);
        }

        $token = $this->jwtEncoder->encodePayload($user);

        $response = new JsonResponse(['message' => 'Login successful', 'token' => $token]);

        return $response;
    }
}