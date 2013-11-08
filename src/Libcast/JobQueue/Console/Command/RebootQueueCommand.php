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
use Libcast\JobQueue\Console\Command\Command;

class RebootQueueCommand extends Command
{
    protected function configure()
    {
        $this->
                setName('queue:reboot')->
                setDescription('Reboot the queue')->
                addOption('profile', 'p', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'List of profiles');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $profiles = $input->getOption('profile') ?
                $input->getOption('profile') :
                $this->jobQueue['profiles'];

        $dialog = $this->getHelperSet()->get('dialog');

        $validate = $dialog->select($output, "Do you really want to reboot the queue?", array(
            'no'  => 'Cancel',
            'yes' => 'Validate (cannot be undone)',
        ), 'no');

        if ('yes' === $validate) {
            $this->jobQueue['queue']->reboot(is_array($profiles) ? $profiles : array());
            $this->addLine('The queue has been rebooted.');
        } else {
            $this->addLine('Cancelled.');
        }

        $output->writeln($this->getLines());
    }
}
