<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TestFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        echo "=== TestFixture is working! ===\n";
    }
}