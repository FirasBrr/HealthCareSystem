<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }

    // Add this new route
    #[Route('/doctors', name: 'app_doctors')]
    public function doctors(): Response
    {
        // You'll need to create this template or redirect to another page
        return $this->render('doctors/index.html.twig');
    }
}