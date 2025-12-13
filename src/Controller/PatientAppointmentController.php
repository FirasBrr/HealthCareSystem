<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\AvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient')]
#[IsGranted('ROLE_PATIENT')]
class PatientAppointmentController extends AbstractController
{
    #[Route('/appointments', name: 'patient_appointments')]
    public function appointments(AppointmentRepository $appointmentRepo): Response
    {
        $patient = $this->getUser()->getPatient();

        // Get all appointments for this patient
        $appointments = $appointmentRepo->findBy([
            'patient' => $patient
        ], ['startDateTime' => 'DESC']);

        return $this->render('patient/appointments.html.twig', [
            'appointments' => $appointments,
        ]);
    }
    #[Route('/prescriptions', name: 'patient_prescriptions')]
    public function prescriptions(AppointmentRepository $appointmentRepo): Response
    {
        $patient = $this->getUser()->getPatient();

        // Get all appointments with prescriptions for this patient
        $appointments = $appointmentRepo->createQueryBuilder('a')
            ->leftJoin('a.prescription', 'p')
            ->where('a.patient = :patient')
            ->andWhere('p.id IS NOT NULL')
            ->setParameter('patient', $patient)
            ->orderBy('a.startDateTime', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('patient/prescriptions.html.twig', [
            'appointments' => $appointments,
        ]);
    }

    #[Route('/doctors', name: 'patient_doctors')]
    public function findDoctors(DoctorRepository $doctorRepo): Response
    {
        $doctors = $doctorRepo->findAll();

        return $this->render('patient/doctors.html.twig', [
            'doctors' => $doctors,
        ]);
    }

    #[Route('/doctor/{id}/availability', name: 'patient_doctor_availability')]
    public function viewDoctorAvailability(int $id, DoctorRepository $doctorRepo, AvailabilityRepository $availabilityRepo): Response
    {
        $doctor = $doctorRepo->find($id);

        if (!$doctor) {
            throw $this->createNotFoundException('Doctor not found');
        }

        // Get today's date
        $today = new \DateTimeImmutable();

        // Get all availabilities for this doctor that are marked as available
        $availabilities = $availabilityRepo->findBy([
            'doctor' => $doctor,
            'isAvailable' => true
        ], ['date' => 'ASC', 'startTime' => 'ASC']);

        // Filter availabilities to show only future slots
        $futureAvailabilities = [];
        foreach ($availabilities as $availability) {
            if ($availability->getDate()) {
                // For specific date availabilities
                $availabilityDate = $availability->getDate();
                $startTime = $availability->getStartTime();

                // Create full datetime for this availability
                $availabilityDateTime = new \DateTimeImmutable(
                    $availabilityDate->format('Y-m-d') . ' ' . $startTime->format('H:i:s')
                );

                // Check if this availability is in the future (including today's future times)
                if ($availabilityDateTime > $today) {
                    $futureAvailabilities[] = $availability;
                }
            } else {
                // For recurring availabilities (day of week)
                // They are always in the future since they're recurring
                $futureAvailabilities[] = $availability;
            }
        }

        return $this->render('patient/doctor_availability.html.twig', [
            'doctor' => $doctor,
            'availabilities' => $futureAvailabilities,
        ]);
    }

    #[Route('/appointment/book', name: 'patient_appointment_book', methods: ['POST'])]
    public function bookAppointment(Request $request, EntityManagerInterface $em, DoctorRepository $doctorRepo, AvailabilityRepository $availabilityRepo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $patient = $this->getUser()->getPatient();

        $doctor = $doctorRepo->find($data['doctorId']);
        $availability = $availabilityRepo->find($data['availabilityId']);

        if (!$doctor || !$availability || $availability->getDoctor() !== $doctor) {
            return $this->json(['success' => false, 'message' => 'Invalid availability slot']);
        }

        if (!$availability->isAvailable()) {
            return $this->json(['success' => false, 'message' => 'This time slot is no longer available']);
        }

        // Create appointment
        $appointment = new Appointment();
        $appointment->setPatient($patient);
        $appointment->setDoctor($doctor);

        // Set appointment datetime based on availability
        if ($availability->getDate()) {
            $startDateTime = new \DateTimeImmutable($availability->getDate()->format('Y-m-d') . ' ' . $availability->getStartTime()->format('H:i:s'));
            $endDateTime = new \DateTimeImmutable($availability->getDate()->format('Y-m-d') . ' ' . $availability->getEndTime()->format('H:i:s'));
        } else {
            // For recurring availabilities, use next occurrence
            $startDateTime = $this->getNextRecurringDate($availability);
            $endDateTime = $startDateTime->modify('+1 hour');
        }

        // Additional check: ensure the appointment time is in the future
        if ($startDateTime <= new \DateTimeImmutable()) {
            return $this->json(['success' => false, 'message' => 'Cannot book appointments in the past']);
        }

        $appointment->setStartDateTime($startDateTime);
        $appointment->setEndDateTime($endDateTime);
        $appointment->setStatus('Pending');
        $appointment->setReference(uniqid('APT_'));

        // Mark availability as taken
        $availability->setIsAvailable(false);

        $em->persist($appointment);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Appointment booked successfully!',
            'appointmentId' => $appointment->getId()
        ]);
    }

    #[Route('/appointment/{id}/cancel', name: 'patient_appointment_cancel', methods: ['POST'])]
    public function cancelAppointment(Appointment $appointment, EntityManagerInterface $em, AvailabilityRepository $availabilityRepo): JsonResponse
    {
        if ($appointment->getPatient() !== $this->getUser()->getPatient()) {
            return $this->json(['success' => false, 'message' => 'Access denied']);
        }

        // Mark the availability slot as available again
        $startDateTime = $appointment->getStartDateTime();

        // Find the availability that matches this appointment
        $availability = $availabilityRepo->findOneBy([
            'doctor' => $appointment->getDoctor(),
            'date' => $startDateTime,
            'startTime' => $startDateTime->format('H:i:s')
        ]);

        if ($availability) {
            $availability->setIsAvailable(true);
        }

        $appointment->setStatus('Cancelled');
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Appointment cancelled']);
    }

    private function getNextRecurringDate($availability): \DateTimeImmutable
    {
        // Simple implementation - returns next occurrence from today
        $dayMap = [
            'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3,
            'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7
        ];

        $targetDay = $dayMap[$availability->getDayOfWeek()] ?? 1;
        $today = new \DateTimeImmutable();
        $currentDay = (int)$today->format('N');

        $daysToAdd = $targetDay >= $currentDay ? $targetDay - $currentDay : 7 - ($currentDay - $targetDay);

        $nextDate = $today->modify("+{$daysToAdd} days")->setTime(
            (int)$availability->getStartTime()->format('H'),
            (int)$availability->getStartTime()->format('i'),
            0
        );

        // If it's today but the time has passed, move to next week
        if ($nextDate <= $today && $daysToAdd === 0) {
            $nextDate = $nextDate->modify('+7 days');
        }

        return $nextDate;
    }

}