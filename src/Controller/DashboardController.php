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
                // Real doctor with profile - USE CORRECT METHOD NAMES
                $stats = [
                    'todayAppointments' => $appointmentRepo->countTodaysAppointments($doctor),
                    'totalAppointments' => $appointmentRepo->count(['doctor' => $doctor]),
                    'pendingAppointments' => $appointmentRepo->count(['doctor' => $doctor, 'status' => 'Pending']),
                    'confirmedAppointments' => $appointmentRepo->count(['doctor' => $doctor, 'status' => 'Confirmed']),
                ];

                // Use findByDoctorAndToday instead of findTodaysAppointments
                $todayAppointments = $appointmentRepo->findByDoctorAndToday($doctor);

                // For upcoming appointments, use findByDoctor and filter by date
                $allDoctorAppointments = $appointmentRepo->findByDoctor($doctor);
                $upcomingAppointments = array_filter($allDoctorAppointments, function($appointment) {
                    return $appointment->getStartDateTime() > new \DateTimeImmutable()
                        && in_array($appointment->getStatus(), ['Pending', 'Confirmed']);
                });

                // Sort upcoming appointments by date
                usort($upcomingAppointments, function($a, $b) {
                    return $a->getStartDateTime() <=> $b->getStartDateTime();
                });
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

            // Add patient-specific data
            if ($patient) {
                $patientAppointments = $appointmentRepo->findByPatient($patient);
                $upcomingPatientAppointments = array_filter($patientAppointments, function($appointment) {
                    return $appointment->getStartDateTime() > new \DateTimeImmutable()
                        && in_array($appointment->getStatus(), ['Pending', 'Confirmed']);
                });

                // Sort patient appointments
                usort($upcomingPatientAppointments, function($a, $b) {
                    return $a->getStartDateTime() <=> $b->getStartDateTime();
                });
            } else {
                $upcomingPatientAppointments = [];
            }

            return $this->render('dashboard/patient.html.twig', [
                'patient' => $patient,
                'user' => $user,
                'upcomingAppointments' => $upcomingPatientAppointments,
            ]);
        }

        // Admin dashboard
        if ($this->isGranted('ROLE_ADMIN')) {
            // Add admin stats
            $totalAppointments = $appointmentRepo->count([]);
            $pendingAppointments = $appointmentRepo->count(['status' => 'Pending']);
            $confirmedAppointments = $appointmentRepo->count(['status' => 'Confirmed']);

            return $this->render('dashboard/admin.html.twig', [
                'totalAppointments' => $totalAppointments,
                'pendingAppointments' => $pendingAppointments,
                'confirmedAppointments' => $confirmedAppointments,
            ]);
        }

        return $this->redirectToRoute('app_home');
    }
}