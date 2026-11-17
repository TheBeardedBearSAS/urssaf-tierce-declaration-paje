<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\ConstraintViolationList;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Validation\ViolationListConverter;

/**
 * The converter of the violations of the package into a Symfony constraint violation list, public for the host (forms,
 * API renderers). It needs symfony/validator; the messages are translated when a translator exists.
 */
final readonly class ValidationRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    public function register(): void
    {
        if (class_exists(ConstraintViolationList::class)) {
            $this->services->set(ViolationListConverter::class)
                ->args([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->public();
        }
    }
}
