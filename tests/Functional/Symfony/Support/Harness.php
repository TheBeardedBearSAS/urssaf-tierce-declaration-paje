<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support;

use Http\Mock\Client;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\App\TestKernel;
use Throwable;

/**
 * A booted test application with the scripted URSSAF platform.
 */
final readonly class Harness
{
    public HttpScript $urssaf;
    public Client $client;
    public MockClock $clock;

    private ContainerInterface $testContainer;

    private function __construct(public TestKernel $kernel, private PhpHandlerGuard $handlers)
    {
        $container = $kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface, 'The test container is not available.');
        $this->testContainer = $container;
        $this->client = $this->privateService('test.http_client', Client::class);
        $this->clock = $this->privateService('clock', MockClock::class);
        $this->urssaf = new HttpScript($this->client);
    }

    /**
     * @param array<string, mixed> $config "urssaf_tierce_declaration_paje" configuration merged over the defaults
     */
    public static function boot(array $config = [], bool $messenger = false, bool $lock = false): self
    {
        $handlers = PhpHandlerGuard::install();
        $kernel = new TestKernel($config, $messenger, $lock);
        try {
            $kernel->boot();
        } catch (Throwable $failure) {
            $handlers->release();

            throw $failure;
        }

        return new self($kernel, $handlers);
    }

    public function __destruct()
    {
        $this->kernel->shutdown();
        $this->handlers->release();
    }

    /**
     * A public service of the application.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function get(string $class): object
    {
        $service = $this->kernel->getContainer()->get($class);
        \assert($service instanceof $class, 'Unexpected service type.');

        return $service;
    }

    /**
     * Any service, private ones included (test container).
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function privateService(string $id, string $class): object
    {
        // @mago-expect analysis:mixed-assignment The PSR container is untyped; the instanceof below narrows the service.
        $service = $this->testContainer->get($id);
        \assert($service instanceof $class, 'Unexpected service type.');

        return $service;
    }

    public function has(string $id): bool
    {
        return $this->kernel->getContainer()->has($id);
    }

    public function hasPrivate(string $id): bool
    {
        return $this->testContainer->has($id);
    }

    public function command(string $name): CommandTester
    {
        $application = new Application($this->kernel);

        return new CommandTester($application->find($name));
    }
}
