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

        $todayAppointments = $apptRepo->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :today AND a.startDateTime < :tomorrow')
            ->setParameters(['doctor' => $doctor, 'today' => $today, 'tomorrow' => $tomorrow])
            ->getQuery()->getResult();

        $upcomingAppointments = $apptRepo->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :tomorrow AND a.startDateTime < :nextWeek')
            ->setParameters(['doctor' => $doctor, 'tomorrow' => $tomorrow, 'nextWeek' => $nextWeek])
            ->getQuery()->getResult();

        $stats = [
            'today' => count($todayAppointments),
            'pending' => $apptRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.doctor = :doctor AND a.status = :status')
                ->setParameters(['doctor' => $doctor, 'status' => 'Pending'])
                ->getQuery()->getSingleScalarResult(),
            'confirmed' => $apptRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.doctor = :doctor AND a.status = :status')
                ->setParameters(['doctor' => $doctor, 'status' => 'Confirmed'])
                ->getQuery()->getSingleScalarResult(),
            'total' => $apptRepo->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.doctor = :doctor')
                ->setParameter('doctor', $doctor)
                ->getQuery()->getSingleScalarResult(),
        ];

        return $this->render('doctor/dashboard.html.twig', [
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
    public function appointments(AppointmentRepository $apptRepo): Response
    {
        $doctor = $this->getUser()->getDoctor();
        $appointments = $apptRepo->findBy(
            ['doctor' => $doctor],
            ['startDateTime' => 'ASC']
        );

        return $this->render('doctor/appointments.html.twig', [
            'appointments' => $appointments,
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
}