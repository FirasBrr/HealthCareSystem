<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Doctor;
use App\Entity\Patient;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $em): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Extract role safely
            $role = $form->get('roles')->getData();
            $role = is_array($role) ? ($role[0] ?? 'ROLE_USER') : $role;

            $user->setRoles([$role]);
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));

            if ($role === 'ROLE_DOCTOR') {
                $doctor = new Doctor();
                $doctor->setClient($user);
                $doctor->setPhone($form->get('phone')->getData());
                $doctor->setSpecialty($form->get('specialty')->getData());
                $doctor->setBio($form->get('bio')->getData() ?? '');
                $doctor->setRating(0.0);
                $em->persist($doctor);
            }

            if ($role === 'ROLE_PATIENT') {
                $patient = new Patient();
                $patient->setClient($user);
                $patient->setPhone($form->get('phone')->getData());
                $patient->setAddress($form->get('address')->getData());
                $em->persist($patient);
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Registration successful!');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}