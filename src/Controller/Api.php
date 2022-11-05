<?php
namespace AXS\ApiBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class Api extends AbstractController {

    public function __construct(private string $user_entity, private string $user_uid, private EntityManagerInterface $em, private Security $security){}
    #[Route("/API", name:"axs_api_index_docs")]
    public function index(Request $req){
        return $this->json([
            "status" => "ok",
            "welcome" => "Bienvenido al Api Bundle de AXS"
        ]);
    }
    #[Route("/API/login", name: "axs_api_login", methods: ["POST"])]
    public function login(Request $req){
        $parameters = json_decode($req->getContent(), false);
        return $this->json($parameters);
        /** @var \Doctrine\ORM\EntityRepository $userRepo */
        $userRepo = $this->em->getRepository($this->user_entity);
        $reqUser = $userRepo->findBy([])
    }
}