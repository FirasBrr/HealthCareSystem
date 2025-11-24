<?php
// src/Repository/AppointmentRepository.php

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    // Compte les RDV d'une journée précise
    public function countByDate(\DateTimeInterface $date): int
    {
        $dateStr = $date->format('Y-m-d');

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.startDateTime >= :start')
            ->andWhere('a.startDateTime < :end')
            ->setParameter('start', $dateStr . ' 00:00:00')
            ->setParameter('end', $dateStr . ' 23:59:59')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Compte les RDV d’un mois
    public function countByMonth(\DateTimeInterface $month): int
    {
        $start = (clone $month)->modify('first day of this month 00:00:00');
        $end   = (clone $start)->modify('+1 month');

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.startDateTime >= :start')
            ->andWhere('a.startDateTime < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Nombre de RDV aujourd'hui pour un docteur
    public function countTodaysAppointments($doctor): int
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime < :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $today . ' 00:00:00')
            ->setParameter('end', $today . ' 23:59:59')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Liste des RDV d’aujourd’hui pour un docteur
    public function findTodaysAppointments($doctor): array
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        return $this->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :start')
            ->andWhere('a.startDateTime < :end')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $today . ' 00:00:00')
            ->setParameter('end', $today . ' 23:59:59')
            ->orderBy('a.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Prochains RDV du docteur
    public function findUpcomingAppointments($doctor): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.doctor = :doctor')
            ->andWhere('a.startDateTime >= :now')
            ->setParameter('doctor', $doctor)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('a.startDateTime', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    // Derniers RDV (admin dashboard)
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.doctor', 'd')->addSelect('d')
            ->leftJoin('d.client', 'dc')->addSelect('dc')
            ->leftJoin('a.patient', 'p')->addSelect('p')
            ->leftJoin('p.client', 'pc')->addSelect('pc')
            ->orderBy('a.startDateTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}