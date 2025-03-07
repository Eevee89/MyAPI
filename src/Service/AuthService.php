<?php
namespace App\Service;

use App\Security\Jwt\JwtEncoder;
use App\Serializer\MySerializer;

class AuthService
{
    static public function auth(?string $authorizationHeader)
    {
        try {
            $return = [
                "userInfos" => null,
                "message" => "Unauthorized"
            ];
    
            if (!$authorizationHeader) {
                $return["message"] = "Authorization header missing";
                return $return;
            }
    
            $tokenParts = explode(" ", $authorizationHeader);
    
            if (count($tokenParts) !== 2 || strtolower($tokenParts[0]) !== 'bearer') {
                $return["message"] = "Invalid authorization header format";
                return $return;
            }
    
            $token = $tokenParts[1];
            $jwtEncoder = new JwtEncoder(new MySerializer());
            $res = $jwtEncoder->decode($token);

            $return["userInfos"] = $res;
            $return["message"] = "";

            return $return;
        } catch (Throwable $e) {
            return [
                "userInfos" => null,
                "message" => "Unauthorized"
            ];
        }
    }
}