<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;

/**
 * The reconciliation always exists; the outbox commands only come with the outbox (ADR 0013).
 */
final class OptionalCommandsRegistrationTest extends TestCase
{
    private const OUTBOX_COMMANDS = ['urssaf:paje:outbox:relay', 'urssaf:paje:outbox:purge'];

    public function testTheReconciliationIsRegisteredWithAndWithoutTheOutbox(): void
    {
        foreach ([[], ['outbox' => ['enabled' => true]]] as $config) {
            $harness = Harness::boot($config);
            $application = new Application($harness->kernel);

            static::assertTrue($application->has('urssaf:paje:declarations:reconcile-stale'));
            static::assertSame('urssaf:paje:declarations:reconcile-stale', $application->get('urssaf:paje:declarations:reconcile-stale')->getName());
        }
    }

    public function testTheOutboxCommandsAreBothRegisteredWithTheOutboxOnly(): void
    {
        $enabledHarness = Harness::boot(['outbox' => ['enabled' => true]]);
        $disabledHarness = Harness::boot();
        $enabled = new Application($enabledHarness->kernel);
        $disabled = new Application($disabledHarness->kernel);

        foreach (self::OUTBOX_COMMANDS as $name) {
            static::assertTrue($enabled->has($name), $name);
            static::assertFalse($disabled->has($name), $name);
        }
    }
}
