<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Admin;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateAdminFixture extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        echo "=== CreateAdminFixture started ===\n";

        // Check if admin already exists
        $existingUser = $manager->getRepository(User::class)->findOneBy([
            'email' => 'admin@system.com'
        ]);

        if ($existingUser) {
            echo "Admin user already exists with email: admin@system.com\n";
            echo "=== CreateAdminFixture skipped ===\n";
            return;
        }

        echo "Creating new admin user...\n";

        // Create default ADMIN user
        $user = new User();
        $user->setEmail('admin@system.com');
        $user->setFirstName('System');
        $user->setLastName('Admin');
        $user->setRoles(['ROLE_ADMIN']);

        $hashedPassword = $this->hasher->hashPassword($user, 'admin123');
        $user->setPassword($hashedPassword);

        // Create the Admin entity linked to user
        $admin = new Admin();
        $admin->setUser($user);

        // Save to database
        $manager->persist($user);
        $manager->persist($admin);
        $manager->flush();

        echo "Admin user created successfully!\n";
        echo "Email: admin@system.com\n";
        echo "Password: admin123\n";
        echo "=== CreateAdminFixture completed ===\n";
    }
}