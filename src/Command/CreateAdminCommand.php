<?php

namespace App\Command;

use App\Entity\Admin;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates the first super admin user (with ROLE_ADMIN)',
    hidden: false
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Admin email')
            ->addArgument('password', InputArgument::OPTIONAL, 'Admin password (leave empty to be asked securely)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');


        $email = $input->getArgument('email');
        if (!$email) {
            $question = new Question('Enter admin email (e.g. admin@example.com): ');
            $email = $helper->ask($input, $output, $question);
        }


        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $output->writeln('<error>User with this email already exists!</error>');
            return Command::FAILURE;
        }


        $plainPassword = $input->getArgument('password');
        if (!$plainPassword) {
            $question = new Question('Enter admin password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $plainPassword = $helper->ask($input, $output, $question);
        }

        // First name / Last name
        $firstName = $helper->ask($input, $output, new Question('First name [Admin]: ', 'Admin'));
        $lastName  = $helper->ask($input, $output, new Question('Last name [Administrator]: ', 'Administrator'));

        // Create User
        $user = new User();
        $user->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setMainRole('ADMIN'); // This sets roles = ["ROLE_ADMIN"]

        // Hash password
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));


        $admin = new Admin();
        $admin->setUser($user);


        $this->em->persist($user);
        $this->em->persist($admin);
        $this->em->flush();

        $output->writeln('');
        $output->writeln('<info>Admin created successfully!</info>');
        $output->writeln(sprintf('Email: <comment>%s</comment>', $email));
        $output->writeln('Role:  <comment>ROLE_ADMIN</comment>');
        $output->writeln('You can now log in at /login');

        return Command::SUCCESS;
    }
}