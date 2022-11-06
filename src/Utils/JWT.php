<?php 
namespace AXS\ApiBundle\Utils;
class JWT {
    public static function check(string $token, string $secret) : bool {
        try{
            list($he, $pe, $se) = explode(".", $token);
        } catch (\Throwable $th){
            return false;
        }
        $header = (array) json_decode(base64_decode($he));
        $payload = (array) json_decode(base64_decode($pe));
        if ($payload["exp"] - time() <= 0) return false;
        $checkToken = self::generate($header, $payload, $secret);
        $test = explode(".", $checkToken)[2] == $se;
        return $test;
    }
    public static function generate(array $header, array $payload, string $secret){
        $he = self::encode(json_encode($header));
        $pe = self::encode(json_encode($payload));
        $sign = hash_hmac("SHA256", "$he.$pe", $secret, true);
        $se = self::encode($sign);
        return "$he.$pe.$se";
    }
    public static function encode($str) {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }

    public static function decode($token){
        try{
            list($he, $pe, $se) = explode(".", $token);
        } catch (\Throwable $th){
            return false;
        }
        $header = (array) json_decode(base64_decode($he));
        $payload = (array) json_decode(base64_decode($pe));
        return [
            "header" => $header,
            "payload" => $payload
        ];
    }

}