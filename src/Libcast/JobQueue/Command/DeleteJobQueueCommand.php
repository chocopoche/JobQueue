<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file 
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Libcast\JobQueue\Command\JobQueueCommand;

class DeleteJobQueueCommand extends JobQueueCommand
{
    protected function configure()
    {
        $this->
                setName('jobqueue:delete')->
                setDescription('Delete a Task')->
                addArgument('id', InputArgument::REQUIRED, 'Task Id');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queue = $this->getQueue();

        $task = $queue->getTask($input->getArgument('id'));

        $queue->remove($task);

        $this->addLine("Task $task has been removed from Queue.");

        $output->writeln($this->getLines());
    }
}