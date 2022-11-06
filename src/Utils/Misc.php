<?php 
namespace AXS\ApiBundle\Utils;

class Misc {

    public const letras = ["A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","W","X","Y","Z"];
    public const bearer = "bearer";
    static public function getletra(int $int){
        $count = count(self::letras);
        $result = "";
        $length = intval(floor($int/$count));
        for ($i=0; $i < $length; $i++) { 
            $result .= self::letras[0];
        } 
        $result .= self::letras[$int % $count];
        return $result;
    }
    static public function subscripter(string $string) :string{
        $string = explode("\\", $string);
        $array = str_split(array_pop($string));
        $result = "";
        foreach ($array as $key => $value) {
            if ($key != 0 && preg_match("/[A-Z]/", $value)){
                $value = "_".$value;
            }
            $result .= strtolower($value);
        }
        return $result;
    }
}