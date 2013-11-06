<?php





namespace Libcast\JobQueue;

class JobQueue
{
  const VERSION = "0.3-dev";

  public function __construct(array $config = array())
  {
    $this->config = $config;
  }
}
