<?php

namespace App\Controller;

use App\Repository\AppointmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(AppointmentRepository $appointmentRepo): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Doctor dashboard
        if ($this->isGranted('ROLE_DOCTOR')) {
            $doctor = $user->getDoctor();

            // Create demo data for testing
            if (!$doctor) {
                // Create a temporary doctor object with demo data
                $doctor = new \stdClass();
                $doctor->client = new \stdClass();
                $doctor->client->firstName = $user->getFirstName();
                $doctor->client->lastName = $user->getLastName();
                $doctor->specialty = 'General Practitioner';
                $doctor->rating = 4.8;
                $doctor->phone = 'Not set';
                $doctor->bio = 'Complete your profile to add a bio';

                $stats = [
                    'todayAppointments' => 0,
                    'totalAppointments' => 0,
                    'pendingAppointments' => 0,
                    'confirmedAppointments' => 0,
                ];

                $todayAppointments = [];
                $upcomingAppointments = [];
            } else {
                // Real doctor with profile
                $stats = [
                    'todayAppointments' => $appointmentRepo->countTodaysAppointments($doctor),
                    'totalAppointments' => $appointmentRepo->count(['doctor' => $doctor]),
                    'pendingAppointments' => $appointmentRepo->count(['doctor' => $doctor, 'status' => 'Pending']),
                    'confirmedAppointments' => $appointmentRepo->count(['doctor' => $doctor, 'status' => 'Confirmed']),
                ];

                $todayAppointments = $appointmentRepo->findTodaysAppointments($doctor);
                $upcomingAppointments = $appointmentRepo->findUpcomingAppointments($doctor);
            }

            return $this->render('dashboard/doctor.html.twig', [
                'doctor' => $doctor,
                'stats' => $stats,
                'todayAppointments' => $todayAppointments,
                'upcomingAppointments' => $upcomingAppointments,
                'today' => new \DateTime(),
            ]);
        }

        // Patient dashboard
        if ($this->isGranted('ROLE_PATIENT')) {
            $patient = $user->getPatient();

            return $this->render('dashboard/patient.html.twig', [
                'patient' => $patient,
                'user' => $user,
            ]);
        }

        // Admin dashboard
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->render('dashboard/admin.html.twig');
        }

        return $this->redirectToRoute('app_home');
    }
}