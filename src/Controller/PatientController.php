<?php
// src/Controller/PatientController.php

namespace App\Controller;

use App\Entity\Patient;
use App\Form\PatientType;
use App\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function form(Request $request, ?Patient $patient = null, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $patient ??= new Patient();

        $form = $this->createForm(PatientType::class, $patient);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($patient);
            $em->flush();

            $this->addFlash('success', 'Patient saved successfully!');
            return $this->redirectToRoute('admin_patients');
        }

        return $this->render('admin/patients/form.html.twig', [
            'form' => $form->createView(),
            'patient' => $patient
        ]);
    }

    #[Route('/{id}', name: 'admin_patients_delete', methods: ['POST'])]
    public function delete(Patient $patient, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$patient->getId(), $request->request->get('_token'))) {
            $em->remove($patient);
            $em->flush();
            $this->addFlash('success', 'Patient deleted');
        }

        return $this->redirectToRoute('admin_patients');
    }
}