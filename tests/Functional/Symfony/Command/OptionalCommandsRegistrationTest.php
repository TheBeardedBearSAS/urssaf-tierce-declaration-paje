<?php

declare(strict_types=1);

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
            $application = new Application(Harness::boot($config)->kernel);

            static::assertTrue($application->has('urssaf:paje:declarations:reconcile-stale'));
            static::assertSame('urssaf:paje:declarations:reconcile-stale', $application->get('urssaf:paje:declarations:reconcile-stale')->getName());
        }
    }

    public function testTheOutboxCommandsAreBothRegisteredWithTheOutboxOnly(): void
    {
        $enabled = new Application(Harness::boot(['outbox' => ['enabled' => true]])->kernel);
        $disabled = new Application(Harness::boot()->kernel);

        foreach (self::OUTBOX_COMMANDS as $name) {
            static::assertTrue($enabled->has($name), $name);
            static::assertFalse($disabled->has($name), $name);
        }
    }
}
