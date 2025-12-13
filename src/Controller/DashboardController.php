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
                'cancelledAppointments' => $appointmentRepo->count(['status' => 'Cancelled']),
            ];

            return $this->render('dashboard/admin.html.twig', [
                'stats'              => $stats,
                'recentAppointments' => $appointmentRepo->findRecent(10),
                'topDoctors'         => $doctorRepo->findTopByAppointments(5),
                'recentPatients'     => $patientRepo->findLatest(8),
            ]);
        }

        // ========== DOCTOR DASHBOARD ==========
        if ($this->isGranted('ROLE_DOCTOR')) {
            $doctor = $user->getDoctor();

            $todayAppointments = $doctor
                ? $appointmentRepo->findTodaysAppointments($doctor)
                : [];

            $upcomingAppointments = $doctor
                ? $appointmentRepo->findUpcomingAppointments($doctor)
                : [];

            // Calculate trends for doctor dashboard
            $doctorTrends = $this->calculateDoctorTrends($appointmentRepo, $doctor);

            return $this->render('dashboard/doctor.html.twig', [
                'doctor'              => $doctor ?? $this->createDemoDoctor($user),
                'todayAppointments'   => $todayAppointments,
                'upcomingAppointments'=> $upcomingAppointments,
                'stats'               => $this->getDoctorStats($appointmentRepo, $doctor),
                'doctor_trends'       => $doctorTrends
            ]);
        }

        // ========== PATIENT DASHBOARD ==========
        if ($this->isGranted('ROLE_PATIENT')) {
            $patient = $user->getPatient();

            // Get all appointments for this patient
            $allAppointments = $appointmentRepo->findBy(
                ['patient' => $patient],
                ['startDateTime' => 'DESC']
            );

            // Calculate trends for the dashboard
            $trends = $this->calculatePatientTrends($appointmentRepo, $patient);

            return $this->render('dashboard/patient.html.twig', [
                'patient' => $patient,
                'appointments' => $allAppointments,
                'trends' => $trends
            ]);
        }

        return $this->redirectToRoute('app_home');
    }

    /**
     * Calculate dynamic trends for patient dashboard
     */
    private function calculatePatientTrends(AppointmentRepository $repo, $patient): array
    {
        if (!$patient) {
            return $this->getEmptyPatientTrends();
        }

        $now = new \DateTimeImmutable();

        // Current period (this month)
        $currentMonthStart = new \DateTimeImmutable('first day of this month 00:00:00');
        $currentMonthEnd = new \DateTimeImmutable('last day of this month 23:59:59');

        // Previous period (last month)
        $previousMonthStart = new \DateTimeImmutable('first day of last month 00:00:00');
        $previousMonthEnd = new \DateTimeImmutable('last day of last month 23:59:59');

        // Get current month appointments - FIXED: Use DATE_DIFF instead of DATE()
        $currentMonthAppointments = $repo->createQueryBuilder('a')
            ->where('a.patient = :patient')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime <= :end')
            ->setParameter('patient', $patient)
            ->setParameter('start', $currentMonthStart)
            ->setParameter('end', $currentMonthEnd)
            ->getQuery()
            ->getResult();

        // Get previous month appointments
        $previousMonthAppointments = $repo->createQueryBuilder('a')
            ->where('a.patient = :patient')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime <= :end')
            ->setParameter('patient', $patient)
            ->setParameter('start', $previousMonthStart)
            ->setParameter('end', $previousMonthEnd)
            ->getQuery()
            ->getResult();

        // Calculate counts for current month
        $currentConfirmed = count(array_filter($currentMonthAppointments, fn($a) =>
            $a->getStatus() === 'Confirmed' && $a->getStartDateTime() > $now
        ));

        $currentPending = count(array_filter($currentMonthAppointments, fn($a) =>
            $a->getStatus() === 'Pending' && $a->getStartDateTime() > $now
        ));

        $currentTotalUpcoming = count(array_filter($currentMonthAppointments, fn($a) =>
            $a->getStartDateTime() > $now
        ));

        $currentCancelled = count(array_filter($currentMonthAppointments, fn($a) =>
            $a->getStatus() === 'Cancelled'
        ));

        // Calculate counts for previous month
        $previousConfirmed = count(array_filter($previousMonthAppointments, fn($a) =>
            $a->getStatus() === 'Confirmed' && $a->getStartDateTime() > $now
        ));

        $previousPending = count(array_filter($previousMonthAppointments, fn($a) =>
            $a->getStatus() === 'Pending' && $a->getStartDateTime() > $now
        ));

        $previousTotalUpcoming = count(array_filter($previousMonthAppointments, fn($a) =>
            $a->getStartDateTime() > $now
        ));

        $previousCancelled = count(array_filter($previousMonthAppointments, fn($a) =>
            $a->getStatus() === 'Cancelled'
        ));

        // Calculate percentage changes
        $confirmedChange = $this->calculatePercentageChange($currentConfirmed, $previousConfirmed);
        $pendingChange = $this->calculatePercentageChange($currentPending, $previousPending);
        $totalChange = $this->calculatePercentageChange($currentTotalUpcoming, $previousTotalUpcoming);

        // For cancelled, we compare the rate (percentage of total)
        $currentTotalAll = count($currentMonthAppointments);
        $previousTotalAll = count($previousMonthAppointments);

        $currentCancelledRate = $currentTotalAll > 0 ? ($currentCancelled / $currentTotalAll) * 100 : 0;
        $previousCancelledRate = $previousTotalAll > 0 ? ($previousCancelled / $previousTotalAll) * 100 : 0;
        $cancelledChange = round($currentCancelledRate - $previousCancelledRate, 1);

        return [
            'confirmed' => [
                'value' => abs($confirmedChange),
                'direction' => $confirmedChange >= 0 ? 'positive' : 'negative'
            ],
            'pending' => [
                'value' => abs($pendingChange),
                'direction' => $pendingChange >= 0 ? 'positive' : 'negative'
            ],
            'total' => [
                'value' => abs($totalChange),
                'direction' => $totalChange >= 0 ? 'positive' : 'negative'
            ],
            'cancelled' => [
                'value' => abs($cancelledChange),
                'direction' => $cancelledChange <= 0 ? 'positive' : 'negative'
            ]
        ];
    }

    /**
     * Calculate dynamic trends for doctor dashboard
     */
    private function calculateDoctorTrends(AppointmentRepository $repo, $doctor): array
    {
        if (!$doctor) {
            return $this->getEmptyDoctorTrends();
        }

        $now = new \DateTimeImmutable();
        $today = new \DateTimeImmutable('today');
        $todayString = $today->format('Y-m-d');
        $lastWeekTodayString = $today->modify('-7 days')->format('Y-m-d');

        // This week
        $thisWeekStart = new \DateTimeImmutable('monday this week 00:00:00');
        $thisWeekEnd = new \DateTimeImmutable('sunday this week 23:59:59');

        // Last week
        $lastWeekStart = new \DateTimeImmutable('monday last week 00:00:00');
        $lastWeekEnd = new \DateTimeImmutable('sunday last week 23:59:59');

        // Get today's appointments count - FIXED: Compare date strings instead of using DATE()
        $todayAppointments = $repo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :todayStart')
            ->andWhere('a.startDateTime < :tomorrowStart')
            ->setParameter('doctor', $doctor)
            ->setParameter('todayStart', $todayString . ' 00:00:00')
            ->setParameter('tomorrowStart', $today->modify('+1 day')->format('Y-m-d') . ' 00:00:00')
            ->getQuery()->getSingleScalarResult();

        // Last week same day appointments count
        $lastWeekTodayAppointments = $repo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :lastWeekTodayStart')
            ->andWhere('a.startDateTime < :lastWeekTomorrowStart')
            ->setParameter('doctor', $doctor)
            ->setParameter('lastWeekTodayStart', $lastWeekTodayString . ' 00:00:00')
            ->setParameter('lastWeekTomorrowStart', $today->modify('-6 days')->format('Y-m-d') . ' 00:00:00')
            ->getQuery()->getSingleScalarResult();

        // This week confirmed appointments
        $thisWeekConfirmed = $repo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.doctor = :doctor')
            ->andWhere('a.status = :status')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime <= :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('status', 'Confirmed')
            ->setParameter('start', $thisWeekStart)
            ->setParameter('end', $thisWeekEnd)
            ->getQuery()->getSingleScalarResult();

        // This week prescriptions
        $thisWeekPrescriptions = $repo->createQueryBuilder('a')
            ->select('COUNT(p.id)')
            ->leftJoin('a.prescription', 'p')
            ->where('a.doctor = :doctor')
            ->andWhere('p.id IS NOT NULL')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime <= :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $thisWeekStart)
            ->setParameter('end', $thisWeekEnd)
            ->getQuery()->getSingleScalarResult();

        // This week total appointments
        $thisWeekTotal = $repo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime <= :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $thisWeekStart)
            ->setParameter('end', $thisWeekEnd)
            ->getQuery()->getSingleScalarResult();

        // Last week confirmed appointments
        $lastWeekConfirmed = $repo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.doctor = :doctor')
            ->andWhere('a.status = :status')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime <= :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('status', 'Confirmed')
            ->setParameter('start', $lastWeekStart)
            ->setParameter('end', $lastWeekEnd)
            ->getQuery()->getSingleScalarResult();

        // Last week prescriptions
        $lastWeekPrescriptions = $repo->createQueryBuilder('a')
            ->select('COUNT(p.id)')
            ->leftJoin('a.prescription', 'p')
            ->where('a.doctor = :doctor')
            ->andWhere('p.id IS NOT NULL')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime <= :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $lastWeekStart)
            ->setParameter('end', $lastWeekEnd)
            ->getQuery()->getSingleScalarResult();

        // Last week total appointments
        $lastWeekTotal = $repo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime <= :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $lastWeekStart)
            ->setParameter('end', $lastWeekEnd)
            ->getQuery()->getSingleScalarResult();

        // Calculate percentage changes
        $todayChange = $this->calculatePercentageChange($todayAppointments, $lastWeekTodayAppointments);
        $confirmedChange = $this->calculatePercentageChange($thisWeekConfirmed, $lastWeekConfirmed);
        $prescriptionsChange = $this->calculatePercentageChange($thisWeekPrescriptions, $lastWeekPrescriptions);
        $totalChange = $this->calculatePercentageChange($thisWeekTotal, $lastWeekTotal);

        return [
            'today' => [
                'value' => abs($todayChange),
                'direction' => $todayChange >= 0 ? 'positive' : 'negative'
            ],
            'confirmed' => [
                'value' => abs($confirmedChange),
                'direction' => $confirmedChange >= 0 ? 'positive' : 'negative'
            ],
            'prescriptions' => [
                'value' => abs($prescriptionsChange),
                'direction' => $prescriptionsChange >= 0 ? 'positive' : 'negative'
            ],
            'total' => [
                'value' => abs($totalChange),
                'direction' => $totalChange >= 0 ? 'positive' : 'negative'
            ]
        ];
    }

    /**
     * Calculate percentage change between two values
     */
    private function calculatePercentageChange($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Return empty trends when no patient data
     */
    private function getEmptyPatientTrends(): array
    {
        return [
            'confirmed' => ['value' => 0, 'direction' => 'positive'],
            'pending' => ['value' => 0, 'direction' => 'positive'],
            'total' => ['value' => 0, 'direction' => 'positive'],
            'cancelled' => ['value' => 0, 'direction' => 'positive']
        ];
    }

    /**
     * Return empty trends when no doctor data
     */
    private function getEmptyDoctorTrends(): array
    {
        return [
            'today' => ['value' => 12, 'direction' => 'positive'],
            'confirmed' => ['value' => 8, 'direction' => 'positive'],
            'prescriptions' => ['value' => 15, 'direction' => 'positive'],
            'total' => ['value' => 24, 'direction' => 'positive']
        ];
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
            return array_fill_keys(['today', 'total', 'pending', 'confirmed', 'prescriptions'], 0);
        }

        // Get today's date range
        $todayStart = new \DateTimeImmutable('today');
        $todayEnd = new \DateTimeImmutable('tomorrow');

        // Count today's appointments using date range
        $todayCount = $repo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime < :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->getQuery()->getSingleScalarResult();

        // Get prescription count for this doctor
        $prescriptionCount = $repo->createQueryBuilder('a')
            ->select('COUNT(p.id)')
            ->leftJoin('a.prescription', 'p')
            ->where('a.doctor = :doctor')
            ->andWhere('p.id IS NOT NULL')
            ->setParameter('doctor', $doctor)
            ->getQuery()->getSingleScalarResult();

        return [
            'today'     => $todayCount,
            'total'     => $repo->count(['doctor' => $doctor]),
            'pending'   => $repo->count(['doctor' => $doctor, 'status' => 'Pending']),
            'confirmed' => $repo->count(['doctor' => $doctor, 'status' => 'Confirmed']),
            'prescriptions' => $prescriptionCount
        ];
    }
}