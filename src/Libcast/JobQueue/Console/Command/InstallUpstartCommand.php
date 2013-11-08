<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Libcast\JobQueue\Exception\CommandException;
use Libcast\JobQueue\Console\Command\UpstartCommand;

class InstallUpstartCommand extends UpstartCommand
{
    protected function configure()
    {
        $this
            ->setName('upstart:install')
            ->setDescription('Install workers\' conf in /etc/init/')
        ;

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // We need the full path to the config file to generate the parameter
        // to lauch the upstart service.
        $this->configFilePath = realpath($input->getArgument('config'));

        if (!$this->upstartDir || !is_dir($this->upstartDir) || !is_writable($this->upstartDir)) {
            $output->writeln("<error>  `{$this->upstartDir}` do not exist or is not writable. You may try with "
              . "sudo or install upstart (at your own risks).  </error>");
            return;
        }

        // Iterate over each worker
        foreach ($this->jobQueue['workers'] as $worker => $profiles) {
            $this->install($worker, $output);
        }
    }

    /**
     * Create a file
     * @param  string          $worker [description]
     * @param  string          $upstart_dir [description]
     * @param  OutputInterface $output [description]
     * @return [type]                  [description]
     */
    protected function install($worker, OutputInterface $output)
    {
        $console = realpath($_SERVER['PHP_SELF']);
        $command = "worker:run $worker {$this->configFilePath}";

        $conf = $this->getWorkerConfPath($worker);
        if (is_file($conf)) {
            $output->writeln("<error>  File `{$conf}` already exists. Manually delete it and relaunch the command.  </error>");
            return false;
        }

        $fh = fopen($conf, 'w+');
        fwrite($fh, implode(PHP_EOL, array(
            'start on runlevel 2',
            'stop on runlevel [!2]',
            'respawn',
            'respawn limit 2 10',
            "exec sudo -u www-data $console $command",
        )));
        fclose($fh);
        $output->writeln("<info>  File `{$conf}` installed.  </info>");
        return true;
    }

}
