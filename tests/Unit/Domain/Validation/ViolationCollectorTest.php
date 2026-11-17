<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Validation;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class ViolationCollectorTest extends TestCase
{
    public function testANewCollectorIsValid(): void
    {
        $collector = new ViolationCollector();

        static::assertTrue($collector->result()->isValid());
        static::assertSame(0, $collector->errorCount());
    }

    public function testRequired(): void
    {
        $collector = new ViolationCollector();
        $long = str_repeat('x', 65);

        static::assertSame('acme', $collector->required('tenantId', 'tenant_id', 'acme', TenantId::fromString(...))?->value());
        static::assertSame(0, $collector->errorCount());
        static::assertNull($collector->required('tenantId', 'tenant_id', null, TenantId::fromString(...)));
        static::assertNull($collector->required('tenantId', 'tenant_id', '', TenantId::fromString(...)));
        static::assertNull($collector->required('tenantId', 'tenant_id', $long, TenantId::fromString(...)));

        $all = $collector->result()->all();
        static::assertSame(
            [
                'error tenantId tenant_id.required',
                'error tenantId tenant_id.required',
                'error tenantId tenant_id.too_long',
            ],
            DomainAssertions::summary($collector->result()),
        );
        static::assertSame('The tenant id is required.', $all[0]->message());
        static::assertSame([], $all[0]->params());
        static::assertSame(['max_length' => 64], $all[2]->params());
        static::assertSame(3, $collector->errorCount());
    }

    public function testOptional(): void
    {
        $collector = new ViolationCollector();

        static::assertSame('acme', $collector->optional('tenantId', 'acme', TenantId::fromString(...))?->value());
        static::assertNull($collector->optional('tenantId', null, TenantId::fromString(...)));
        static::assertNull($collector->optional('tenantId', '', TenantId::fromString(...)));
        static::assertTrue($collector->result()->isValid());
        static::assertNull($collector->optional('tenantId', str_repeat('x', 65), TenantId::fromString(...)));
        static::assertSame(['error tenantId tenant_id.too_long'], DomainAssertions::summary($collector->result()));
    }

    public function testAttemptAddAndMerge(): void
    {
        $collector = new ViolationCollector();

        static::assertSame('acme', $collector->attempt('a', static fn(): TenantId => TenantId::fromString('acme'))?->value());
        static::assertNull($collector->attempt('b', static fn(): TenantId => TenantId::fromString(str_repeat('x', 65))));
        $collector->add(Violation::warning('c', 'c.warning', 'Warning.'));
        $collector->merge(ValidationResult::of(Violation::error('d', 'd.error', 'Error.')), 'parent');
        $collector->merge(ValidationResult::of(Violation::error('', 'e.error', 'Error.')), 'root');

        static::assertSame(
            [
                'error b tenant_id.too_long',
                'warning c c.warning',
                'error parent.d d.error',
                'error root e.error',
            ],
            DomainAssertions::summary($collector->result()),
        );
        static::assertSame(3, $collector->errorCount());
    }

    public function testAttemptOnlyCatchesInvalidValue(): void
    {
        $collector = new ViolationCollector();

        $this->expectException(InvariantViolation::class);

        $collector->attempt('a', static fn(): never => throw InvariantViolation::because('a.b', 'm'));
    }

    public function testWarningsDoNotCountAsErrors(): void
    {
        $collector = new ViolationCollector();
        $collector->add(Violation::warning('a', 'a.warning', 'Warning.'));

        static::assertSame(0, $collector->errorCount());
        static::assertTrue($collector->result()->isValid());
    }
}
