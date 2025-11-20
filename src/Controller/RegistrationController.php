<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Patient;
use App\Entity\Doctor;
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
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Set the role
            $role = $form->get('role')->getData();
            $user->setRole($role);

            $phone = $form->get('phone')->getData();

            if ($role === 'ROLE_PATIENT') {
                $patient = new Patient();
                $patient->setPhone($phone);
                $patient->setAddress($form->get('address')->getData());
                $patient->setClient($user);
                $user->setPatient($patient);

                $entityManager->persist($patient);
            } elseif ($role === 'ROLE_DOCTOR') {
                $doctor = new Doctor();
                $doctor->setPhone($phone);
                $doctor->setSpecialty($form->get('specialty')->getData());
                $doctor->setBio($form->get('bio')->getData());
                $doctor->setRating(0.0);
                $doctor->setClient($user);
                $user->setDoctor($doctor);

                $entityManager->persist($doctor);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Registration successful! Please login.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}