<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Doctor;
use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    // Find appointments by doctor
    public function findByDoctor(Doctor $doctor): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.doctor = :doctor')
            ->setParameter('doctor', $doctor)
            ->orderBy('a.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Find appointments by patient
    public function findByPatient(Patient $patient): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('a.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Find appointments by status
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', $status)
            ->orderBy('a.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Find upcoming appointments
    public function findUpcomingAppointments(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.startDateTime > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('statuses', ['Pending', 'Confirmed'])
            ->orderBy('a.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Find appointments between dates
    public function findAppointmentsBetweenDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.startDateTime BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('a.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Find available time slots for a doctor on a specific date
    public function findAvailableSlots(Doctor $doctor, \DateTimeInterface $date): array
    {
        // This would need to check against the doctor's availability
        $startOfDay = (clone $date)->setTime(0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('a')
            ->andWhere('a.doctor = :doctor')
            ->andWhere('a.startDateTime BETWEEN :start AND :end')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('doctor', $doctor)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->setParameter('statuses', ['Pending', 'Confirmed'])
            ->orderBy('a.startDateTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Save appointment (persist and flush)
    public function save(Appointment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // Remove appointment
    public function remove(Appointment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // Find appointments for dashboard (recent and upcoming)
    public function findForDashboard(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.startDateTime > :now')
            ->setParameter('now', new \DateTimeImmutable('-1 day'))
            ->orderBy('a.startDateTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // Count appointments by status
    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}