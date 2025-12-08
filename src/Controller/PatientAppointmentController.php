<?php

namespace App\Controller;

use App\Entity\Appointment;
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

        $availabilities = $availabilityRepo->findBy([
            'doctor' => $doctor,
            'isAvailable' => true
        ], ['date' => 'ASC', 'startTime' => 'ASC']);

        return $this->render('patient/doctor_availability.html.twig', [
            'doctor' => $doctor,
            'availabilities' => $availabilities,
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

        $appointment->setStartDateTime($startDateTime);
        $appointment->setEndDateTime($endDateTime);
        $appointment->setStatus('Pending');
        $appointment->setReference(uniqid('APT_'));

        $em->persist($appointment);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Appointment booked successfully!',
            'appointmentId' => $appointment->getId()
        ]);
    }

    #[Route('/appointment/{id}/cancel', name: 'patient_appointment_cancel', methods: ['POST'])]
    public function cancelAppointment(Appointment $appointment, EntityManagerInterface $em): JsonResponse
    {
        if ($appointment->getPatient() !== $this->getUser()->getPatient()) {
            return $this->json(['success' => false, 'message' => 'Access denied']);
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

        return $today->modify("+{$daysToAdd} days")->setTime(
            (int)$availability->getStartTime()->format('H'),
            (int)$availability->getStartTime()->format('i'),
            0
        );
    }
}