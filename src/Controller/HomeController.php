<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }

    #[Route('/home', name: 'app_home_alt')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/doctors', name: 'app_doctors')]
    public function doctors(): Response
    {
        return $this->render('doctors/index.html.twig');
    }
}