<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Libcast\JobQueue\Queue\QueueInterface;
use Libcast\JobQueue\JobQueue;
use Libcast\JobQueue\Console\Command\AddDummyJob;
use Libcast\JobQueue\Console\Command\ControlUpstart;
use Libcast\JobQueue\Console\Command\DeleteJob;
use Libcast\JobQueue\Console\Command\EditJob;
use Libcast\JobQueue\Console\Command\FlushQueue;
use Libcast\JobQueue\Console\Command\InstallUpstart;
use Libcast\JobQueue\Console\Command\ListJob;
use Libcast\JobQueue\Console\Command\RebootQueue;
use Libcast\JobQueue\Console\Command\RunWorker;


class Application extends BaseApplication
{
    protected $parameters;

    /**
     * Constructor.
     *
     * @param \Libcast\JobQueue\Queue\QueueInterface  $queue
     * @param array                                   $parameters
     *
     * @api
     */
    function __construct()
    {
        parent::__construct('Libcast Job Queue CLI', JobQueue::VERSION);
        $this->add(new AddDummyJob);
        $this->add(new ControlUpstart);
        $this->add(new DeleteJob);
        $this->add(new EditJob);
        $this->add(new FlushQueue);
        $this->add(new InstallUpstart);
        $this->add(new ListJob);
        $this->add(new RebootQueue);
        $this->add(new RunWorker);
    }
}
