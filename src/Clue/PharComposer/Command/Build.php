<?php

namespace Clue\PharComposer\Command;

use Symfony\Component\Process\Process;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Clue\PharComposer\PharComposer;
use InvalidArgumentException;

class Build extends Command
{
    protected function configure()
    {
        $this->setName('build')
             ->setDescription('Build phar for the given composer project')
             ->addArgument('path', InputArgument::OPTIONAL, 'Path to project directory or composer.json', '.')
             ->addArgument('target', InputArgument::OPTIONAL, 'Path to write phar output to (defaults to project name)')
           /*->addOption('dev', null, InputOption::VALUE_NONE, 'If set, Whether require-dev dependencies should be shown') */;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (ini_get('phar.readonly') === "1") {
            if (!function_exists('pcntl_exec')) {
                $output->writeln('<error>Your configuration disabled writing phar files (phar.readonly = On), please update your configuration or run with "php -d phar.readonly=off ' . $_SERVER['argv'][0].'"</error>');
                return;
            }

            $output->writeln('<info>Your configuration disables writing phar files (phar.readonly = On), trying to re-spawn with correct config...');
            sleep(1);

            $args = array_merge(array('php', '-d phar.readonly=off'), $_SERVER['argv']);
            if (pcntl_exec('/usr/bin/env', $args) === false) {
                $output->writeln('<error>Unable to switch into new configuration</error>');
                return;
            }
        }

        $path = $input->getArgument('path');

        if ($this->isPackageName($path)) {
            if (is_dir($path)) {
                $output->writeln('<info>There\'s also a directory with the given name</info>');
            }
            $package = $path;
            $path = 'temporary' . mt_rand(0,9);

            $output->writeln('Installing <info>' . $package . '</info> to <info>' . $path . '...');

            $process = new Process('php composer.phar create-project ' . escapeshellarg($package) . ' ' . escapeshellarg($path) . ' --no-dev --no-progress --no-scripts');
            $process->start();
            $process->wait(function($type, $data) use ($output) {
                if ($type === Process::OUT) {
                    $output->write($data);
                }
            });
        }

        if (is_dir($path)) {
            $path = rtrim($path, '/') . '/composer.json';
        }
        if (!is_file($path)) {
            throw new InvalidArgumentException('The given path "' . $path . '" is not a readable file');
        }


        $output->getFormatter()->setStyle('warning', new OutputFormatterStyle('black', 'yellow'));

        $pharcomposer = new PharComposer($path);

        $pathVendor = $pharcomposer->getPathVendor();
        if (!is_dir($pathVendor)) {
//             if ($input->isInteractive()) {
//                 /** @var $dialog DialogHelper */
//                 $dialog = $this->getHelperSet()->get('dialog');

//                 $output->writeln('<warning>Vendor directory does not exist, looks like project was not properly installed via "composer install"</warning>');

//                 if ($dialog->askConfirmation($output, '<question>Install project via composer (execute "composer install")?</question>', true)) {
//                     $output->writeln('<info>Let\'s try to install..</info>');
//                 } else {
//                     $output->writeln('<info>Aborting...</info>');
//                     return;
//                 }
//             } else {
                $output->writeln('<error>Project is not installed via composer. Run "composer install" manually</error>');
                return;
//             }
        }

//         $timeinstalled = @filemtime($pathVendor . '/autoload.php');

//         if (filemtime($this->pathProject . '/composer.json') >= $timeinstalled) {
//             throw new RuntimeException('Looks like your "composer.json" was modified after the project was installed, try running "composer update"?');
//         }

        $target = $input->getArgument('target');
        if ($target !== null) {
            $pharcomposer->setTarget($target);
        }

        $pharcomposer->build();
    }

    private function isPackageName($path)
    {
        return !!preg_match('/^[^\s\/]+\/[^\s\/]+(\:[^\s]+)?$/i', $path);
    }
}