<?php

/*
 * This file is part of Libcast JobQueue component.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file 
 * that was distributed with this source code.
 */

namespace Libcast\JobQueue\Task;

use Libcast\JobQueue\Exception\TaskException;
use Libcast\JobQueue\Job\JobInterface;
use Libcast\JobQueue\Task\TaskInterface;
use Libcast\JobQueue\Notification\Notification;

class Task implements TaskInterface
{
    /**
     * instance of a Task not yet enqueued
     */
    const STATUS_PENDING = 'pending';

    /**
     * enqueued Task waiting to be executed
     */
    const STATUS_WAITING = 'waiting';

    /**
     * running Task (executed by a Job)
     */
    const STATUS_RUNNING = 'running';

    /**
     * success
     */
    const STATUS_SUCCESS = 'success';

    /**
     * error
     */
    const STATUS_FAILED = 'failed';

    /**
     * Task may be destroyed
     */
    const STATUS_FINISHED = 'finished';

    /**
     * @var integer
     */
    protected $id = null;

    /**
     * @var string
     */
    protected $tag = null;

    /**
     * @var integer
     */
    protected $parent_id = null;

    /**
     * @var JobInterface
     */
    protected $job = null;

    /**
     * @var string
     */
    protected $status = null;

    /**
     * @var float
     */
    protected $progress = 0;

    /**
     * @var int
     */
    protected $created_at = null;

    /**
     * @var int
     */
    protected $scheduled_at = null;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var array
     */
    protected $parameters = array();

    /**
     * @var \Libcast\JobQueue\Notification\Notification
     */
    protected $notification;

    /**
     * @var array
     */
    protected $children = array();

    /**
     * @var int
     */
    protected $children_count = null;

    /**
     * Create a new Task
     * 
     * Some options are required:
     * - priority : from 1 (lower) to infinite (higher)
     * - set      : any set name registred from Queue configuration
     * 
     * Required parameters may be required depending on the Job associated
     * with this Task
     * 
     * @param \Libcast\JobQueue\Job\JobInterface  $job          Affect a job to the task
     * @param array                               $options      Task options
     * @param array                               $parameters   Task parameters
     * @param Notification                        $notification Notification for succes and or alert
     */
    function __construct(JobInterface $job, $options = array(), $parameters = array(), Notification $notification = null)
    {
        $this->setJob($job);

        $this->setOptions($options);

        $this->setParameters($parameters);

        if ($notification) {
            $this->setNotification($notification);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getStatuses()
    {
        return array(
            self::STATUS_PENDING,
            self::STATUS_WAITING,
            self::STATUS_RUNNING,
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_FINISHED,
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function getFakeTaskStatuses()
    {
        return array(
            self::STATUS_PENDING,
            self::STATUS_FINISHED,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function setTag($tag)
    {
        $this->tag = $tag;
    }

    /**
     * {@inheritdoc}
     */
    public function getTag()
    {
        if (!$this->tag) {
            $this->setTag(md5(uniqid().rand(0, 9999)));
        }

        return $this->tag;
    }

    /**
     * {@inheritdoc}
     */
    public function setParentId($id)
    {
        $this->parent_id = $id;
    }

    /**
     * {@inheritdoc}
     */
    public function getParentId()
    {
        return $this->parent_id;
    }

    /**
     * {@inheritdoc}
     */
    public function setJob(JobInterface $job)
    {
        $this->job = $job;
    }

    /**
     * 
     * @return \Libcast\JobQueue\Job\JobInterface $job
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus($status)
    {
        if (!in_array($status, $this->getStatuses())) {
            throw new TaskException("The status '$status' does not exists.");
        }

        $this->status = $status;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        if (!$this->status) {
            $this->setStatus(self::STATUS_PENDING);
        }

        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function setProgress($percent)
    {
        if (!is_numeric($percent)) {
            throw new TaskException('Progress must be numeric.');
        }

        if ($percent < 0) {
            throw new TaskException('Progress must be bigger or equal to 0 (0%).');
        }

        if ($percent > 1) {
            throw new TaskException('Progress must be less or equal to 1 (100%).');
        }

        $this->progress = $percent;
    }

    /**
     * {@inheritdoc}
     */
    public function getProgress($human_readable = false, $cumulate_children = true, $percentage = true)
    {
        $progress = $this->progress;

        if (!$cumulate_children) {
            return $human_readable ? sprintf('%d%%', $progress * 100) : $progress;
        }

        foreach ($this->getChildren() as $child) {
            $progress += $child->getProgress(false, true, false);
        }

        if (!$percentage) {
            return $progress;
        }

        $task_count = $this->countChildren() + 1;
        $progress = ceil(($progress / $task_count) * 100) / 100;

        return $human_readable ? sprintf('%d%%', $progress * 100) : $progress;
    }

    /**
     * 
     * @param float $float
     * @param bool  $percentage 
     * @param bool  $absolute_value
     * @param int   $task_count 
     */
    protected function formatProgress($float, $human_readable, $absolute_value, $task_count = 1)
    {
        if ($absolute_value) {
            return $float;
        }

        $progress = ceil(($float / $task_count) * 100) / 100;

        if ($human_readable) {
            return ($progress * 100).'%';
        }

        return $progress;
    }

    /**
     * 
     * @param string $string A valid date format (Eg. '2013-11-30 20:30:50')
     * @throws \Libcast\JobQueue\Exception\TaskException
     */
    protected function setCreatedAt($string = null)
    {
        try {
            $date = new \DateTime($string);
            $this->created_at = $date->getTimestamp();
        } catch (\Exception $e) {
            throw new TaskException("Impossible to set '$string' as date of creation ({$e->getMessage()}).");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt($human_readable = true)
    {
        if (!$this->created_at) {
            $this->setCreatedAt();
        }

        return $human_readable ? date('Y-m-d H:i:s', $this->created_at) : (int) $this->created_at;
    }

    /**
     * {@inheritdoc}
     */
    public function setScheduledAt($string = null)
    {
        try {
            $date = new \DateTime($string);
            $this->scheduled_at = $date->getTimestamp();
        } catch (\Exception $e) {
            throw new TaskException("Impossible to set '$string' as schedule date ({$e->getMessage()}).");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getScheduledAt($human_readable = true)
    {
        if (!$this->scheduled_at) {
            return null;
        }

        return $human_readable ? date('Y-m-d H:i:s', $this->scheduled_at) : (int) $this->scheduled_at;
    }

    /**
     * 
     * @param array $options
     */
    protected function setOptions($options)
    {
        if ($job = $this->getJob()) {
            $options = array_merge($job->getOptions(), $options);
        }

        $this->options = (array) $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name)
    {
        if (!isset($this->options[$name])) {
            throw new TaskException("The option '$name' does not exists.");
        }

        return $this->options[$name];
    }

    /**
     * 
     * @param array $parameters
     */
    protected function setParameters($parameters)
    {
        if ($job = $this->getJob()) {
            $parameters = array_merge($job->getParameters(), $parameters);
        }

        $this->parameters = (array) $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter($name)
    {
        if (!isset($this->parameters[$name])) {
            throw new TaskException("The parameter '$name' does not exists.");
        }

        return $this->parameters[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function setNotification(Notification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * {@inheritdoc}
     */
    public function getNotification()
    {
        return $this->notification;
    }

    /**
     * {@inheritdoc}
     */
    public function addChild(TaskInterface $task)
    {
        if ($this->hasChild($task)) {
            return $this->updateChild($task);
        }

        if ($this->children_count)
        {
            $this->children_count++;
        }

        $task->setParentId($this->getId());

        $this->children[$task->getTag()] = $task;
    }

    /**
     * {@inheritdoc}
     */
    public function updateChild(TaskInterface $task)
    {
        if (!$this->hasChild($task)) {
            return $this->addChild($task);
        }

        $this->children[$task->getTag()] = $task;
    }

    /**
     * {@inheritdoc}
     */
    public function removeChild(TaskInterface $task)
    {
        if ($this->hasChild($task)) {
            if ($this->children_count)
            {
                $this->children_count--;
            }

            unset($this->children[$task->getTag()]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * {@inheritdoc}
     */
    public function getChild($tag)
    {
        if (!$this->hasChild($tag)) {
            throw new TaskException("Child with tag '{$task->getTag()}' does not exists.");
        }

        return $this->children[$tag];
    }

    /**
     * {@inheritdoc}
     */
    public function hasChild($tag)
    {
        if ($tag instanceof Task) {
            $tag = $tag->getTag();
        }

        return isset($this->children[$tag]);
    }

    /**
     * {@inheritdoc}
     */
    public function countChildren()
    {
        $count = count($this->getChildren());

        foreach ($this->getChildren() as $child) {
            /* @var $child Task */
            $count += $child->countChildren();
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public static function jsonImport($json)
    {
        if (!$data = json_decode($json, true)) {
            return null;
        }

        $notification = isset($data['notification']) && $data['notification'] ? 
                unserialize($data['notification']) : 
                null;

        $task = new Task(new $data['job'], 
                $data['options'], 
                $data['parameters'], 
                $notification instanceof Notification ? $notification : null);

        $task->setId($data['id']);
        $task->setTag($data['tag']);
        $task->setParentId($data['parent_id']);
        $task->setStatus($data['status']);
        $task->setProgress($data['progress']);
        $task->setCreatedAt(date('Y-m-d H:i:s', $data['created_at']));
        $task->setScheduledAt(date('Y-m-d H:i:s', $data['scheduled_at']));

        foreach ($data['children'] as $child) {
            $task->addChild(self::jsonImport($child));
        }

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonExport()
    {
        $array = array(
            'id'             => $this->getId(),
            'tag'            => $this->getTag(),
            'parent_id'      => $this->getParentId(),
            'job'            => $this->getJob()->getClassName(),
            'status'         => $this->getStatus(),
            'progress'       => $this->getProgress(false, false),
            'created_at'     => $this->getCreatedAt(false),
            'scheduled_at'   => $this->getScheduledAt(false),
            'options'        => $this->getOptions(),
            'parameters'     => $this->getParameters(),
            'notification'   => serialize($this->getNotification()),
            'children'       => array(),
        );

        foreach ($this->getChildren() as $child) {
            $array['children'][] = $child->jsonExport();
        }

        return json_encode($array);
    }

    public function __toString()
    {
        if ($this->getId()) {
            return (string) $this->getId();
        }

        return (string) $this->getTag();
    }
}