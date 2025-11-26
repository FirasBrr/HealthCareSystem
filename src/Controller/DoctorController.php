<?php
// src/Controller/DoctorController.php

namespace App\Controller;

use App\Entity\Doctor;
use App\Entity\User;
use App\Form\DoctorType;
use App\Repository\DoctorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/doctors')]
class DoctorController extends AbstractController
{
    #[Route('/', name: 'admin_doctors')]
    public function index(DoctorRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin/doctors/index.html.twig', [
            'doctors' => $repo->findAll()
        ]);
    }

    #[Route('/new', name: 'admin_doctors_new')]
    #[Route('/{id}/edit', name: 'admin_doctors_edit')]
    public function form(
        Request $request,
        ?Doctor $doctor = null,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $isEdit = $doctor?->getId() !== null;
        $doctor ??= new Doctor();

        $form = $this->createForm(DoctorType::class, $doctor);
        $form->handleRequest($request);

        // AJAX: Load form
        if ($request->isXmlHttpRequest() && $request->isMethod('GET')) {
            $html = $this->renderView('admin/doctors/form.html.twig', [
                'form' => $form->createView(),
                'doctor' => $doctor,
                'modal_title' => $isEdit ? 'Edit Doctor' : 'Add New Doctor'
            ]);
            return new JsonResponse(['html' => $html]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $isEdit ? $doctor->getClient() : new User();

            $user->setFirstName($form->get('firstName')->getData());
            $user->setLastName($form->get('lastName')->getData());
            $user->setEmail($form->get('email')->getData());
            $user->setRoles(['ROLE_DOCTOR']);

            if (!$isEdit) {
                $plainPassword = $form->get('plainPassword')->getData();
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }

            $doctor->setClient($user);
            $em->persist($user);
            $em->persist($doctor);
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true, 'message' => 'Doctor saved!']);
            }

            $this->addFlash('success', 'Doctor saved!');
            return $this->redirectToRoute('admin_doctors');
        }

        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('admin/doctors/form.html.twig', [
                'form' => $form->createView(),
                'doctor' => $doctor,
                'modal_title' => $isEdit ? 'Edit Doctor' : 'Add New Doctor'
            ]);
            return new JsonResponse(['html' => $html, 'success' => false], 400);
        }

        return $this->redirectToRoute('admin_doctors');
    }

    #[Route('/{id}', name: 'admin_doctors_delete', methods: ['POST'])]
    public function delete(Doctor $doctor, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$doctor->getId(), $request->request->get('_token'))) {
            $em->remove($doctor);
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true, 'message' => 'Doctor deleted!']);
            }
            $this->addFlash('success', 'Doctor deleted');
        }

        return $this->redirectToRoute('admin_doctors');
    }
}