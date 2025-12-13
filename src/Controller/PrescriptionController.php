<?php

namespace App\Controller;

use App\Entity\Prescription;
use App\Form\PrescriptionType;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/doctor/prescriptions')]
class PrescriptionController extends AbstractController
{
    #[Route('/', name: 'doctor_prescriptions')]
    public function index(): Response
    {
        $doctor = $this->getUser()->getDoctor();

        return $this->render('doctor/prescription/index.html.twig', [
            'prescriptions' => $doctor->getPrescriptions(),
        ]);
    }

    #[Route('/upload/{id}', name: 'doctor_prescription_upload')]
    public function upload(
        int $id,
        Request $request,
        AppointmentRepository $appointmentRepo,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $appointment = $appointmentRepo->find($id);

        if (!$appointment) {
            throw $this->createNotFoundException('Appointment not found');
        }

        // Check if doctor owns this appointment
        $doctor = $this->getUser()->getDoctor();
        if ($appointment->getDoctor()->getId() !== $doctor->getId()) {
            throw $this->createAccessDeniedException('You can only upload prescriptions for your own appointments');
        }

        // Check if appointment already has prescription
        if ($appointment->getPrescription()) {
            $this->addFlash('warning', 'This appointment already has a prescription');
            return $this->redirectToRoute('doctor_dashboard');
        }

        $now = new \DateTime();

        if ($appointment->getEndDateTime() > $now) {
            $this->addFlash('warning', 'Prescriptions can only be uploaded after the appointment time has ended');
            return $this->redirectToRoute('doctor_dashboard');
        }


        $prescription = new Prescription();
        $prescription->setAppointment($appointment);
        $prescription->setDoctor($doctor);
        $prescription->setUploadedAt(new \DateTime());

        $form = $this->createForm(PrescriptionType::class, $prescription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file upload
            $uploadedFile = $form->get('file')->getData();

            if ($uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

                // Move the file to the uploads directory
                try {
                    $uploadedFile->move(
                        $this->getParameter('prescriptions_directory'),
                        $newFilename
                    );

                    $prescription->setFileName($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'File upload failed: ' . $e->getMessage());
                    return $this->redirectToRoute('doctor_dashboard');
                }
            }

            $em->persist($prescription);
            $em->flush();

            $this->addFlash('success', 'Prescription uploaded successfully!');
            return $this->redirectToRoute('doctor_dashboard');
        }

        return $this->render('doctor/prescription/upload.html.twig', [
            'form' => $form->createView(),
            'appointment' => $appointment,
        ]);
    }

    #[Route('/{id}', name: 'doctor_prescription_view')]
    public function view(Prescription $prescription): Response
    {
        // Check authorization
        $doctor = $this->getUser()->getDoctor();
        if ($prescription->getDoctor()->getId() !== $doctor->getId()) {
            throw $this->createAccessDeniedException('You can only view your own prescriptions');
        }

        return $this->render('doctor/prescription/view.html.twig', [
            'prescription' => $prescription,
        ]);
    }
}