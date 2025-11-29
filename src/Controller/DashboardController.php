<?php
// src/Controller/DashboardController.php

namespace App\Controller;

use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        AppointmentRepository $appointmentRepo,
        UserRepository $userRepo,
        DoctorRepository $doctorRepo,
        PatientRepository $patientRepo
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // ========== ADMIN DASHBOARD ==========
        // Dans ta méthode du dashboard admin
        if ($this->isGranted('ROLE_ADMIN')) {
            $today = new \DateTimeImmutable('today');

            $stats = [
                'totalUsers'           => $userRepo->count([]),
                'totalDoctors'         => $doctorRepo->count([]),
                'totalPatients'        => $patientRepo->count([]),
                'totalAppointments'    => $appointmentRepo->count([]),
                'todayAppointments'    => $appointmentRepo->countByDate($today),
                'monthAppointments'    => $appointmentRepo->countByMonth(new \DateTimeImmutable()),
                'pendingAppointments'  => $appointmentRepo->count(['status' => 'Pending']),
                'confirmedAppointments'=> $appointmentRepo->count(['status' => 'Confirmed']),
            ];

            return $this->render('dashboard/admin.html.twig', [
                'stats'              => $stats,
                'recentAppointments' => $appointmentRepo->findRecent(10),
                'topDoctors'         => $doctorRepo->findTopByAppointments(5),
                'recentPatients'     => $patientRepo->findLatest(8),
            ]);
        }

        // ========== DOCTOR DASHBOARD (your existing code improved) ==========
        if ($this->isGranted('ROLE_DOCTOR')) {
            $doctor = $user->getDoctor();

            $todayAppointments = $doctor
                ? $appointmentRepo->findTodaysAppointments($doctor)
                : [];

            $upcomingAppointments = $doctor
                ? $appointmentRepo->findUpcomingAppointments($doctor)
                : [];

            return $this->render('dashboard/doctor.html.twig', [
                'doctor'              => $doctor ?? $this->createDemoDoctor($user),
                'todayAppointments'   => $todayAppointments,
                'upcomingAppointments'=> $upcomingAppointments,
                'stats'               => $this->getDoctorStats($appointmentRepo, $doctor),
            ]);
        }

        // ========== PATIENT DASHBOARD ==========
        if ($this->isGranted('ROLE_PATIENT')) {
            return $this->render('dashboard/patient.html.twig', [
                'patient' => $user->getPatient(),
                'appointments' => $appointmentRepo->findBy(['patient' => $user->getPatient()], ['startDateTime' => 'DESC'], 10),            ]);
        }

        return $this->redirectToRoute('app_home');
    }

    private function createDemoDoctor($user): object
    {
        $demo = new \stdClass();
        $demo->client = $user;
        $demo->specialty = 'General Medicine';
        $demo->phone = 'Not set';
        $demo->bio = 'Complete your profile to appear in searches';
        $demo->rating = null;
        return $demo;
    }

    private function getDoctorStats(AppointmentRepository $repo, $doctor): array
    {
        if (!$doctor) {
            return array_fill_keys(['today', 'total', 'pending', 'confirmed'], 0);
        }

        return [
            'today'     => $repo->countTodaysAppointments($doctor),
            'total'     => $repo->count(['doctor' => $doctor]),
            'pending'   => $repo->count(['doctor' => $doctor, 'status' => 'Pending']),
            'confirmed' => $repo->count(['doctor' => $doctor, 'status' => 'Confirmed']),
        ];
    }
}