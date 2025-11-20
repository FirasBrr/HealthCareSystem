<?php

namespace App\Controller;

use App\Entity\Doctor;
use App\Entity\Patient;
use App\Form\DoctorProfileType;
use App\Form\PatientProfileType;
use App\Form\UserProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit')]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/doctor/edit', name: 'app_profile_doctor_edit')]
    public function editDoctorProfile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_DOCTOR');

        $user = $this->getUser();
        $doctor = $user->getDoctor();

        if (!$doctor) {
            $doctor = new Doctor();
            $doctor->setClient($user);
        }

        $form = $this->createForm(DoctorProfileType::class, $doctor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($doctor);
            $entityManager->flush();

            $this->addFlash('success', 'Doctor profile updated successfully!');
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('profile/doctor_edit.html.twig', [
            'form' => $form->createView(),
            'doctor' => $doctor,
        ]);
    }

    #[Route('/profile/patient/edit', name: 'app_profile_patient_edit')]
    public function editPatientProfile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PATIENT');

        $user = $this->getUser();
        $patient = $user->getPatient();

        if (!$patient) {
            $patient = new Patient();
            $patient->setClient($user);
        }

        $form = $this->createForm(PatientProfileType::class, $patient);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($patient);
            $entityManager->flush();

            $this->addFlash('success', 'Patient profile updated successfully!');
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('profile/patient_edit.html.twig', [
            'form' => $form->createView(),
            'patient' => $patient,
        ]);
    }
}