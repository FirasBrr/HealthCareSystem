<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppointmentBookingController extends AbstractController
{
    #[Route('/appointment/booking', name: 'app_appointment_booking')]
    public function index(): Response
    {
        return $this->render('appointment_booking/doctor.html.twig', [
            'controller_name' => 'AppointmentBookingController',
        ]);
    }
}
