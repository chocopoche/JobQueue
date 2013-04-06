<?php

namespace Libcast\Job\Worker;

use Libcast\Job\Exception\WorkerException;
use Libcast\Job\Worker\WorkerInterface;

use Libcast\Job\Queue\QueueInterface;
use Libcast\Job\Task\Task;

use Psr\Log\LoggerInterface;

class Worker implements WorkerInterface
{
  const STATUS_WORKING = 'working';

  const STATUS_PAUSED = 'paused';

  protected $name;
  
  protected $status = null;

  protected $queue;
  
  protected $profiles;

  protected $logger;
  
  /**
   * Setup a Worker to connect a Queue.
   * The Worker will receive Tasks from Queue profiled sets.
   * Each Task will setup a Job that can be run (executed).
   * 
   * @param string                    $name     Name of the Worker
   * @param Queue                     $queue    Queue instance
   * @param array                     $profiles Profiles names (sets of Tasks)
   * @param \Psr\Log\LoggerInterface  $logger   Implementation of Psr\Log class
   */
  function __construct($name, QueueInterface $queue, $profiles = array(), LoggerInterface $logger = null)
  {
    // makes sure the Worker has space
    ini_set('memory_limit', '-1');
    ini_set('max_execution_time', '-1');
    set_time_limit(0);

    $this->setName($name);
    $this->setStatus(self::STATUS_PAUSED);
    $this->setQueue($queue);
    $this->setProfiles($profiles);
    $this->setLogger($logger);

    set_error_handler(function ($code, $message, $file, $line, $context) use ($logger)
    {
      if ($logger)
      {
        $logger->error($message, array(
            'file'    => $file,
            'line'    => $line,
            'context' => $context,
        ));
      }
    });
  }

  protected function setName($name)
  {
    $this->name = (string) $name;
  }

  protected function getName()
  {
    return $this->name;
  }

  protected function setStatus($status)
  {
    if (!in_array($status, array(self::STATUS_WORKING, self::STATUS_PAUSED)))
    {
      throw new WorkerException("Status '$status' does not exists.");
    }

    $this->status = $status;
  }

  protected function getStatus()
  {
    return $this->status;
  }

  protected function setQueue(QueueInterface $queue)
  {
    $this->queue = $queue;
  }

  /**
   * @return \Libcast\Job\Queue\QueueInterface 
   */
  protected function getQueue()
  {
    return $this->queue;
  }

  protected function setProfiles($profiles)
  {
    $this->profiles = (array) $profiles;
  }

  protected function getProfiles()
  {
    return $this->profiles;
  }

  protected function setLogger(LoggerInterface $logger)
  {
    $this->logger = $logger;
  }

  /**
   * @return \Psr\Log\LoggerInterface 
   */
  protected function getLogger()
  {
    return $this->logger;
  }

  public function run()
  {
    $queue = $this->getQueue();

    /* @hack avoid multiple worker startup to run a same Task twice */
    usleep(rand(0, 3000));

    $this->log(sprintf('Worker \'%s\' start taking Tasks from Queue with %s profile%s.', 
            $this->getName(),
            implode(', ', $this->getProfiles()),
            count($this->getProfiles()) > 1 ? 's' : ''));

    $this->setStatus(self::STATUS_WORKING);

    while (true)
    {
      while ($task = $queue->getNextTask($this->getProfiles()))
      {
        /* @var $task \Libcast\Job\Task\TaskInterface */

        $this->setStatus(self::STATUS_WORKING);

        $this->log("Worker '$this' received Task '{$task->getId()}'", array(
            'tag' =>$task->getTag(),
            'job' => $task->getJob()->getClassName(),
            'priority' => $task->getOption('priority'),
            'profile' => $task->getOption('profile'),
            'children' => count($task->getChildren()),
        ));

        if (!$queue->flag($task))
        {
          throw new WorkerException("The task '{$task->getId()}' can't be reserved.");
        }

        $job = $task->getJob();
        $job->setup($task, $queue, $this->getLogger());

        // flag Task as running
        $task->setStatus(Task::STATUS_RUNNING);
        $queue->update($task);

        if ($job->execute())
        {
          // flag Task as success
          $task->setStatus(Task::STATUS_SUCCESS);
          $queue->update($task);

          $finished = true;
          foreach ($task->getChildren() as $child)
          {
            /* @var $child \Libcast\Job\Task\TaskInterface */

            // insert parent id to be able to follow children and flag the
            // parent Task as finished
            $child->setParentId($task->getId());

            $id = $queue->add($child);

            $finished = false;
          }

          if ($finished)
          {
            // flag Task as finished
            $task->setStatus(Task::STATUS_FINISHED);
            $queue->update($task);
          }
        }
        else 
        {
          // flag Task as failed
          $task->setStatus(Task::STATUS_FAILED);
          $queue->update($task);
        }
        
        sleep(3); // give CPU some rest
      }
      
      // log pause
      if (self::STATUS_WORKING === $this->getStatus())
      {
        $this->log("Worker '$this' paused.");
      }
      
      $this->setStatus(self::STATUS_PAUSED);

      sleep(15); // no more Task, let's sleep a little bit longer
    }
  }
  
  protected function log($message, $context = array())
  {
    if ($logger = $this->getLogger())
    {
      $logger->info($message, $context);
    }
  }
  
  function __destruct()
  {
    $this->log("Worker '$this' stoped.");
  }
  
  public function __toString()
  {
    return $this->getName();
  }
}