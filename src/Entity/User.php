<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    // Relations
    #[ORM\OneToOne(mappedBy: 'client', cascade: ['persist', 'remove'])]
    private ?Doctor $doctor = null;

    #[ORM\OneToOne(mappedBy: 'client', cascade: ['persist', 'remove'])]
    private ?Patient $patient = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?Admin $admin = null;

    /**
     * @var Collection<int, Report>
     */
    #[ORM\OneToMany(targetEntity: Report::class, mappedBy: 'client')]
    private Collection $reports;

    public function __construct()
    {
        $this->reports = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // -----------------------------
    // Getters & Setters
    // -----------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Guarantee every user has at least ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * Helper: Get the main role (e.g. ROLE_DOCTOR, ROLE_PATIENT) without ROLE_USER
     */
    public function getMainRole(): ?string
    {
        $roles = $this->getRoles();
        $filtered = array_filter($roles, fn($role) => $role !== 'ROLE_USER');
        return $filtered ? reset($filtered) : null;
    }

    /**
     * Helper: Set a single main role (convenient for forms/fixtures)
     */
    public function setMainRole(string $role): static
    {
        $role = strtoupper($role);
        if (!str_starts_with($role, 'ROLE_')) {
            $role = 'ROLE_' . $role;
        }

        $this->roles = [$role];

        return $this;
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // -----------------------------
    // Relation Methods
    // -----------------------------

    public function getDoctor(): ?Doctor
    {
        return $this->doctor;
    }

    public function setDoctor(?Doctor $doctor): static
    {
        // Unset the owning side of the relation if necessary
        if ($doctor === null && $this->doctor !== null) {
            $this->doctor->setClient(null);
        }

        // Set the owning side of the relation if necessary
        if ($doctor !== null && $doctor->getClient() !== $this) {
            $doctor->setClient($this);
        }

        $this->doctor = $doctor;
        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        // Unset the owning side of the relation if necessary
        if ($patient === null && $this->patient !== null) {
            $this->patient->setClient(null);
        }

        // Set the owning side of the relation if necessary
        if ($patient !== null && $patient->getClient() !== $this) {
            $patient->setClient($this);
        }

        $this->patient = $patient;
        return $this;
    }

    public function getAdmin(): ?Admin
    {
        return $this->admin;
    }

    public function setAdmin(?Admin $admin): static
    {
        // Unset the owning side of the relation if necessary
        if ($admin === null && $this->admin !== null) {
            $this->admin->setUser(null);
        }

        // Set the owning side of the relation if necessary
        if ($admin !== null && $admin->getUser() !== $this) {
            $admin->setUser($this);
        }

        $this->admin = $admin;
        return $this;
    }

    /**
     * @return Collection<int, Report>
     */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function addReport(Report $report): static
    {
        if (!$this->reports->contains($report)) {
            $this->reports->add($report);
            $report->setClient($this);
        }
        return $this;
    }

    public function removeReport(Report $report): static
    {
        if ($this->reports->removeElement($report)) {
            // set the owning side to null (unless already changed)
            if ($report->getClient() === $this) {
                $report->setClient(null);
            }
        }
        return $this;
    }

    // -----------------------------
    // Business Logic Methods
    // -----------------------------

    /**
     * Get the associated profile (Doctor, Patient, or Admin)
     */
    public function getProfile(): Doctor|Patient|Admin|null
    {
        return $this->doctor ?? $this->patient ?? $this->admin;
    }

    /**
     * Get the profile type
     */
    public function getProfileType(): ?string
    {
        if ($this->doctor !== null) return 'doctor';
        if ($this->patient !== null) return 'patient';
        if ($this->admin !== null) return 'admin';
        return null;
    }

    /**
     * Check if user has a complete profile
     */
    public function hasCompleteProfile(): bool
    {
        return $this->getProfile() !== null;
    }

    /**
     * Check if user is activated (has roles beyond ROLE_USER)
     */
    public function isActivated(): bool
    {
        return count($this->roles) > 0;
    }

    public function __toString(): string
    {
        return $this->getFullName() ?: $this->email ?? 'New User';
    }
}