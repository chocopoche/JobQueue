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

use Symfony\Component\Console\Command\Command;
use Libcast\JobQueue\Exception\CommandException;
use Libcast\JobQueue\Command\JobQueueApplication;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class JobQueueCommand extends Command
{
    protected $lines = array('');

    /**
     * @see Command
     */
    protected function configure()
    {
        $this->getDefinition()->addArgument(
            new InputArgument('config', InputArgument::REQUIRED, 'The configuration')
        );
    }

    /**
     * @see Command
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $config = $input->getArgument('config');
        $filesystem = new Filesystem();

        if (!$filesystem->isAbsolutePath($config)) {
            $config = getcwd().'/'.$config;
        }

        if (!file_exists($config)) {
            throw new \InvalidArgumentException(sprintf('Configuration file "%s" does not exist.', $config));
        }

        $this->jobQueue = require $config;
        $job_files = $this->jobQueue->config['job_files'];

        // var_dump($job_files);die;
        foreach ($job_files as $job_file) {
            include $job_file;
        }
        // var_dump($this->config);die;
    }

    /**
     *
     * @return \Libcast\JobQueue\Queue\QueueInterface
     */
    protected function getQueue()
    {
        return $this->jobQueue->config['queue'];
    }

    /**
     * Gets the application instance for this command.
     *
     * @return \Libcast\JobQueue\Command\JobQueueApplication
     */
    public function getApplication()
    {
        $application = parent::getApplication();

        if (!$application instanceof JobQueueApplication) {
            throw new CommandException('This application is not valid.');
        }

        return $application;
    }

    protected function addLine($line = null)
    {
        if (!$line) {
            $line = '';
        }

        if (is_array($line)) {
            $this->lines = array_merge($this->lines, $line);
            return;
        }

        $this->lines[] = $line;
    }

    protected function getLines()
    {
        $this->lines[] = '';

        return $this->lines;
    }

    protected function flushLines()
    {
        $this->lines = array('');
    }
}
