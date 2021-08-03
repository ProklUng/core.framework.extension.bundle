<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Prokl\CustomFrameworkExtensionsBundle\Command\Fork;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Warmup the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class CacheWarmupCommand extends Command
{
    protected static $defaultName = 'cache:warmup';

    private $cacheWarmer;

    /**
     * @var KernelInterface $kernel
     */
    private $kernel;

    public function __construct(CacheWarmerAggregate $cacheWarmer, KernelInterface $kernel)
    {
        parent::__construct();

        $this->cacheWarmer = $cacheWarmer;
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ])
            ->setDescription('Warm up an empty cache')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command warms up the cache.

Before running this command, the cache must be empty.

This command does not generate the classes cache (as when executing this
command, too many classes that should be part of the cache are already loaded
in memory). Use <comment>curl</comment> or any other similar tool to warm up
the classes cache if you want.

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->comment(sprintf('Warming up the cache for the <info>%s</info> environment with debug <info>%s</info>', $this->kernel->getEnvironment(), var_export($this->kernel->isDebug(), true)));

        if (!$input->getOption('no-optional-warmers')) {
            $this->cacheWarmer->enableOptionalWarmers();
        }

        $this->cacheWarmer->warmUp($this->kernel->getContainer()->getParameter('kernel.cache_dir'));

        $io->success(sprintf('Cache for the "%s" environment (debug=%s) was successfully warmed.', $this->kernel->getEnvironment(), var_export($this->kernel->isDebug(), true)));

        return 0;
    }
}
