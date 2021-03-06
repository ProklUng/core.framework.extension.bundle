<?php

namespace Prokl\CustomFrameworkExtensionsBundle\Services\Console;

use Exception;
use IteratorAggregate;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Bitrix\Main\ModuleManager;

/**
 * Class ConsoleCommandConfigurator
 * @package Prokl\CustomFrameworkExtensionsBundle\Services\Console
 *
 * @since 10.12.2020
 * @since 20.12.2020 Рефакторинг. Форк нативного способа подключения команд.
 * @since 26.02.2021 Убрал array_merge в цикле.
 * @since 12.01.2021 Подхватывание команд установленных битриксовых модулей.
 */
class ConsoleCommandConfigurator
{
    /**
     * @var Application $application Конфигуратор консольных команд.
     */
    private $application;

    /**
     * @var Command[] $commands Команды.
     */
    private $commands;

    /**
     * @var ContainerInterface $servicesIdCommands
     */
    private $container;

    /**
     * ConsoleCommandConfigurator constructor.
     *
     * @param Application        $application Конфигуратор консольных команд.
     * @param ContainerInterface $container   Контейнер.
     */
    public function __construct(
        Application $application,
        ContainerInterface $container
    ) {
        $this->application = $application;
        $this->container = $container;
    }

    /**
     * Инициализация команд.
     *
     * @return self
     */
    public function init(): self
    {
        $this->registerCommands();

        return $this;
    }

    /**
     * Запуск команд.
     *
     * @return void
     * @throws Exception
     */
    public function run(): void
    {
        $this->application->run();
    }

    /**
     * Добавить команды.
     *
     * @param array $commands Команды.
     *
     * @return void
     * @throws Exception
     */
    public function add(...$commands): void
    {
        $result = [];

        foreach ($commands as $command) {
            $array = $command;
            if ($command instanceof IteratorAggregate) {
                $iterator = $command->getIterator();
                $array = iterator_to_array($iterator);
            }

            $result[] = $array;
        }

        $this->commands = array_merge($this->commands, $result);
    }

    /**
     * Finds a command by name or alias.
     *
     * Contrary to get, this command tries to find the best
     * match if you give it an abbreviation of a name or alias.
     *
     * @param string $name A command name or a command alias.
     *
     * @return Command A Command instance
     *
     * @throws CommandNotFoundException When command name is incorrect or ambiguous
     */
    public function find(string $name) : Command
    {
        return $this->application->find($name);
    }

    /**
     * Опция авто выхода из команды.
     *
     * @param boolean $autoexit
     *
     * @return void
     */
    public function setAutoExit(bool $autoexit) : void
    {
        $this->application->setAutoExit($autoexit);
    }

    /**
     * Регистрация команд битриксовых модулей.
     *
     * @return void
     *
     * @since 12.01.2021
     *
     * @internal Формат файла cli.php в папке модуля.
     * return [
     *      new SampleCommand(), // Должен наследоваться от \Symfony\Component\Console\Command\Command
     *      container()->get('sample.command') // Из контейнера
     * ]
     */
    private function registerModuleCommands() : void
    {
        // Проверка - в Битриксе мы или нет.
        if (!class_exists(ModuleManager::class)) {
            return;
        }

        $result = [];

        $documentRoot = $this->container->getParameter('kernel.project_dir');

        foreach (glob($documentRoot . '/local/modules/*/cli.php') as $path) {
            $moduleName = $this->getModuleNameByPath($path);
            if (ModuleManager::isModuleInstalled($moduleName)) {
                $result = require_once $path;
            }
        }

        foreach (glob($documentRoot . '/bitrix/modules/*/cli.php') as $path) {
            $moduleName = $this->getModuleNameByPath($path);
            if (ModuleManager::isModuleInstalled($moduleName)) {
                $result  = require_once $path;
            }
        }

        foreach ((array)$result as $item) {
            if (is_subclass_of($item, Command::class)) {
                $this->application->add($item);
            }
        }
    }

    /**
     * Регистрация команд.
     *
     * @return void
     */
    private function registerCommands() : void
    {
        $bundles = (array)$this->container->getParameter('kernel.bundles');

        foreach ($bundles as $bundle) {
            if ($bundle instanceof Bundle) {
                $bundle->registerCommands($this->application);
            }
        }

        if ($this->container->has('console.command_loader')) {
            $this->application->setCommandLoader(
                $this->container->get('console.command_loader')
            );
        }

        if ($this->container->hasParameter('console.command.ids')) {
            $lazyCommandIds = $this->container->hasParameter('console.lazy_command.ids')
                ? $this->container->getParameter('console.lazy_command.ids') :
                [];

            foreach ($this->container->getParameter('console.command.ids') as $id) {
                if (!isset($lazyCommandIds[$id])) {
                    $this->application->add(
                        $this->container->get($id)
                    );
                }
            }
        }

        $this->registerModuleCommands();
    }

    /**
     * Название битриксового модуля по пути.
     *
     * @param string $path
     *
     * @return string
     * @since 12.01.2021
     */
    private function getModuleNameByPath(string $path): string
    {
        $documentRoot = $this->container->getParameter('kernel.project_dir');

        $path = str_replace(
            [
                $documentRoot . '/bitrix/modules/',
                $documentRoot . '/local/modules/',
            ],
            '',
            $path
        );

        return current(explode('/', $path));
    }
}