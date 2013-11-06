<?php

$job_files = glob(__DIR__ . '/../src/Libcast/JobQueue/Job/*Job.php')
    + glob('/home/co/apps/providers/vendor/libcast/encoding/src/Libcast/Encoding/Job/*Job.php');

// var_dump($job_files);die;

return new Libcast\JobQueue\JobQueue(array(
  "profiles"  => array(
    "dummy-stuff",
    "notsodummy"
  ),
  "queue"     => Libcast\JobQueue\Queue\QueueFactory::load(new Predis\Client("tcp://localhost:6379")),
  "job_files" => $job_files,
));

