<?php
// src/Repository/PatientRepository.php

namespace App\Repository;

use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PatientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Patient::class);
    }

    /**
     * Find latest patients with pagination
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.client', 'c')
            ->addSelect('c')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total patients
     */
    public function countAll(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find patients by search term
     */
    public function findBySearchTerm(string $term): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.client', 'c')
            ->addSelect('c')
            ->where('c.firstName LIKE :term')
            ->orWhere('c.lastName LIKE :term')
            ->orWhere('c.email LIKE :term')
            ->orWhere('p.phone LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('c.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}