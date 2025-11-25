<?php
// src/Controller/AppointmentBookingController.php

namespace App\Controller;

use App\Repository\AppointmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/appointments')]
class AppointmentBookingController extends AbstractController  // ← CHANGE THIS LINE ONLY
{
    #[Route('/', name: 'admin_appointments')]
    public function index(AppointmentRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/appointments/index.html.twig', [
            'appointments' => $repo->findBy([], ['startDateTime' => 'DESC'])
        ]);
    }
}