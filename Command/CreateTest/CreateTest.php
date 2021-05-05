<?php

namespace Prokl\CustomFrameworkExtensionsBundle\Command\CreateTest;

use Local\Commands\Exceptions\RuntimeConsoleException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class CreateTest
 * Заготовка теста.
 * @package Prokl\CustomFrameworkExtensionsBundle\Command\CreateTest
 */
class CreateTest extends Command
{
    /**
     * @var Filesystem $filesystem Symfony filesystem.
     */
    private $filesystem;

    /**
     * @var OutputInterface $output Output
     */
    private $output;

    /** @const string DIR_TEST Место, где лежат тесты. */
    private const DIR_TEST = '/local/classes/Tests/Cases/';

    /** @const string TEMPLATE_CLASS Шаблон тестового класса. */
    private const TEMPLATE_CLASS = __DIR__ . 'Templates/BaseClassTest.php.template';

    /**
     * Configure.
     */
    protected function configure() : void
    {
        $this->setName('make:test')
             ->setDescription('Create test class.')
             ->setHelp('Create test class.')
             ->addArgument('original.class', InputArgument::REQUIRED, 'original class')
             ->addArgument('path', InputArgument::REQUIRED, 'Test class path class.');
    }

    /**
     * Execute.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        /** @var string $className Оригинальный класс. */
        $className = $input->getArgument('original.class');
        /** @var string $pathTestClass Путь, где будет лежать класс теста. */
        $pathTestClass = $input->getArgument('path');

        $this->output = $output;
        $this->filesystem = new Filesystem();

        $this->output->writeln(sprintf(
            'Creating test of class %s.',
            $className
        ));

        try {
            $this->createTest(
                $className,
                $pathTestClass
            );
        } catch (RuntimeConsoleException $e) {
            return 1;
        }

        return 0;
    }

    /**
     * Создать класс с тестом.
     *
     * @param string $className Имя оригинального класса.
     * @param string $path      Папка в тестовой директории.
     *
     * @throws RuntimeConsoleException
     *
     * @since 06.09.2020 Проставляется аннотация since.
     */
    private function createTest(
        string $className,
        string $path
    ) : void {
        $testClassPath = self::DIR_TEST . $path. DIRECTORY_SEPARATOR . $className. 'Test.php';

        if (!is_dir($_SERVER['DOCUMENT_ROOT'] . self::DIR_TEST . $path. DIRECTORY_SEPARATOR)) {
            $resultDirCreation = mkdir($_SERVER['DOCUMENT_ROOT'] . self::DIR_TEST . $path. DIRECTORY_SEPARATOR, true);
            if (!$resultDirCreation) {
                throw new RuntimeConsoleException(
                    sprintf(
                        'Cannot create directory %s',
                        self::DIR_TEST . $path. DIRECTORY_SEPARATOR
                    )
                );
            }
        }

        $originalClassPath = $path. DIRECTORY_SEPARATOR . $className;

        if ($this->filesystem->exists(
            $testClassPath
        )) {
            $this->output->writeln(sprintf('<error>Test class %s already exists.</error>', $testClassPath));
            throw new RuntimeConsoleException('Test class '. $testClassPath . ' already exists.');
        }

        $skeleton = file_get_contents(
            $_SERVER['DOCUMENT_ROOT']. self::TEMPLATE_CLASS
        );


        $path = str_replace('\\\\', '\\', $path);
        $originalClassPath = str_replace('\\\\', '\\', $originalClassPath);
        $currentDate = date('d.m.Y');

        $content = str_replace(
            ['%class%', '%path.class%', '%namespace%', '%date%'],
            [$className, $originalClassPath, $path, $currentDate],
            $skeleton
        );

        file_put_contents(
            $_SERVER['DOCUMENT_ROOT']. $testClassPath,
            $content
        );

        $this->output->writeln(sprintf('Test class %s created.', $testClassPath));
    }
}
