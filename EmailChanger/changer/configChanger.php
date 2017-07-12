<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
const CONFIG_NAME = 'changerConfig.yml';

require_once(__DIR__.'/vendor/autoload.php');

$fs = new Filesystem();
$input = new ArgvInput();
$output = new ConsoleOutput();
$configFile = $input->getParameterOption(['--config']);
$action = $input->getFirstArgument();

if ($configFile && !$fs->exists($configFile)) {
    $output->writeln('Config file not found');
    exit(1);
}

$options = ($fs->exists($configFile)) ? Yaml::parse(file_get_contents($configFile)) : [];
$logFile = isset($config['log']) ? $config['log'] : '/tmp/config_changer.log';
$logger = new Logger('changerLog');
$logger->pushHandler(new StreamHandler($logFile));

$changer = new ConfigChanger($logger, $input, $output);

if ($action === 'change') {
    $changer->configChange($options);
} else {
    $output->writeln('Use php configChanger.phar change --config=/absolute/path/changerConfig.yml');
}


