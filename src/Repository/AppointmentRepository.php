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
    public function findWithFilters(
        ?string $status = null,
        ?int $doctorId = null,
        ?int $patientId = null,
        ?string $date = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.doctor', 'd')
            ->leftJoin('a.patient', 'p')
            ->addSelect('d')
            ->addSelect('p')
            ->leftJoin('d.client', 'dc')
            ->leftJoin('p.client', 'pc')
            ->addSelect('dc')
            ->addSelect('pc')
            ->orderBy('a.startDateTime', 'DESC');

        if ($status && $status !== '') {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        if ($doctorId && $doctorId !== '') {
            $qb->andWhere('d.id = :doctorId')
                ->setParameter('doctorId', $doctorId);
        }

        if ($patientId && $patientId !== '') {
            $qb->andWhere('p.id = :patientId')
                ->setParameter('patientId', $patientId);
        }

        if ($date && $date !== '') {
            $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if ($dateObj) {
                $startOfDay = $dateObj->setTime(0, 0, 0);
                $endOfDay = $dateObj->setTime(23, 59, 59);

                $qb->andWhere('a.startDateTime BETWEEN :startDate AND :endDate')
                    ->setParameter('startDate', $startOfDay)
                    ->setParameter('endDate', $endOfDay);
            }
        }

        return $qb->getQuery()->getResult();
    }

    // ADD THIS METHOD: Search appointments by reference or patient name
    public function searchAppointments(string $searchTerm): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.patient', 'p')
            ->leftJoin('p.client', 'pc')
            ->leftJoin('a.doctor', 'd')
            ->leftJoin('d.client', 'dc')
            ->addSelect('p', 'pc', 'd', 'dc')
            ->where('a.reference LIKE :term')
            ->orWhere('pc.firstName LIKE :term')
            ->orWhere('pc.lastName LIKE :term')
            ->orWhere('dc.firstName LIKE :term')
            ->orWhere('dc.lastName LIKE :term')
            ->orWhere('pc.email LIKE :term')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('a.startDateTime', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    // ADD THIS METHOD: Get appointments statistics for admin dashboard
    public function getAdminStatistics(): array
    {
        $today = new \DateTimeImmutable('today');
        $startOfMonth = $today->modify('first day of this month');
        $endOfMonth = $today->modify('last day of this month 23:59:59');

        // Get count of appointments by status
        $statusCounts = $this->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as count')
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();

        // Format status counts
        $statusStats = [];
        foreach ($statusCounts as $row) {
            $statusStats[$row['status']] = (int)$row['count'];
        }

        // Get appointments for today
        $todayCount = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.startDateTime >= :startToday')
            ->andWhere('a.startDateTime <= :endToday')
            ->setParameter('startToday', $today->setTime(0, 0, 0))
            ->setParameter('endToday', $today->setTime(23, 59, 59))
            ->getQuery()
            ->getSingleScalarResult();

        // Get appointments for this month
        $monthCount = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.startDateTime >= :startMonth')
            ->andWhere('a.startDateTime <= :endMonth')
            ->setParameter('startMonth', $startOfMonth)
            ->setParameter('endMonth', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'today' => (int)$todayCount,
            'month' => (int)$monthCount,
            'status' => $statusStats,
            'total' => (int)$this->count([])
        ];
    }

    
}