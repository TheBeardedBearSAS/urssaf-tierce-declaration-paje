<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Http\Mock\Client as MockClient;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\UrssafTierceDeclarationPajeBundle;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Credentials\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\ExtensionConfigSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;

/**
 * Minimal application hosting the bundle: the framework, optionally DoctrineBundle (in-memory SQLite) and an in-memory
 * Messenger transport. The URSSAF HTTP client is a mock ("test.http_client") and the clock a fixed one ("clock"). The
 * bundle messages are routed by namespace, so that the kernel does not depend on the message classes.
 *
 * The persistence driver is "in_memory" by default; the "$doctrine" parameter is in last position, like the sibling
 * packages.
 *
 * The compiled container is cached per configuration for the duration of the PHP process only (a stale container
 * would hide a code change, and mutation testing swaps classes under a running process).
 */
final class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    // @mago-expect lint:no-literal-password Fake secret of the test application, never a real credential.
    public const SECRET = 'top-secret-client-secret';
    public const NOW = '2026-11-15 10:00:00 UTC';
    public const MESSAGES = 'TheBeardedBear\\UrssafTierceDeclarationPaje\\Bridge\\Symfony\\Message\\*';

    private static ?string $cacheRoot = null;

    /**
     * @param array<string, mixed>      $bundleConfig configuration of "urssaf_tierce_declaration_paje", merged over the defaults
     * @param bool                      $messenger    true to configure an in-memory "async" transport and route the bundle messages
     * @param bool                      $lock         true to configure the framework lock component (in-memory store)
     * @param bool|array<string, mixed> $doctrine     true for DoctrineBundle on an in-memory SQLite, or its own configuration
     */
    public function __construct(
        private readonly array $bundleConfig = [],
        private readonly bool $messenger = false,
        private readonly bool $lock = false,
        private readonly bool|array $doctrine = false,
    ) {
        $_ENV['URSSAF_TEST_SECRET'] = self::SECRET;
        parent::__construct('t' . substr(md5(serialize([$bundleConfig, $messenger, $lock, $doctrine])), 0, 12), false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the constructor.
        return [
            'environment' => 'sandbox',
            'credentials' => ['tenant-1' => ['client_id' => 'client-1', 'client_secret' => '%env(URSSAF_TEST_SECRET)%']],
            'http_client' => 'test.http_client',
            'persistence' => ['driver' => 'in_memory'],
        ];
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        if ($this->doctrine !== false) {
            yield new DoctrineBundle();
        }
        yield new UrssafTierceDeclarationPajeBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 4);
    }

    public function getCacheDir(): string
    {
        if (self::$cacheRoot === null) {
            self::$cacheRoot = $this->getProjectDir() . '/var/t5/cache/' . bin2hex(random_bytes(8));
            register_shutdown_function(static function (): void {
                try {
                    (new Filesystem())->remove((string) self::$cacheRoot);
                } catch (IOException) {
                    // @mago-expect lint:no-empty-catch-clause The cache is disposable: a directory left behind is harmless.
                }
            });
        }

        return self::$cacheRoot . '/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir() . '/log';
    }

    /**
     * The compiled container as YAML (what a container debugging command shows).
     */
    public function containerAsYaml(): string
    {
        $this->initializeBundles();
        $container = $this->buildContainer();
        $container->compile();

        return (new YamlDumper($container))->dump();
    }

    /**
     * The services as declared by the bundles: extensions loaded, but nothing removed, inlined nor resolved.
     * The optional snapshot records the configuration of an extension after the prepending.
     */
    public function definitions(): ContainerBuilder
    {
        return $this->definitionsRecordedBy(new ExtensionConfigSnapshot('urssaf_tierce_declaration_paje'));
    }

    public function definitionsRecordedBy(ExtensionConfigSnapshot $snapshot): ContainerBuilder
    {
        $this->initializeBundles();
        $container = $this->buildContainer();
        $container->addCompilerPass($snapshot, PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $passes = $container->getCompilerPassConfig();
        $passes->setOptimizationPasses([]);
        $passes->setRemovingPasses([]);
        $passes->setAfterRemovingPasses([]);
        $container->compile();

        return $container;
    }

    public function configureContainer(ContainerConfigurator $container): void
    {
        // @mago-expect lint:no-literal-password The framework "secret" of a throw-away test application.
        $framework = [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'cache' => ['app' => 'cache.adapter.array'],
        ];
        if ($this->messenger) {
            $framework['messenger'] = [
                'transports' => ['async' => 'in-memory://'],
                'routing' => [self::MESSAGES => 'async'],
            ];
        }
        if ($this->lock) {
            $framework['lock'] = 'in-memory';
        }
        $container->extension('framework', $framework);
        if ($this->doctrine !== false) {
            $container->extension('doctrine', \is_array($this->doctrine) ? $this->doctrine : [
                'dbal' => ['driver' => 'pdo_sqlite', 'memory' => true],
                'orm' => ['enable_lazy_ghost_objects' => true, 'enable_native_lazy_objects' => DoctrineIntegration::nativeLazyObjectsAvailable()],
            ]);
        }
        $container->extension('urssaf_tierce_declaration_paje', array_replace_recursive(self::defaults(), $this->bundleConfig));

        $services = $container->services();
        $services->set('test.http_client', MockClient::class)->public();
        $services->set('test.accounts', InMemoryEmployerAccountRepository::class)->public();
        $services->set('test.mandates', InMemoryMandateRepository::class)->public();
        $services->set('test.declarations', InMemoryDeclarationRecordRepository::class)->public();
        $services->set('test.transactions', ImmediateTransactionManager::class)->public();
        $services->set('test.outbox', InMemoryOutbox::class)->public();
        $services->set('test.sink', RecordingIntegrationEventSink::class)->args([['refused-aggregate']])->public();
        $services->set('test.statistics', InMemoryWorkflowStatistics::class)->args([new Reference('test.accounts'), new Reference('test.mandates'), new Reference('test.declarations')])->public();
        $services->set('test.token_store', InMemoryTokenStore::class)->public();
        $services->set('test.credentials', StaticCredentialsProvider::class)->args([[]])->public();
        if ($this->lock) {
            // Kept even when nothing uses it (lock disabled): the oldest supported Symfony removes unused private services.
            $services->alias('test.lock_factory', 'lock.factory')->public();
        }
        $services->set('logger', CapturingLogger::class)->public();
        $services->set('clock', MockClock::class)->args([self::NOW])->public();
    }
}
