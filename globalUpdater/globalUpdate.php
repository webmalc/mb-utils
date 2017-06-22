<?php
///Скрипт глобального обновления.

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

const CONFIG_NAME = 'updateConfig.yml';

require_once 'vendor/autoload.php';

$fs = new Filesystem();
$input = new ArgvInput();
$output = new ConsoleOutput();
$configFile = $input->getParameterOption(['--config']);
$action = $input->getFirstArgument();

if ($configFile && !$fs->exists($configFile)) {
    $output->writeln('Config file not found');
    exit(1);
}

$config = ($fs->exists($configFile)) ? Yaml::parse(file_get_contents($configFile)) : [];
$logFile = isset($config['log']) ? $config['log'] : '/tmp/update.log';
$logger = new Logger('updateLog');
$logger->pushHandler(new StreamHandler($logFile));

$updater = new Updater($logger, $config);

if ($action === 'update') {
    $updater->update();
    exit(0);
} elseif($action === 'rabbit_reset') {
    try {
        $updater->rabbitReset();
    } catch (UpdaterException $e) {
        $output->writeln($e->getMessage());
        exit(1);
    }

} else {
    $output->writeln('Use php updater.phar update --config=/absolute/path/config.yml');
    $output->writeln('Use php updater.phar rabbit_reset');
}


