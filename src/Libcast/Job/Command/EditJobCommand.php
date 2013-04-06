<?php

namespace Libcast\Job\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

use Libcast\Job\Command\JobCommand;
use Libcast\Job\Command\OutputTable;

class EditJobCommand extends JobCommand
{
  protected function configure()
  {
    $this->setName('edit:task')->
            setDescription('Edit a Task')->

            addArgument('id',             InputArgument::REQUIRED,     'Task Id')->

            addOption('parent-id',  'i',  InputOption::VALUE_OPTIONAL, 'Set parent Id (Eg. 123)',       null)->
            addOption('priority',   'p',  InputOption::VALUE_OPTIONAL, 'Set priority (1, 2, ...)',      null)->
            addOption('profile',    'f',  InputOption::VALUE_OPTIONAL, 'Set profile (eg. "high-cpu")',  null)->
            addOption('status',     's',  InputOption::VALUE_OPTIONAL, 'Set status (pending|waiting|running|success|failed|finished)', null);
    
    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $queue = $this->getQueue($input);
    
    $task = $queue->getTask($input->getArgument('id'));
    
    $update = false;
    
    if ($input->getOption('parent-id'))
    {
      $task->setParentId($input->getOption('parent-id'));
      $update = true;
    }
    
    if ($input->getOption('priority'))
    {
      $task->setOptions(array_merge($task->getOptions(), array(
          'priority' => $input->getOption('priority'),
      )));
      $update = true;
    }
    
    if ($input->getOption('profile'))
    {
      $task->setOptions(array_merge($task->getOptions(), array(
          'profile' => $input->getOption('profile'),
      )));
      $update = true;
    }
    
    if ($input->getOption('status'))
    {
      $task->setStatus($input->getOption('status'));
      $update = true;
    }
    
    if ($update)
    {
      $header = "Task '$task' has been updated.";
      $queue->update($task);
    }
    else
    {
      $header = "Nothing to update on Task '$task'.";
    }

    $table = new OutputTable;
    $table->addColumn('Key',    15, OutputTable::RIGHT);
    $table->addColumn('Value',  25, OutputTable::LEFT);
    
    $table->addRow(array(
        'Key'   => 'Id',
        'Value' => $task->getId(),
    ));
    $table->addRow(array(
        'Key'   => 'Parent Id',
        'Value' => $task->getParentId(),
    ));
    $table->addRow(array(
        'Key'   => 'Priority',
        'Value' => $task->getOption('priority'),
    ));
    $table->addRow(array(
        'Key'   => 'Profile',
        'Value' => $task->getOption('profile'),
    ));
    $table->addRow(array(
        'Key'   => 'Status',
        'Value' => $task->getStatus(),
    ));
    $table->addRow(array(
        'Key'   => 'Progress',
        'Value' => $task->getProgress(false),
    ));
    
    $this->addLine();
    $this->addLine($header);
    $this->addLine();
    $this->addLine($table->getTable());
    $this->addLine();
    
    $output->writeln($this->getLines());
  }
}