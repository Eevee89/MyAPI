<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Repository\UserRepository;
use App\Manager\UserManager;
use App\Security\Jwt\JwtEncoder;
use App\Serializer\MySerializer;

class APIController extends AbstractController
{
    private $jwtEncoder;
    private $repository;

    public function __construct(UserRepository $repository)
    {
        $this->jwtEncoder = new JwtEncoder(new MySerializer());
        $this->repository = $repository;
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {

        $donnees = json_decode($request->getContent(), true);

        $user = $this->repository->findByFullName($donnees["username"]);

        if (!$user) {
            return new JsonResponse(['message' => 'Bad request'], 400);
        }

        if (!password_verify($donnees["password"], $user->getPassword())) {
            return new JsonResponse(['message' => 'Unauthorized'], 401);
        }

        $token = $this->jwtEncoder->encode([
            'user' => $user->getFullName(),
            'password' => $user->getPassword(),
            'roles' => $user->getRoles(),
        ]);

        $response = new JsonResponse(['message' => 'Login successful', 'token' => $token]);
        $cookie = Cookie::create('jwt_token', $token, time() + 3600, '/', null, true, true, false, 'lax');
        $response->headers->setCookie($cookie);

        return $response;
    }
}