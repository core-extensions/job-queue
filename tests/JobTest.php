<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Tests;

use CoreExtensions\JobQueue\Entity\Job;
use PHPUnit\Framework\TestCase;

final class JobTest extends TestCase
{
    public function testInitNew() {
        $job = Job::initNew();
    }
}
