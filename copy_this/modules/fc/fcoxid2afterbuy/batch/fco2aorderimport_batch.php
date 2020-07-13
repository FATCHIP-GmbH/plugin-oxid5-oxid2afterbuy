<?php

require_once 'fco2abootstrap.php';

/**
 * Start the job
 */
$oJob = oxNew('fco2aorderimport');
$oJob->execute();
