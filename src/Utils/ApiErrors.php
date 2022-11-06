<?php 
namespace AXS\ApiBundle\Utils;

class ApiErrors {
    public static function imposibleToParseRequest(){
        return [
            "status" => "error",
            "errno" => 0,
            "message" => "Fue imposible procesar la petición ¿enviaste un json valido?"
        ];
    }
    public static function noLoginParameters(){
        return [
            "status" => "error",
            "errno" => 1,
            "message" => "Parametros insuficientes ¿olvidaste incluir el usuario [user] o contraseña [password]?"
        ];
    }
    public static function incorrectCredentials(){
        return [
            "status" => "error",
            "errno" => 2,
            "message" => "Credenciales incorrectas"
        ];
    }
    public static function unauthorized(){
        return [
            "status" => "error",
            "errno" => 3,
            "message" => "Sin permisos, puede que tu token este caducado o no hayas iniciado seción"
        ];
    }
    public static function classNotFound(){
        return [
            "status" => "error",
            "errno" => 4,
            "message" => "La clase no ha sido encontrada"
        ];
    }
    public static function resultNotFound(){
        return [
            "status" => "error",
            "errno" => 5,
            "message" => "El objeto solicitado no ha sido encontrado"
        ];
    }
}