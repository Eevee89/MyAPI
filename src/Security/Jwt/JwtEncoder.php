<?php

namespace App\Security\Jwt;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtEncoder implements JWTEncoderInterface
{
    private string $secretKey;
    private SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $resolvedPath = str_replace('%kernel.project_dir%', __DIR__."/../../..", $_ENV['JWT_SECRET_KEY']);
        $this->secretKey = file_get_contents($resolvedPath);
        $this->serializer = $serializer;
    }

    public function encode(array $payload): string
    {
        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    public function decode($token): array
    {
        if (!is_string($token)) {
            return [];
        }

        try {
            $decoded = (array) JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function encodePayload(User $user): string
    {
        $payload = [
            'username' => $user->getFullname(),
            'roles' => $user->getRoles(),
            'exp' => time() + 3600,
        ];

        return $this->encode($payload);
    }

    public function decodeJwt(string $jwt): ?array
    {
        return $this->decode($jwt);
    }

    public function getUsernameForToken(string $jwt): ?string
    {
        $decoded = $this->decode($jwt);

        return $decoded['username'] ?? null;
    }

    public function refresh(string $jwt): string
    {
        $payload = $this->decode($jwt);
        if(!isset($payload['exp'])){
            return "";
        }
        $payload['exp'] = time() + 3600;

        return $this->encode($payload);
    }
}