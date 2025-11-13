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
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set the roles based on the selected role
            $selectedRole = $form->get('role')->getData();
            $user->setRoles([$selectedRole]);
            $user->setRole($selectedRole);

            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Create role-specific profile
            if ($selectedRole === 'ROLE_DOCTOR') {
                $doctor = new Doctor();
                $doctor->setClient($user);
                $doctor->setSpecialty($form->get('specialty')->getData());
                $doctor->setPhone($form->get('phone')->getData());
                $doctor->setBio($form->get('bio')->getData() ?? '');
                $doctor->setRating(0.0); // Default rating
                $entityManager->persist($doctor);
            } elseif ($selectedRole === 'ROLE_PATIENT') {
                $patient = new Patient();
                $patient->setClient($user);
                $patient->setPhone($form->get('phone')->getData());
                $patient->setAddress($form->get('address')->getData());
                $entityManager->persist($patient);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            // Add success message
            $this->addFlash('success', 'Registration successful! Please login with your credentials.');

            // Redirect to login page after registration
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}