<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use App\Repository\DoctorRepository;
use App\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin/appointments')]
#[IsGranted('ROLE_ADMIN')]
final class SimpleAdminAppointmentController extends AbstractController
{#[Route('/', name: 'admin_appointments')]
public function index(
    AppointmentRepository $appointmentRepository,
    Request $request
): Response {
    // Get filter parameters
    $status = $request->query->get('status');
    $doctorId = $request->query->get('doctor');
    $patientId = $request->query->get('patient');
    $date = $request->query->get('date');
    $search = $request->query->get('search');

    // Get appointments with filters
    if ($search) {
        $appointments = $appointmentRepository->searchAppointments($search);
    } else {
        $appointments = $appointmentRepository->findWithFilters($status, $doctorId, $patientId, $date);
    }

    return $this->render('admin/appointments/index.html.twig', [
        'appointments' => $appointments,
        'current_filters' => [
            'status' => $status,
            'doctor' => $doctorId,
            'patient' => $patientId,
            'date' => $date,
            'search' => $search
        ]
    ]);
}

    #[Route('/{id}', name: 'admin_appointment_show', methods: ['GET'])]
    public function show(Appointment $appointment): Response
    {
        return $this->render('admin/appointments/show.html.twig', [
            'appointment' => $appointment,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_appointment_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Appointment $appointment,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->isMethod('POST')) {
            $status = $request->request->get('status');
            $date = $request->request->get('date');
            $time = $request->request->get('time');

            if ($status) {
                $appointment->setStatus($status);
            }

            if ($date && $time) {
                $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
                if ($dateTime) {
                    $appointment->setStartDateTime($dateTime);
                    $appointment->setEndDateTime($dateTime->modify('+30 minutes'));
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Appointment updated successfully!');

            return $this->redirectToRoute('admin_appointments');
        }

        return $this->render('admin/appointments/edit.html.twig', [
            'appointment' => $appointment,
        ]);
    }

    #[Route('/{id}/confirm', name: 'admin_appointment_confirm', methods: ['POST'])]
    public function confirm(
        Appointment $appointment,
        EntityManagerInterface $entityManager
    ): Response {
        $appointment->setStatus('Confirmed');
        $entityManager->flush();

        $this->addFlash('success', 'Appointment confirmed!');

        return $this->redirectToRoute('admin_appointments');
    }

    #[Route('/{id}/cancel', name: 'admin_appointment_cancel', methods: ['POST'])]
    public function cancel(
        Appointment $appointment,
        EntityManagerInterface $entityManager
    ): Response {
        $appointment->setStatus('Cancelled');
        $entityManager->flush();

        $this->addFlash('warning', 'Appointment cancelled!');

        return $this->redirectToRoute('admin_appointments');
    }

    #[Route('/{id}/complete', name: 'admin_appointment_complete', methods: ['POST'])]
    public function complete(
        Appointment $appointment,
        EntityManagerInterface $entityManager
    ): Response {
        $appointment->setStatus('Completed');
        $entityManager->flush();

        $this->addFlash('success', 'Appointment marked as completed!');

        return $this->redirectToRoute('admin_appointments');
    }

    #[Route('/{id}/delete', name: 'admin_appointment_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Appointment $appointment,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$appointment->getId(), $request->request->get('_token'))) {
            $entityManager->remove($appointment);
            $entityManager->flush();

            $this->addFlash('success', 'Appointment deleted successfully!');
        }

        return $this->redirectToRoute('admin_appointments');
    }

}