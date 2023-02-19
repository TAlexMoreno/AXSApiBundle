<?php
namespace AXS\ApiBundle\Controller;

use AXS\ApiBundle\Utils\ApiErrors;
use AXS\ApiBundle\Utils\JWT;
use AXS\ApiBundle\Utils\Misc;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Error;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class Api extends AbstractController {
    
    public String $className;
    public function __construct(
        private string $api_secret,
        private string $user_entity, 
        private string $user_uid,
        private int $exp_defer,
        private EntityManagerInterface $em, 
        private Security $security, 
        private UserPasswordHasherInterface $passHasher
    ){}

    public function getSecret(){
        return $this->api_secret;
    }

    #[Route("/API", name:"axs_api_index_docs")]
    public function index(Request $req){
        return $this->json([
            "status" => "ok",
            "welcome" => "Bienvenido al Api Bundle de AXS"
        ]);
    }

    #[Route("/API/login", name: "axs_api_login", methods: ["POST"])]
    public function login(Request $req){
        try {
            $parameters = json_decode($req->getContent(), false);
        } catch (\Throwable $th) {
            return $this->json(ApiErrors::imposibleToParseRequest(), Response::HTTP_BAD_REQUEST);
        }
        if (!isset($parameters->user) || !isset($parameters->password)) return $this->json(ApiErrors::noLoginParameters(), Response::HTTP_BAD_REQUEST);
        /** @var \Doctrine\ORM\EntityRepository $userRepo */
        $userRepo = $this->em->getRepository($this->user_entity);
        $reqUser = $userRepo->findOneBy([$this->user_uid => $parameters->user]);
        if (!$reqUser || !$this->passHasher->isPasswordValid($reqUser, $parameters->password)) return $this->json(ApiErrors::incorrectCredentials(), Response::HTTP_UNAUTHORIZED);
        $exp = (new DateTime())->modify("+ 72 Hours");
        $token = JWT::generate([
            "alg" => "HS256",
            "typ" => "JWT"
        ], [
            "exp" => $exp->getTimestamp(),
            "sub" => json_encode(method_exists($reqUser, "serialize") ? $reqUser->serialize() : $reqUser)
        ], $this->api_secret);
        return $this->json(["status" => "ok", "token" => $token]);
    }

    #[Route("/API/list", name: "axs_api_list")]
    public function list(Request $req){
        $validation = $this->validateToken($req);
        if (!$validation["check"]) return $this->json(ApiErrors::unauthorized());
        $clases = array_map(function(ClassMetadata $metadata){
            return strtolower($this->CNToName($metadata->getName()));
        }, $this->em->getMetadataFactory()->getAllMetadata());
        return $this->json([
            "Entidades" => $clases
        ]);
    }

    #[Route("/API/{entity}", name:"axs_entity_get", methods:["GET"])]
    public function entityGet(string $entity, Request $req){
        $validation = $this->validateToken($req);
        if (!$validation["check"]) return $this->json(ApiErrors::unauthorized());
        $this->className = $this->nameToCN($entity);
        $repo = $this->getRepo($this->className);
        if (!$repo) return $this->json(ApiErrors::classNotFound(), Response::HTTP_NOT_FOUND);
        $query = $repo->createQueryBuilder("e");
        $this->getQuery($query, $req->query->all());
        $result = $query->getQuery()->getResult();
        $result = array_map(function ($el){
            if (gettype($el) == "object" && method_exists($el, "serialize")){
                return $el->serialize();
            }else {
                return $el;
            }
        }, $result);
        return $this->json(["status" => "ok", "data" => $result]);
    }
    #[Route("/API/{entity}", name:"axs_entity_post", methods:["POST"])]
    public function entityPost(string $entity, Request $req){
        $validation = $this->validateToken($req);
        if (!$validation["check"]) return $this->json(ApiErrors::unauthorized());
        $this->className = $this->nameToCN($entity);
        $data = count ($req->request->all()) ? $req->request->all() : json_decode($req->getContent(), true);
        $validation = $this->validate($data);
        if ($validation["veredict"]){
            $result = $this->save($data);
            return $result;
        }
    }
    #[Route("/API/{entity}/{id}", name:"axs_entity_put", methods:["PUT"])]
    public function entityPut(int $id, string $entity, Request $req){
        $validation = $this->validateToken($req);
        if (!$validation["check"]) return $this->json(ApiErrors::unauthorized());
        $this->className = $this->nameToCN($entity);
        $repo = $this->getRepo($this->className);
        if (!$repo) return $this->json(ApiErrors::classNotFound(), Response::HTTP_NOT_FOUND);
        $object = $repo->find($id);
        if (!$object) return $this->json(ApiErrors::resultNotFound(), Response::HTTP_NOT_FOUND);
        $data = json_decode($req->getContent(), true);
        $this->specialCases($data);
        $validation = $this->validate($data, null, true);
        if ($validation["veredict"]){
            $this->updateOne($data, $object);
            return new JsonResponse(["status" => "ok", "data" => $object]);
        }
    }
    #[Route("/API/{entity}/{id}", name:"axs_entity_del", methods:["DELETE"])]
    public function entityDelete(int $id, string $entity, Request $req){
        $validation = $this->validateToken($req);
        if (!$validation["check"]) return $this->json(ApiErrors::unauthorized());
        $this->className = $this->nameToCN($entity);
        $repo = $this->getRepo($this->className);
        if (!$repo) return $this->json(ApiErrors::classNotFound(), Response::HTTP_NOT_FOUND);
        $object = $repo->find($id);
        if (!$object) return $this->json(ApiErrors::resultNotFound(), Response::HTTP_NOT_FOUND);
        //TODO: Terminar deletion
    }

    private function updateOne(array $data, object $entity): JsonResponse {
        $response = $this->fillAndPersist($data, $entity);
        if ($response->getStatusCode() == Response::HTTP_OK){
            $data = json_decode($response->getContent())->data;
            $data->action = "create";
            $clase = Misc::subscripter($this->class);
            $id = $data->id ?? $data["id"];
            // $this->spread([
            //     "action" => "message",
            //     "topics" => ["/API/update/{$clase}", "/API/update/{$clase}/{$id}"],
            //     "data" => $data
            // ]);
        }
        return $response;
    }
    private function specialCases(&$data){
        foreach ($data as $key => $value){
            if (preg_match("/\w+autocomplete/", $key)) {
                $data[str_replace("autocomplete", "", $key)] = $value;
                unset($data[$key]);
            }
        }
    }
    private function validate(array $data, string $clase = null, bool $isUpdating=false) : array {
        $c = $clase ?? $this->className;
        $metadata = $this->em->getClassMetadata($c);
        $mandatoryFields = [];
        $allFields = [];
        $toInsert = [];
        $assosiationFields = $metadata->associationMappings;
        foreach ($metadata->fieldMappings as $field => $metadata) {
            if ($field == "id") continue;
            //TODO: Refactor apihide
            $check = array_key_exists("options", $metadata) && array_key_exists("comment", $metadata["options"]) && $metadata["options"]["comment"] == "apihide";
            if ($check) continue;
            if (!$metadata["nullable"] && !isset($metadata["id"])) $mandatoryFields[] = $field;
            $allFields[$field] = $metadata;
        }
        $mandatoryValid = [];
        if (!$isUpdating){
            foreach ($mandatoryFields as $field) {
                if (!isset($data[$field])) $this->validationErrors["required"][] = $field;
                $mandatoryValid[$field] = isset($data[$field]);
                if (isset($data[$field])) $toInsert[] = $field;
            }
        }

        $typeValid = [];
        foreach ($allFields as $field => $metadata) {
            if (!isset($data[$field])) continue;
            $typeValid[$field] = $this->checkType($data[$field], $metadata["type"]);
        }

        $assosiationValid = [];
        foreach ($assosiationFields as $field => $metadata) {
            if (!isset($data[$field])) continue;
            if (gettype($data[$field]) == "integer"){
                $repo = $this->em->getRepository($metadata["targetEntity"]);
                $object = $repo->find($data[$field]);
                $assosiationValid[$field] =  $object != null;
                if ($object != null) $toInsert[] = $field;
            }
        }

        $veredict = array_reduce($mandatoryValid, function($carry, $el){
            return $carry && $el;
        }, true) && array_reduce($typeValid, function($carry, $el){
            return $carry && $el;
        }, true) && array_reduce($assosiationValid, function($carry, $el){
            return $carry && $el;
        }, true);
        $return = [
            "veredict" => $veredict,
            "type" => array_merge($typeValid, $assosiationValid),
            "available" => $allFields,
            "assosiation" => $assosiationFields,
            "assocValid" => $assosiationValid,
            "toInsert" => $toInsert
        ];
        if (!$isUpdating) $return["mandatory"] = $mandatoryValid;
        return $return;
    }
    public function save(array $data) : JsonResponse {
        $entity = new $this->class();
        $response = $this->fillAndPersist($data, $entity);
        if ($response->getStatusCode() == Response::HTTP_OK){
            $data = json_decode($response->getContent())->data;
            $data->action = "create";
            $clase = Misc::subscripter($this->class);
            // $this->spread([
            //     "action" => "message",
            //     "topics" => ["/API/save/{$clase}", "/API/save/{$clase}/{$data->id}"],
            //     "data" => $data
            // ]);
        }
        return $response;
    }
    public function fillAndPersist(array $data, $entity, $bypass=false) {
        $m = $this->em->getClassMetadata(get_class($entity));
        //TODO: Refactor apihide
        foreach ($m->fieldMappings as $field => $metadata) {
            $check = array_key_exists("options", $metadata) && array_key_exists("comment", $metadata["options"]) && $metadata["options"]["comment"] == "apihide";
            if ($check) continue;
            if (!isset($metadata["id"]) && isset($data[$field])){
                $value = $data[$field];
                $this->preprocess($value, $metadata["type"]);
                $method = "set" . ucfirst($field);
                if ($method == "setPassword") $value = $this->hasher->hashPassword($entity, $value);
                try {
                    $entity->$method($value);
                } catch (\Throwable $th) {
                    dd($method, $value, $th);
                }
            }
        }
        foreach ($m->associationMappings as $field => $metadata) {
            //TODO: Refactor apihide
            $check = array_key_exists("options", $metadata) && 
                array_key_exists("comment", $metadata["options"]) && 
                $metadata["options"]["comment"] == "apihide"
            ;
            if ($check) continue;
            if (!isset($data[$field])) continue;
            if (is_numeric($data[$field])){
                $repo = $this->em->getRepository($metadata["targetEntity"]);
                $relation = $repo->find($data[$field]);
                $method = "set" . ucfirst($field);
                $entity->$method($relation);
            }else {
                foreach ($data[$field] as $assocFields) {
                    $assosiation = new $metadata["targetEntity"];
                    $validation = $this->validate($assocFields, $metadata["targetEntity"]);
                    if ($validation){
                        $assosiation = $this->fillAndPersist($assocFields, $assosiation, true);
                        if (method_exists($entity, "set" . ucfirst($field))){
                            $method = "set" . ucfirst($field);
                        }else if (method_exists($entity, "add" . ucfirst($field))) {
                            $method = "add" . ucfirst($field);
                        }else if (method_exists($entity, "add" . ucfirst(mb_substr($field, 0, -1)))) {
                            $method = "add" . ucfirst(mb_substr($field, 0, -1));
                        }
                        $entity->$method($assosiation);
                    }
                }
            }
        }
        if ($bypass) return $entity;
        try {
            $this->em->persist($entity);
            $this->em->flush();
            return new JsonResponse(["status" => "ok", "data" => $entity]);
        } catch (\Throwable $th) {
            return new JsonResponse([
                "status" => "error",
                "error" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #stopPropagation
    #searchQuery
    #show
    #getTotal
    #offset
    private function getQuery(QueryBuilder &$qb, array $query){
        $metadata = $this->em->getClassMetadata($this->className);
        $mappings = $metadata->fieldMappings;
        $uniqueFields = array_filter(
            $mappings, 
            function($map){ return $map["unique"] || $map["fieldName"] == "id"; 
        });
        $uniqueField = array_shift($uniqueFields);
        $relationMappings = array_filter($metadata->associationMappings, function($map){
            return $map["type"] <= 2;
        });
        if (!isset($query["stopPropagation"])){
            foreach($relationMappings as $relationMap){
                $fieldName = $relationMap["fieldName"];
                $alias = "r_{$fieldName}";
                $relationMetadata = $this->em->getClassMetadata($relationMap["targetEntity"]);
                $relationMappings = [];
                foreach($relationMetadata->fieldMappings as $key => $fieldMetadata){
                    $relationMappings["{$alias}.{$key}"] = $fieldMetadata;
                }
                $qb->leftJoin("e.{$fieldName}", $alias);
                $mappings = array_merge($mappings, $relationMappings);
            }
        }
        
        $paramsToSearch = ($query["searchQuery"] ?? false) ? explode(" ", $query["searchQuery"]) : [];
        $ors = [];
        foreach($paramsToSearch as $key => $value){
            foreach ($mappings as $field => $fieldMetadata) {
                if (in_array($fieldMetadata["type"], ["string", "json", "smallint", "integer"])){
                    $parametro = str_replace(".", "_", "{$field}_".Misc::getletra($key));
                    $field = strpos($field, ".") ? $field : "e.{$field}";
                    $ors[] = $qb->expr()->like($field, ":{$parametro}");
                    $qb->setParameter($parametro, "%{$value}%");
                }else if (in_array($fieldMetadata["type"], ["datetime", "datetime_immutable"])){
                    //TODO: agregar estos tipos
                }
            }
            $qb->andWhere(implode(" OR ", $ors));
        }

        if (isset($query["show"]) && !isset($query["getTotal"])){
            $qb->setMaxResults($query["show"]);
        }
        if (isset($query["offset"]) && !isset($query["getTotal"])){
            $qb->setFirstResult($query["offset"]);
        }
        if (isset($query["getTotal"])){
            $qb->select($qb->expr()->countDistinct("e.{$uniqueField["fieldName"]}")." total");
        }
        if (isset($query["orderBy"])){
            //TODO: verificar que sea un orderBy correcto
            foreach ($query["orderBy"] as $key => $dir) {
                $dir = mb_strtolower($dir) == "desc" ? "DESC" : "ASC";
                $qb->addOrderBy("e.{$key}", $dir);
            }
        }
        if (isset($query["filter"])){
            foreach ($query["filter"] as $key => $val) {
                $type = $this->localOrRelation($key, $metadata);
                if (strpos($val, "_")){
                    $explode = explode("_", $val);
                    $val = $explode[1];
                    $rawOperator = $explode[0];
                }else {
                    $rawOperator = "";
                }
                switch ($type) {
                    case 'local':
                        $type = $metadata->fieldMappings[$key]["type"];
                        $check = $this->checkType($val, $type);
                        if (!$check){
                            // TODO: handle error;
                        }else {
                            $this->preprocess($val, $type);
                        }
                    break;
                    case 'relation':
                        $te = $metadata->associationMappings[$key]["targetEntity"];
                        $repo = $this->em->getRepository($te);
                        $matches = [];
                        preg_match("/\[\w+\]/", $rawOperator, $matches);
                        if (count($matches) > 1){
                            //TODO: Handle no deep search
                            //TODO: deep search
                        }else if (count($matches) == 1) {
                            $rawOperator = str_replace($matches[0], "", $rawOperator);
                            $propToSearch = str_replace(["[", "]"], "", $matches[0]);
                            $val = $repo->findOneBy([$propToSearch => $val]);
                        }else {
                            $val = $repo->find($val);
                        }
                    break;
                    default:
                        // TODO: handle error
                        break;
                }
                $this->whereOperator($qb, $key, $val, $rawOperator);
            }
        }
    }
    private function whereOperator(QueryBuilder &$qb, $key, $val, $raw){
        switch ($raw) {
            case "neq":
                $qb->andWhere("e.{$key} != :{$key}");
                $qb->setParameter($key, $val);
            break;
            case "lk":
                $qb->andWhere("e.{$key} like :{$key}");
                $qb->setParameter($key, "%{$val}%");
            break;
            default:
                $qb->andWhere("e.{$key} = :{$key}");
                $qb->setParameter($key, $val);

            break;
        }
    }
    private function preprocess(&$data, $type){
        switch($type){
            case "datetime": 
                if ($data == "now"){
                    $data = new DateTime();
                }else {
                    $data = new DateTime($data); 
                }
            case "boolean":
                if ($data == 1 || $data === "true") $data = true;
                if ($data == 0 || $data === "false") $data = false;
            break;
            case "json":
                try {
                    $newData = json_decode($data, true);
                    if (gettype($newData) == "array") {
                        $data = $newData;
                    }else {
                        $data = explode(",", $data);
                    }
                } catch (\Throwable $th) {
                    if (gettype($data) == "string") {
                        $data = explode(",", $data);
                    }
                }
            break;
        }
    } 
    private function checkType($data, string $type){
        switch ($type) {
            case 'string': return in_array(gettype($data), ["string", "integer", "double"]);
            case 'float': return in_array(gettype($data), [
                "integer",
                "double"
            ]);
            case 'datetime': 
                try {
                    $test = new DateTime($data);
                    return true;
                } catch (\Throwable $th){
                    return false;
                }
            break;
            case 'json': return in_array(gettype($data), [
                "array",
                "object",
                "string",
                "stdClass"
            ]);
            case 'boolean': return is_bool($data) || $data == "1" || $data = "0" || in_array($data, ["true", "false"]);
            case 'smallint': return is_int($data) && $data <= 65535;
            default: return gettype($data) == $type;
        }
    }
    private function localOrRelation($key, $metadata) : ?string{
        if (in_array($key, array_keys($metadata->fieldMappings))) return "local";
        if (in_array($key, array_keys($metadata->associationMappings))) return "relation";
        return null;
    }
    private function getRepo(String $className): EntityRepository{
        return $this->em->getRepository($className);
    }
    private function nameToCN($name){
        return "App\\Entity\\".ucfirst($name);
    }
    private function CNToName($className){
        $name = explode("\\", $className);
        return array_pop($name);
    }
    private function getToken(Request $req){
        $header = $req->headers->get("authorization");
        if (!$header || !preg_match("/Bearer \w+/i", $header)) return null;
        return trim( preg_replace("/Bearer /i", "", $header) );
    }
    private function validateToken(Request $req){
        $token = $this->getToken($req);
        $check = $token ? JWT::check($token, $this->api_secret) : false;
        $decode = JWT::decode($token);
        return [
            "check" => $check,
            "decoded" => $decode
        ];
    }
}