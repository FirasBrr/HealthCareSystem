<?php

namespace App\Entity;

use App\Repository\PatientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
class Patient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $address = null;

    #[ORM\OneToOne(inversedBy: 'patient', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $client = null;

    /**
     * @var Collection<int, Appointment>
     */
    #[ORM\OneToMany(targetEntity: Appointment::class, mappedBy: 'patient')]
    private Collection $appointments;

    public function __construct()
    {
        $this->appointments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(User $client): static
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function getAppointments(): Collection
    {
        return $this->appointments;
    }

    public function addAppointment(Appointment $appointment): static
    {
        if (!$this->appointments->contains($appointment)) {
            $this->appointments->add($appointment);
            $appointment->setPatient($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): static
    {
        if ($this->appointments->removeElement($appointment)) {
            // set the owning side to null (unless already changed)
            if ($appointment->getPatient() === $this) {
                $appointment->setPatient(null);
            }
        }

        return $this;
    }

    // Business methods for better functionality
    public function getFullName(): string
    {
        return $this->client ? $this->client->getFirstName() . ' ' . $this->client->getLastName() : 'Unknown Patient';
    }

    public function getEmail(): ?string
    {
        return $this->client ? $this->client->getEmail() : null;
    }

    public function getUpcomingAppointments(): Collection
    {
        $now = new \DateTimeImmutable();
        return $this->appointments->filter(
            fn(Appointment $appointment) => $appointment->getStartDateTime() > $now
        );
    }

    public function getPastAppointments(): Collection
    {
        $now = new \DateTimeImmutable();
        return $this->appointments->filter(
            fn(Appointment $appointment) => $appointment->getStartDateTime() <= $now
        );
    }

    public function hasUpcomingAppointments(): bool
    {
        return !$this->getUpcomingAppointments()->isEmpty();
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}