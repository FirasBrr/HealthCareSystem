<?php
// src/Controller/PatientController.php

namespace App\Controller;

use App\Entity\Patient;
use App\Entity\User;
use App\Form\PatientType;
use App\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/patients')]
class PatientController extends AbstractController
{
    #[Route('/', name: 'admin_patients')]
    public function index(PatientRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/patients/index.html.twig', [
            'patients' => $repo->findAll()
        ]);
    }

    #[Route('/new', name: 'admin_patients_new', methods: ['GET', 'POST'])]
    #[Route('/{id}/edit', name: 'admin_patients_edit', methods: ['GET', 'POST'])]
    public function form(
        Request $request,
        ?Patient $patient = null,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $isEdit = $patient !== null;
        $patient ??= new Patient();

        $form = $this->createForm(PatientType::class, $patient, [
            'is_edit' => $isEdit
        ]);

        // Pre-populate user data when editing
        if ($isEdit && $patient->getClient()) {
            $user = $patient->getClient();
            $form->get('firstName')->setData($user->getFirstName());
            $form->get('lastName')->setData($user->getLastName());
            $form->get('email')->setData($user->getEmail());
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = null;

                if ($isEdit) {
                    // Update existing user
                    $user = $patient->getClient();
                    $user->setFirstName($form->get('firstName')->getData());
                    $user->setLastName($form->get('lastName')->getData());
                    $user->setEmail($form->get('email')->getData());

                    // Update password if provided
                    $plainPassword = $form->get('plainPassword')->getData();
                    if ($plainPassword) {
                        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                        $user->setPassword($hashedPassword);
                    }
                } else {
                    // Create new user
                    $user = new User();
                    $user->setFirstName($form->get('firstName')->getData());
                    $user->setLastName($form->get('lastName')->getData());
                    $user->setEmail($form->get('email')->getData());

                    // Hash password
                    $plainPassword = $form->get('plainPassword')->getData();
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);

                    // Set patient role
                    $user->setMainRole('ROLE_PATIENT');

                    // Link user to patient
                    $patient->setClient($user);
                }

                // Persist entities
                if (!$isEdit) {
                    $em->persist($user);
                }
                $em->persist($patient);
                $em->flush();

                $this->addFlash('success', 'Patient ' . ($isEdit ? 'updated' : 'created') . ' successfully!');
                return $this->redirectToRoute('admin_patients');

            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while saving the patient: ' . $e->getMessage());
            }
        }

        return $this->render('admin/patients/form.html.twig', [
            'form' => $form->createView(),
            'patient' => $patient,
            'modal_title' => $isEdit ? 'Edit Patient' : 'Add New Patient'
        ]);
    }

    #[Route('/{id}', name: 'admin_patients_delete', methods: ['POST'])]
    public function delete(Patient $patient, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$patient->getId(), $request->request->get('_token'))) {
            try {
                // Also remove the associated user
                $user = $patient->getClient();
                $em->remove($patient);
                if ($user) {
                    $em->remove($user);
                }
                $em->flush();
                $this->addFlash('success', 'Patient deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while deleting the patient: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_patients');
    }
}