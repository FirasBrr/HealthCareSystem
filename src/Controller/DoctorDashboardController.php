<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Availability;
use App\Form\AvailabilityType;
use App\Repository\AppointmentRepository;
use App\Repository\AvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/doctor')]
#[IsGranted('ROLE_DOCTOR')]
class DoctorDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'doctor_dashboard')]
    public function dashboard(AppointmentRepository $apptRepo): Response
    {
        $doctor = $this->getUser()->getDoctor();
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $nextWeek = new \DateTimeImmutable('+1 week');

        // FIX: Use individual setParameter() calls instead of setParameters()
        $todayAppointments = $apptRepo->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :today AND a.startDateTime < :tomorrow')
            ->setParameter('doctor', $doctor)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()->getResult();

        // FIX: Use individual setParameter() calls
        $upcomingAppointments = $apptRepo->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :tomorrow AND a.startDateTime < :nextWeek')
            ->setParameter('doctor', $doctor)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('nextWeek', $nextWeek)
            ->getQuery()->getResult();

        // Add prescription count
        $prescriptionCount = count($doctor->getPrescriptions());

        // FIX: Also fix the stats queries
        $stats = [
            'today' => count($todayAppointments),
            'pending' => $apptRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.doctor = :doctor AND a.status = :status')
                ->setParameter('doctor', $doctor)
                ->setParameter('status', 'Pending')
                ->getQuery()->getSingleScalarResult(),
            'confirmed' => $apptRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.doctor = :doctor AND a.status = :status')
                ->setParameter('doctor', $doctor)
                ->setParameter('status', 'Confirmed')
                ->getQuery()->getSingleScalarResult(),
            'total' => $apptRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.doctor = :doctor')
                ->setParameter('doctor', $doctor)
                ->getQuery()->getSingleScalarResult(),
            'prescriptions' => $prescriptionCount,
        ];

        return $this->render('dashboard/doctor.html.twig', [
            'todayAppointments' => $todayAppointments,
            'upcomingAppointments' => $upcomingAppointments,
            'stats' => $stats,
            'doctor' => $doctor,
        ]);
    }

    #[Route('/availability', name: 'doctor_availability')]
    public function availability(AvailabilityRepository $repo): Response
    {
        $doctor = $this->getUser()->getDoctor();
        $availabilities = $repo->findBy(['doctor' => $doctor]);

        $events = [];
        $dayMap = [
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
            'Sunday' => 0,
        ];

        foreach ($availabilities as $avail) {
            $title = $avail->isAvailable() ? 'Available' : 'Blocked';
            $className = $avail->isAvailable() ? 'availability-slot' : 'blocked-slot';

            if ($avail->isRecurring()) {
                $dayNum = $dayMap[$avail->getDayOfWeek()] ?? null;
                if ($dayNum !== null) {
                    $events[] = [
                        'id' => $avail->getId(),
                        'title' => $title,
                        'daysOfWeek' => [$dayNum],
                        'startTime' => $avail->getStartTime()->format('H:i:s'),
                        'endTime' => $avail->getEndTime()->format('H:i:s'),
                        'className' => $className,
                        'extendedProps' => [
                            'type' => $avail->isAvailable() ? 'available' : 'blocked'
                        ]
                    ];
                }
            } else if ($avail->getDate()) {
                $dateStr = $avail->getDate()->format('Y-m-d');
                $events[] = [
                    'id' => $avail->getId(),
                    'title' => $title,
                    'start' => $dateStr . 'T' . $avail->getStartTime()->format('H:i:s'),
                    'end' => $dateStr . 'T' . $avail->getEndTime()->format('H:i:s'),
                    'className' => $className,
                    'extendedProps' => [
                        'type' => $avail->isAvailable() ? 'available' : 'blocked'
                    ]
                ];
            }
        }

        return $this->render('doctor/availability/index.html.twig', [
            'events' => json_encode($events),
        ]);
    }

    #[Route('/availability/quick-add', name: 'doctor_availability_quick_add', methods: ['POST'])]
    public function quickAddAvailability(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $doctor = $this->getUser()->getDoctor();

        // Parse dates assuming they're in UTC (FullCalendar sends ISO strings in local time)
        $startDateTime = new \DateTime($data['start']);
        $endDateTime = new \DateTime($data['end']);

        // Convert to server's timezone to ensure consistency
        $serverTimezone = new \DateTimeZone(date_default_timezone_get());
        $startDateTime->setTimezone($serverTimezone);
        $endDateTime->setTimezone($serverTimezone);

        // Create time objects using the formatted time from the timezone-adjusted datetime
        $startTime = \DateTime::createFromFormat('H:i:s', $startDateTime->format('H:i:s'));
        $endTime = \DateTime::createFromFormat('H:i:s', $endDateTime->format('H:i:s'));

        // Create date object using the formatted date from the timezone-adjusted datetime
        $dateOnly = \DateTime::createFromFormat('Y-m-d', $startDateTime->format('Y-m-d'));

        $availability = new Availability();
        $availability->setDoctor($doctor);
        $availability->setStartTime($startTime);
        $availability->setEndTime($endTime);
        $availability->setIsAvailable(true);
        $availability->setRecurring(false);
        $availability->setDate($dateOnly);

        $em->persist($availability);
        $em->flush();

        return $this->json([
            'success' => true,
            'id' => $availability->getId(),
            'title' => 'Available',
            'className' => 'availability-slot',
            'extendedProps' => [
                'type' => 'available'
            ]
        ]);
    }

    #[Route('/availability/new', name: 'doctor_availability_new', methods: ['GET', 'POST'])]
    public function newAvailability(Request $request, EntityManagerInterface $em): Response
    {
        $availability = new Availability();

        $form = $this->createForm(AvailabilityType::class, $availability);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $availability->setDoctor($this->getUser()->getDoctor());
            $em->persist($availability);
            $em->flush();
            $this->addFlash('success', 'Availability added!');
            return $this->redirectToRoute('doctor_availability');
        }

        return $this->render('doctor/availability/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/availability/{id}/edit', name: 'doctor_availability_edit', methods: ['GET', 'POST'])]
    public function editAvailability(Request $request, Availability $availability, EntityManagerInterface $em): Response
    {
        if ($availability->getDoctor() !== $this->getUser()->getDoctor()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AvailabilityType::class, $availability);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Availability updated!');
            return $this->redirectToRoute('doctor_availability');
        }

        return $this->render('doctor/availability/edit.html.twig', [
            'form' => $form->createView(),
            'availability' => $availability // Add this line

        ]);
    }

    #[Route('/availability/{id}/delete', name: 'doctor_availability_delete', methods: ['POST'])]
    public function deleteAvailability(Availability $availability, EntityManagerInterface $em): Response
    {
        if ($availability->getDoctor() !== $this->getUser()->getDoctor()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($availability);
        $em->flush();
        $this->addFlash('success', 'Availability deleted!');
        return $this->redirectToRoute('doctor_availability');
    }

    #[Route('/appointments', name: 'doctor_appointments')]
    public function appointments(Request $request, AppointmentRepository $appointmentRepo): Response
    {
        $doctor = $this->getUser()->getDoctor();

        // Get filter parameters
        $status = $request->query->get('status');
        $date = $request->query->get('date');
        $search = $request->query->get('search');

        // Build query
        $qb = $appointmentRepo->createQueryBuilder('a')
            ->leftJoin('a.patient', 'p')
            ->leftJoin('p.client', 'c')
            ->where('a.doctor = :doctor')
            ->setParameter('doctor', $doctor)
            ->orderBy('a.startDateTime', 'DESC');

        // Apply status filter
        if ($status && $status !== 'all') {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        // Apply date filter
        if ($date) {
            $dateObj = new \DateTime($date);
            $qb->andWhere('DATE(a.startDateTime) = :date')
                ->setParameter('date', $dateObj->format('Y-m-d'));
        }

        // Apply search filter - FIXED VERSION
        if ($search) {
            // Search in full name (first + last) OR phone
            $qb->andWhere('(CONCAT(c.firstName, \' \', c.lastName) LIKE :search OR p.phone LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $appointments = $qb->getQuery()->getResult();

        // Get upcoming appointments (next 7 days)
        $upcomingQb = $appointmentRepo->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime > :now')
            ->andWhere('a.status = :status')
            ->setParameter('doctor', $doctor)
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'Confirmed')
            ->orderBy('a.startDateTime', 'ASC')
            ->setMaxResults(10);

        $upcomingAppointments = $upcomingQb->getQuery()->getResult();

        // Get stats
        $stats = [
            'total' => count($appointments),
            'pending' => $appointmentRepo->count(['doctor' => $doctor, 'status' => 'Pending']),
            'confirmed' => $appointmentRepo->count(['doctor' => $doctor, 'status' => 'Confirmed']),
            'cancelled' => $appointmentRepo->count(['doctor' => $doctor, 'status' => 'Cancelled']),
        ];

        // Try to get completed count if the status exists
        try {
            $stats['completed'] = $appointmentRepo->count(['doctor' => $doctor, 'status' => 'Completed']);
        } catch (\Exception $e) {
            $stats['completed'] = 0;
        }

        return $this->render('doctor/appointments.html.twig', [
            'appointments' => $appointments,
            'upcomingAppointments' => $upcomingAppointments,
            'stats' => $stats,
            'currentFilters' => [
                'status' => $status,
                'date' => $date,
                'search' => $search,
            ],
        ]);
    }

    #[Route('/appointment/{id}/confirm', name: 'doctor_appointment_confirm', methods: ['POST'])]
    public function confirmAppointment(Appointment $appointment, EntityManagerInterface $em): JsonResponse
    {
        $doctor = $this->getUser()->getDoctor();

        if (!$doctor || $appointment->getDoctor() !== $doctor || $appointment->getStatus() !== 'Pending') {
            return $this->json(['success' => false, 'message' => 'Access denied or invalid appointment status']);
        }

        $appointment->setStatus('Confirmed');
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Appointment confirmed!']);
    }

    #[Route('/appointment/{id}/cancel', name: 'doctor_appointment_cancel', methods: ['POST'])]
    public function cancelAppointment(Appointment $appointment, EntityManagerInterface $em): JsonResponse
    {
        $doctor = $this->getUser()->getDoctor();

        if (!$doctor || $appointment->getDoctor() !== $doctor) {
            return $this->json(['success' => false, 'message' => 'Access denied']);
        }

        $appointment->setStatus('Cancelled');
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Appointment cancelled!']);
    }

    #[Route('/appointment/{id}/complete', name: 'doctor_appointment_complete', methods: ['POST'])]
    public function completeAppointment(Appointment $appointment, EntityManagerInterface $em): JsonResponse
    {
        $doctor = $this->getUser()->getDoctor();

        if (!$doctor || $appointment->getDoctor() !== $doctor || $appointment->getStatus() !== 'Confirmed') {
            return $this->json(['success' => false, 'message' => 'Access denied or invalid appointment status']);
        }

        // Check if appointment has ended (optional - you can remove this if you want doctors to complete anytime)
        $now = new \DateTime();
        if ($appointment->getEndDateTime() > $now) {
            return $this->json(['success' => false, 'message' => 'Cannot complete appointment before its end time']);
        }

        // Set status to Completed (if you have this status) or keep as Confirmed
        // If you don't have Completed status, you can either:
        // 1. Add it to your Appointment entity
        // 2. Or keep as Confirmed but add a 'completed' boolean field
        // 3. Or just don't use this feature

        // For now, let's assume you have a 'Completed' status
        $appointment->setStatus('Completed');
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Appointment marked as completed!']);
    }

    #[Route('/availability/copy-week', name: 'doctor_availability_copy_week', methods: ['POST'])]
    public function copyWeekAvailability(Request $request, AvailabilityRepository $repo, EntityManagerInterface $em): Response
    {
        $doctor = $this->getUser()->getDoctor();

        // Get current week's recurring availabilities
        $currentAvailabilities = $repo->findBy([
            'doctor' => $doctor,
            'recurring' => true
        ]);

        // Create copies for next week
        foreach ($currentAvailabilities as $avail) {
            $newAvailability = clone $avail;
            $newAvailability->setRecurring(false);
            $newAvailability->setDate((new \DateTime())->modify('+1 week'));
            $em->persist($newAvailability);
        }

        $em->flush();
        $this->addFlash('success', 'Week schedule copied successfully!');
        return $this->redirectToRoute('doctor_availability');
    }

    // Optional: If you want to keep the old appointments method for backward compatibility
    #[Route('/appointments-old', name: 'doctor_appointments_old')]
    public function appointmentsOld(AppointmentRepository $apptRepo): Response
    {
        $doctor = $this->getUser()->getDoctor();
        $appointments = $apptRepo->findBy(
            ['doctor' => $doctor],
            ['startDateTime' => 'ASC']
        );

        return $this->render('doctor/appointments_old.html.twig', [
            'appointments' => $appointments,
        ]);
    }
    // In DoctorDashboardController.php - update the patients() method

    #[Route('/patients', name: 'doctor_patients')]
    public function patients(Request $request, AppointmentRepository $appointmentRepo): Response
    {
        $doctor = $this->getUser()->getDoctor();

        // Get filter parameters
        $search = $request->query->get('search');
        $sort = $request->query->get('sort', 'name');
        $order = $request->query->get('order', 'asc');

        // Get all appointments for this doctor to extract unique patients
        $qb = $appointmentRepo->createQueryBuilder('a')
            ->leftJoin('a.patient', 'p')
            ->leftJoin('p.client', 'c')
            ->leftJoin('a.prescription', 'pr') // ADD THIS JOIN
            ->where('a.doctor = :doctor')
            ->setParameter('doctor', $doctor)
            ->groupBy('p.id');

        // Apply search filter
        if ($search) {
            $qb->andWhere('(c.firstName LIKE :search OR c.lastName LIKE :search OR p.phone LIKE :search OR c.email LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        // Apply sorting
        switch ($sort) {
            case 'name':
                $qb->orderBy('c.firstName', $order);
                break;
            case 'last_appointment':
                $qb->orderBy('MAX(a.startDateTime)', $order === 'asc' ? 'DESC' : 'ASC');
                break;
            case 'total_appointments':
                $qb->orderBy('COUNT(a.id)', $order);
                break;
            default:
                $qb->orderBy('c.firstName', 'asc');
        }

        $appointments = $qb->getQuery()->getResult();

        // Extract unique patients with their statistics
        $patients = [];
        foreach ($appointments as $appointment) {
            $patient = $appointment->getPatient();
            $client = $patient->getClient();
            $patientId = $patient->getId();

            if (!isset($patients[$patientId])) {
                // Get patient statistics - FIXED QUERY
                $patientStats = $appointmentRepo->createQueryBuilder('a2')
                    ->select('COUNT(a2.id) as total_appointments')
                    ->addSelect('MAX(a2.startDateTime) as last_appointment')
                    ->addSelect('SUM(CASE WHEN a2.status = :confirmed THEN 1 ELSE 0 END) as confirmed_count')
                    ->leftJoin('a2.prescription', 'pr2') // ADD JOIN HERE TOO
                    ->addSelect('SUM(CASE WHEN pr2.id IS NOT NULL THEN 1 ELSE 0 END) as prescription_count')
                    ->where('a2.doctor = :doctor AND a2.patient = :patient')
                    ->setParameter('doctor', $doctor)
                    ->setParameter('patient', $patient)
                    ->setParameter('confirmed', 'Confirmed')
                    ->getQuery()
                    ->getSingleResult();

                $patients[$patientId] = [
                    'patient' => $patient,
                    'client' => $client,
                    'stats' => [
                        'total_appointments' => $patientStats['total_appointments'],
                        'last_appointment' => $patientStats['last_appointment'],
                        'confirmed_count' => $patientStats['confirmed_count'],
                        'prescription_count' => $patientStats['prescription_count'],
                    ]
                ];
            }
        }

        // Convert to array for template
        $patients = array_values($patients);

        // Get overall statistics - FIXED QUERY
        $totalAppointmentsQb = $appointmentRepo->createQueryBuilder('a3')
            ->select('COUNT(a3.id)')
            ->where('a3.doctor = :doctor')
            ->setParameter('doctor', $doctor);

        $prescriptionCountQb = $appointmentRepo->createQueryBuilder('a4')
            ->select('COUNT(pr4.id)')
            ->leftJoin('a4.prescription', 'pr4')
            ->where('a4.doctor = :doctor')
            ->setParameter('doctor', $doctor);

        $stats = [
            'total' => count($patients),
            'total_appointments' => $totalAppointmentsQb->getQuery()->getSingleScalarResult(),
            'active_patients' => count(array_filter($patients, function($p) {
                $lastAppointment = $p['stats']['last_appointment'];
                if (!$lastAppointment) return false;
                $thirtyDaysAgo = new \DateTime('-30 days');
                return $lastAppointment > $thirtyDaysAgo;
            })),
            'prescription_count' => $prescriptionCountQb->getQuery()->getSingleScalarResult(),
        ];

        return $this->render('doctor/patients/index.html.twig', [
            'patients' => $patients,
            'stats' => $stats,
            'currentFilters' => [
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
            ],
        ]);
    }
}