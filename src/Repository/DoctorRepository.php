<?php
// src/Repository/DoctorRepository.php

namespace App\Repository;

use App\Entity\Doctor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Doctor::class);
    }

    /**
     * Top docteurs par nombre de RDV (sans warning !)
     */
    public function findTopByAppointments(int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('d');

        $results = $qb
            ->select('d.id, d.specialty')
            ->addSelect('c.firstName, c.lastName') // on prend que ce qu’il faut du User
            ->addSelect('COUNT(a.id) AS appointmentCount')
            ->leftJoin('d.appointments', 'a')
            ->innerJoin('d.client', 'c') // innerJoin ou leftJoin, mais pas les deux en même temps
            ->groupBy('d.id, c.id') // IMPORTANT : grouper aussi sur c.id
            ->orderBy('appointmentCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult(); // getArrayResult() → plus jamais de problème d’hydratation !

        // On retourne un tableau propre avec les infos nécessaires
        $topDoctors = [];
        foreach ($results as $row) {
            $topDoctors[] = (object) [
                'id'              => $row['id'],
                'specialty'       => $row['specialty'],
                'firstName'       => $row['firstName'],
                'lastName'        => $row['lastName'],
                'appointmentCount'=> (int) $row['appointmentCount'],
                'client'          => (object) [
                    'getFullName' => fn() => trim($row['firstName'] . ' ' . $row['lastName'])
                ]
            ];
        }

        return $topDoctors;
    }

    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.client', 'c')
            ->addSelect('c')
            ->orderBy('d.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}