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
use Libcast\JobQueue\Console\OutputTable;

class ControlUpstart extends UpstartCommand
{
    protected function configure()
    {
        $this
            ->setName('upstart:control')
            ->setDescription('Control workers via upstart')
            ->addArgument('action', InputArgument::REQUIRED, 'stop|start|status')
        ;

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument('action');
        $method = "{$action}Workers";
        if (method_exists($this, $method)) {
            $this->$method($output);
        } else {
            throw new CommandException("$action not implemented.");
        }
    }

    protected function statusWorkers($output)
    {
        $table = new OutputTable;
        $table->addColumn('Name',           6,   OutputTable::RIGHT);
        $table->addColumn('Upstart Name',   12,  OutputTable::RIGHT);
        $table->addColumn('Status',         8,   OutputTable::LEFT);

        foreach ($this->jobQueue['workers'] as $worker => $profiles) {
            if (!$this->workerConfIsInstalled($worker)) {
                $status = "Not installed";
            }
            else {
                $status = $this->pingWorker($worker);
                $status = $status == false ? "Not running" : $status;
            }
            $table->addRow(array(
                'Name'          => $worker,
                'Upstart Name'  => $this->getUpstartName($worker),
                'Status'        => $status,
            ));
        }
        $output->writeln($table->getTable(true));
    }

    protected function startWorkers($output)
    {
        $output->writeln('Start Workers:');

        foreach ($this->jobQueue['workers'] as $worker => $profiles) {
            if ($this->startWorker($worker)) {
                $output->writeln("<info>$worker started</info>");
            }
        }
    }
    protected function startWorker($worker)
    {
        $this->jobQueue['queue']->reboot($this->jobQueue['profiles']);
        if ($this->pingWorker($worker)) {
            return false;
        }

        return $this->controlUpstart('start', $worker);
    }

    protected function stopWorkers($output)
    {
        $output->writeln('Stop Workers:');

        foreach ($this->jobQueue['workers'] as $worker => $profiles) {
            if ($this->stopWorker($worker)) {
                $output->writeln("<info>$worker stopped</info>");
            }
        }
    }
    protected function stopWorker($worker)
    {
        if (!$this->pingWorker($worker)) {
            return false;
        }

        return $this->controlUpstart('stop', $worker);
    }

    protected function controlUpstart($action, $worker)
    {
        if (!in_array($action, array('stop', 'start'))) {
            throw new CommandException("Action '$action' does not exists.");
        }

        return false !== system("service {$this->getUpstartName($worker)} $action &> /dev/null");
    }

    protected function pingWorker($worker)
    {
        exec("pgrep -c -f {$this->getUpstartName($worker)}", $count);

        return $count[0] < 2 ? false : true;
    }
}
