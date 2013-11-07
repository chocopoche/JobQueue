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

use Libcast\JobQueue\Console\Command\Command;

class UpstartCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        parent::configure();
        $this->upstartDir = '/etc/init';
    }

    /**
     * Check if the upstart config file exists.
     *
     * @param  string $name The worker name
     * @return boolean      Wheter or not it exists
     */
    protected function workerConfIsInstalled($name)
    {
        return is_file($this->getWorkerConfPath($name));
    }

    /**
     * Build the upstart worker conf path
     *
     * @param  string $name The worker name
     * @return string       The upstart worker conf path
     */
    protected function getWorkerConfPath($name)
    {
        return "{$this->upstartDir}/{$this->getUpstartName($name)}.conf";
    }

    /**
     * Build the upstart service name, arbitrarily
     *
     * @param  string $name The worker name
     * @return string       The upstart service name
     */
    protected function getUpstartName($name)
    {
        return "libcast-jobqueue-{$name}";
    }
}
