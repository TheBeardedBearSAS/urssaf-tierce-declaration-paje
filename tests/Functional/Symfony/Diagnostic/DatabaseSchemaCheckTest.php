<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Diagnostic;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\DatabaseSchemaCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;

/**
 * The "database schema" check of the "doctrine" driver (ADR 0013 decision 3, ADR 0014).
 */
final class DatabaseSchemaCheckTest extends TestCase
{
    private const NAMED = [
        'dbal' => ['connections' => ['default' => ['driver' => 'pdo_sqlite', 'memory' => true], 'urssaf' => ['driver' => 'pdo_sqlite', 'memory' => true]]],
        'orm' => ['enable_lazy_ghost_objects' => true, 'entity_managers' => ['default' => ['connection' => 'default'], 'urssaf' => ['connection' => 'urssaf']]],
    ];

    public function testTheTablesAreReportedMissingThenInSync(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine']], false, false, true);
        $check = self::check($harness, 'default');

        $before = $check->run(self::context());
        $harness->createDatabaseSchema();
        $after = $check->run(self::context());

        static::assertSame(CheckStatus::Failure, $before[0]->status);
        static::assertSame('database schema', $before[0]->check);
        static::assertMatchesRegularExpression('/^\d+ schema change\(s\) pending on the package tables: run doctrine:migrations:diff then doctrine:migrations:migrate\.$/', $before[0]->detail);
        static::assertGreaterThanOrEqual(5, (int) $before[0]->detail);
        static::assertEquals(new CheckResult('database schema', 'default', CheckStatus::Ok, 'The package tables match the mapping.'), $after[0]);
    }

    public function testASharedEntityManagerIsAWarning(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine']], false, false, true);

        $results = self::check($harness, 'default')->run(self::context());

        static::assertCount(2, $results);
        static::assertEquals(
            new CheckResult('entity manager', 'default', CheckStatus::Warning, 'Shared with the application: use a dedicated entity manager (docs/doctrine.md).'),
            $results[1],
        );
    }

    public function testADedicatedEntityManagerHasNoWarning(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']], false, false, self::NAMED);
        $harness->createDatabaseSchemaOf('urssaf');

        $results = self::check($harness, 'urssaf')->run(self::context());

        static::assertEquals([new CheckResult('database schema', 'urssaf', CheckStatus::Ok, 'The package tables match the mapping.')], $results);
    }

    public function testTablesOfTheHostAreIgnored(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine']], false, false, true);
        $harness->createDatabaseSchema();
        $entityManager = $harness->privateService('doctrine.orm.default_entity_manager', EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('CREATE TABLE host_note (id INTEGER PRIMARY KEY)');

        $results = self::check($harness, 'default')->run(self::context());

        static::assertSame(CheckStatus::Ok, $results[0]->status);
    }

    public function testTheInstallationCommandRunsTheCheckOfTheDoctrineDriver(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine']], false, false, true);
        $harness->createDatabaseSchema();

        $command = $harness->command('urssaf:paje:check');
        $command->execute(['--skip-remote' => true]);

        static::assertStringContainsString('database schema', $command->getDisplay());
        static::assertStringContainsString('The package tables match the mapping.', $command->getDisplay());
        static::assertStringContainsString('Doctrine repositories (see the "database schema" check).', $command->getDisplay());
    }

    private static function check(Harness $harness, string $name): DatabaseSchemaCheck
    {
        return new DatabaseSchemaCheck($harness->privateService('doctrine.orm.' . $name . '_entity_manager', EntityManagerInterface::class), $name);
    }

    private static function context(): CheckContext
    {
        return CheckContext::withoutRemoteCalls([TenantId::fromString('tenant-1')]);
    }
}
