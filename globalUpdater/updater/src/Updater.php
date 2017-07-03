<?php


use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use Monolog\Logger;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\SplFileInfo as FinderSplFileInfo;

/**
 * Class Updater
 */
class Updater
{

    /**
     * @var string
     */
    const BACKUP_FOLDER = '/var/www/backup';
    /**
     * @var string
     */
    const MODELS_FOLDER = '/var/www/models';
    /**
     * @var array
     */
    const MODELS = [
        'oldFormModel' => [
            'branch' => 'updateOld',
            'suffix' => '/old_form_maxibooking',
            'excludes' => [
                'src/MBH/Bundle/OnlineBundle/Controller/ApiController.php',
                'src/MBH/Bundle/OnlineBundle/Resources/views/Api',
            ]
        ]
        ,
        'newFormModel' => [
            'branch' => 'updateNew',
            'suffix' => '/new_form_maxibooking',
            'excludes' => []
        ],
    ];

    /**
     * @var string
     */
    const REPO = 'git@bitbucket.org:MaxiBookingTeam/maxibooking-hotel.git';
    /**
     * @var string
     */
    const CLIENTS_FOLDER = '/var/www/mbh';
    /**
     * @var string
     */
    const PARAMETERS_FILE_NAME = 'parameters.yml';
    /**
     * @var string
     */
    const PARAMETERS_DIST_FILE_NAME = 'parameters.yml.dist';
    /**
     * @var string
     */
    const CONFIG_DIR = '/app/config';

    /**
     * @var array
     */
    const TAR_EXCLUDED_FOLDERS = [
        'var',
        'vendor',
        'tests',
        '.git',
        '.idea',
    ];
    /**
     * @var array
     */
    const RSYNC_EXCLUDED_FOLDERS = [
        '.git',
        'docker',
        'protectedUpload',
        'scripts',
        'tests',
        'var',
        'web/upload',
        'README.md',
        'phpunit.xml/dist',
        'app/config/parameters.yml'
    ];
    /**
     * @var array
     */
    const MONGO = [
        'host' => 'localhost',
        'port' => 27017,
    ];
    /**
     * @var string
     */
    private $modelsFolder;

    /**
     * @var string
     */
    private $clientsFolder;

    /**
     * @var Filesystem
     */

    private $fs;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var array
     */
    private $config;

    /**
     * Updater constructor.
     */
    public function __construct(Logger $logger, array $config)
    {
        $this->fs = new Filesystem();
        $this->logger = $logger;
        $this->modelsFolder = isset($config['models_folder']) ? $config['models_folder'] : self::MODELS_FOLDER;
        $this->clientsFolder = (isset($config['clients_folder'])) ? $config['clients_folder'] : self::CLIENTS_FOLDER;
        $this->config = $config;
    }

    /**
     *
     */
    public function update()
    {

        $this->modelPreparer();
        $clientsDirs = $this->getClientsFolders();
        $updated = [];
        if (count($clientsDirs)) {
            /** @var FinderSplFileInfo $clientDir */
            foreach ($clientsDirs as $clientDir) {
                if (isset($this->config['onlyClients']) && !in_array(
                        $clientDir->getFileName(),
                        $this->config['onlyClients']
                    )
                ) {
                    continue;
                }
                try {
                    if (!$this->config || (isset($this->config['skip_backup']) && !$this->config['skip_backup'])) {
                        $backupFolder = (isset($this->config['backup_folder'])) ? $this->config['backup_folder'] : self::BACKUP_FOLDER;
                        $this->backUp($clientDir, $backupFolder);
                    }
                    $this->clientUpdate($clientDir);
                    $updated[] = $clientDir->getFilename();
                } catch (UpdaterException $e) {
                    $message =  'Client error '.$clientDir->getFileName().' '.$e->getMessage();
                    $this->logger->addCritical($message);
                }

            }
            try {
                $this->afterUpdate($clientsDirs, $updated);
            } catch (UpdaterException $e) {
                $this->logger->addCritical($e->getMessage());
            }

        }

    }


    /**
     *
     */
    private function modelPreparer()
    {
        foreach (self::MODELS as $model) {
            $this->logger->addInfo('Start Model prepare for ', $model);
            /** @var string $folder */
            $folder = $this->modelsFolder.$model['suffix'];
            /** @var string $branch */
            $branch = $model['branch'];
            $this->checkAndMkDir($folder);
            $this->cloneModel($folder, $branch);
            $this->vendorInstall($folder);
            $this->bowerInstall($folder);
        }
    }

    /**
     * @return Finder
     */
    private function getClientsFolders(): Finder
    {
        $finder = new Finder();

        return $finder->depth(0)->in($this->clientsFolder)->directories();
    }

    /**
     * @param string $folder
     */
    private function bowerInstall(string $folder)
    {
        $command = 'bower install --allow-root';
        $this->runProcess($command, $folder);
    }

    /**
     * @param Finder $clientsDirs
     * @param array $updated
     * @throws UpdaterException
     */
    private function afterUpdate(Finder $clientsDirs, array $updated)
    {
        return;
        //Тут волшебство. Выполнение ребилда супервизора виснет, но продолжается если убить демон.
        foreach ($clientsDirs as $clientDir) {
            if (in_array($clientDir->getFilename(), $updated)) {
                $this->supervisorRebuild($clientDir);
            }
        }
    }

    /**
     * @param array $commands
     * @param string|null $pwd
     */
    private function execBatchCommand(array $commands, string $pwd = null)
    {
        foreach ($commands as $command) {
            $this->runProcess($command, $pwd);
        }
    }

//    public function rebuildAllSupervisors()
//    {
//        $clientsDirs = $this->getClientsFolders();
//
//    }

    /**
     * @param $clientDir
     */
    private function supervisorRebuild($clientDir)
    {
        $command = $clientDir. '/bin/console rabbitmq-supervisor:rebuild --env=prod';
        $this->logger->addInfo('Start exec '.$command);
        exec($command, $output, $return);

    }



    /**
     * @param FinderSplFileInfo $clientDir
     */
    private function clientUpdate(FinderSplFileInfo $clientDir): void
    {
        $model = $this->determineModel($clientDir);
        $suffix = $model['suffix'];
        $modelFolder = $this->modelsFolder.$suffix;
        $excludes = array_merge(self::RSYNC_EXCLUDED_FOLDERS, $model['excludes']);
        $this->syncParameters($clientDir, $modelFolder);
        $this->syncCodeBase($clientDir, $modelFolder, $excludes);
        $this->clientPrepare($clientDir);
    }

    /**
     * @param string $dir
     * @throws UpdaterException
     */
    private function checkAndMkDir(string $dir)
    {
        if (!$this->fs->exists($dir)) {
            try {
                $this->fs->mkdir($dir);
                $message = "folder $dir created";
                $this->logger->addInfo($message);
            } catch (IOException $e) {
                $message = "An error occurred while creating your directory at ".$e->getPath();
                $this->logger->addCritical($message.' '.$e->getMessage());
                throw new UpdaterException($message);
            }

        }
    }

    /**
     * @param FinderSplFileInfo $clientDir
     * @return string
     * @throws UpdaterException
     */
    private function determineModel(FinderSplFileInfo $clientDir): array
    {
        $parameters = $this->getParameters($clientDir);
        $mongoUrl = $parameters['parameters']['mongodb_url'];
        try {
            $manager = new Manager($mongoUrl);
            $filter = ['resultsUrl' => ['$exists' => true]];
            $query = new Query($filter);
            $result = $manager->executeQuery('mbh_berloga.FormConfig', $query)->toArray();
        } catch (ConnectionException $e) {
            throw new UpdaterException('Нет связи с базой данных для определения версии');
        }

        $model = $result ? self::MODELS['newFormModel'] : self::MODELS['oldFormModel'];

        return $model;
    }

    /**
     * @param FinderSplFileInfo $clientDir
     */
    private function clientPrepare(FinderSplFileInfo $clientDir)
    {
        $commands = [
            'rm -rf var/cache',
            'bin/console cache:clear --no-warmup --env=prod',
            'bin/console cache:warmup --env=prod',
            'bin/console doctrine:mongodb:cache:clear-metadata --env=prod',
            'bin/console doctrine:mongodb:generate:hydrators --env=prod',
            'bin/console doctrine:mongodb:generate:proxies --env=prod',
            'bin/console mbh:package:accommodation_migrate --env=prod',
            'bin/console assets:install --symlink --env=prod',
            'bin/console fos:js-routing:dump --env=prod',
            'bin/console bazinga:js-translation:dump --env=prod',
            'bin/console assetic:dump --env=prod',
        ];

        $this->execBatchCommand($commands, $clientDir);
    }

    /**
     * @param FinderSplFileInfo $clientDir
     * @param string $modelFolder
     * @throws UpdaterException
     */
    private function syncParameters(FinderSplFileInfo $clientDir, string $modelFolder): void
    {
        $parameters = $this->getParameters($clientDir);
        $parametersDist = $this->getParametersDist($modelFolder);
        if (!isset($parameters['parameters']['rabbitmq_host'])) {
            $rabbitMQHost = $this->config['rabbit_host']??null;
            if (!$rabbitMQHost) {
                throw new UpdaterException('Обязательно нужно указать в конфигурации rabbitmq host');
            }
            $parameters['parameters']['rabbitmq_host'] = $rabbitMQHost;
        }

        if (!isset($parameters['parameters']['mbh_redis'])) {
            $redisHost = $this->config['redis_host']??null;
            if (!$redisHost) {
                throw new UpdaterException('Обязательно нужно указать в конфигурации rabbitmq host');
            }
            $parameters['parameters']['mbh_redis'] = $redisHost;
        }
        $syncedParameters = array_replace_recursive($parametersDist, $parameters);
        $yaml = Yaml::dump($syncedParameters, 5);
        $filename = $clientDir.self::CONFIG_DIR.'/'.self::PARAMETERS_FILE_NAME;
        try {
            $this->fs->dumpFile($filename, $yaml);
        } catch (IOException $e) {
            throw new UpdaterException('Невозможно сохранить файл для клиента '.$clientDir->getFilename());
        }


    }

    /**
     * @param FinderSplFileInfo $clientDir
     * @param string $modelFolder
     */
    private function syncCodeBase(FinderSplFileInfo $clientDir, string $modelFolder, array $excludes = [])
    {
        $from = $modelFolder.'/';
        $to = $clientDir;
        $exclude = $this->generateExcludes($excludes);
        $command = sprintf('rsync -avz --delete %s %s %s', $exclude, $from, $to);
        $this->runProcess($command);
    }

    /**
     * @param FinderSplFileInfo $clientDir
     * @param string $backupFolder
     */
    private function backUp(FinderSplFileInfo $clientDir, string $backupFolder): void
    {
        $parameters = $this->getParameters($clientDir);
        $this->checkAndMkDir($backupFolder);
        if (empty($parameters)) {
            return;
        }
        $client = $clientDir->getFilename();
        $backupDir = $backupFolder.'/'.$client;

        $this->backupMongo($parameters, $backupDir);
        $this->backupFiles($clientDir, $backupDir);
    }

    /**
     * @param array $parameters
     * @param string $backupDir
     * @throws UpdaterException
     */
    private function backupMongo(array $parameters, string $backupDir): void
    {
        $mongoBackupDir = $backupDir.'/mongo';
        $this->checkAndMkDir($mongoBackupDir);
        $database = $parameters['parameters']['mongodb_database']??null;
        if (!$database) {
            throw new UpdaterException('В параметр YML не существует имя базы данных');
        }
        $time = (new DateTime())->format('dmHi');
        $command = sprintf(
            'mongodump --host %s --port %s -d %s -o %s',
            self::MONGO['host'],
            self::MONGO['port'],
            $database,
            $database.$time
        );
        $this->runProcess($command, $mongoBackupDir);

    }


    /**
     * @param FinderSplFileInfo $clientDir
     * @param string $backupDir
     */
    private function backupFiles(FinderSplFileInfo $clientDir, string $backupDir)
    {
        $this->checkAndMkDir($backupDir.'/files');
        $exclude = $this->generateExcludes(self::TAR_EXCLUDED_FOLDERS);
        $backupName = $clientDir->getFilename().(new DateTime())->format('dmHi').'.tar.gz';
        $fileBackupDir = $backupDir.'/files';
        $command = sprintf("tar -czvf %s -C %s %s %s", $backupName, $clientDir->getRealPath(), '.', $exclude);
        $this->runProcess($command, $fileBackupDir);
    }


    /**
     * @param array $excludeList
     * @return string
     */
    private function generateExcludes(array $excludeList): string
    {
        return implode(
            " ",
            array_map(
                function ($value) {
                    return "--exclude=\"$value\"";
                },
                $excludeList
            )
        );
    }

    /**
     * @param string $modelFolder
     * @param string $branch
     */
    private function cloneModel(string $modelFolder, string $branch)
    {
        if ($this->fs->exists($modelFolder) && !$this->fs->exists($modelFolder."/.git")) {
            $this->gitClone($modelFolder, self::REPO, $branch);
        } else {
            $this->gitPull($modelFolder);
        }
    }

    /**
     * @param string $folder
     * @param string $repo
     * @param string $branch
     */
    private function gitClone(string $folder, string $repo, string $branch): void
    {
        $this->logger->addInfo("git clone for $branch");
        $command = sprintf("git clone -b %s --single-branch %s . --progress --verbose", $branch, $repo);
        $this->runProcess($command, $folder);
    }


    /**
     * @param string $folder
     */
    private function gitPull(string $folder): void
    {
        $this->logger->addInfo('do git pull');
        $command = sprintf("git pull");
        $this->runProcess($command, $folder);
    }

    /**
     * @param string $command
     * @param string|null $pwd
     * @throws UpdaterException
     */
    private function runProcess(string $command, string $pwd = null)
    {
        $process = new Process($command, $pwd, null, null, null);
        $this->logger->addInfo("Start $command");
        try {
            $process->run(
                function ($type, $buffer) {
                    if (Process::ERR === $type) {
                        echo 'ERR > '.$buffer;
                    } else {
                        echo 'OUT > '.$buffer;
                    }
                }
            );
            $this->logger->addInfo("$command Done");
        } catch (ProcessFailedException $e) {
            $this->logger->addCritical("Process Error! {$e->getMessage()}");
            throw new UpdaterException($e->getMessage());
        }

    }

    /**
     * @param string $command
     * @param string|null $pwd
     */
    private function startProcess(string $command, string $pwd = null)
    {
        $process = new Process($command, $pwd, null, null, null);
        $this->logger->addInfo("Start $command");
        $process->start();
    }

    /**
     * @param string $folder
     */
    private function vendorInstall(string $folder)
    {
        $command = 'composer install';
        $this->runProcess($command, $folder);
    }

    /**
     * @param string $clientDir
     * @return array
     */
    private function getParameters(string $clientDir): array
    {
        return $this->getParametersAsArray($clientDir, self::PARAMETERS_FILE_NAME);
    }

    /**
     * @param string $modelFolder
     * @return array
     */
    private function getParametersDist(string $modelFolder): array
    {

        return $this->getParametersAsArray($modelFolder, self::PARAMETERS_DIST_FILE_NAME);
    }

    /**
     * @param string $dir
     * @param string $fileName
     * @return array
     */
    private function getParametersAsArray(string $dir, string $fileName): array
    {
        $result = [];
        $configDir = $dir.self::CONFIG_DIR;
        $finder = new Finder();
        if ($this->fs->exists($configDir.'/'.$fileName)) {
            $files = $finder->files()->name($fileName)->in($configDir);
            foreach ($files as $file) {
                /** @var FinderSplFileInfo $file */
                $parsedFile = Yaml::parse($file->getContents());
                if ($parsedFile && is_array($parsedFile)) {
                    $result = $parsedFile;
                }
                break;
            }
        }

        return $result;
    }

    /**
     * @throws UpdaterException
     */
    public function rabbitReset()
    {
        $commands = [
            'rabbitmqctl stop_app',
            'rabbitmqctl reset',
            'rabbitmqctl start_app'
        ];

        $this->execBatchCommand($commands);

        $clientsDirs = $this->getClientsFolders();
        foreach ($clientsDirs as $clientDir) {
            /** @var FinderSplFileInfo $clientDir */
            $parameters = $this->getParameters($clientDir);
            if (!$parameters) {

                throw new UpdaterException('Не достаточно данных для RabbitmQ');
            }
            $user = $parameters['parameters']['rabbitmq_user'];
            $password = $parameters['parameters']['rabbitmq_password'];
            $vhost = $parameters['parameters']['rabbitmq_vhost'];
            $this->rabbitMQSetup($user, $password, $vhost);
        }

    }

    /**
     * @param string $user
     * @param string $password
     * @param string $vhost
     */
    private function rabbitMQSetup(string $user, string $password, string $vhost)
    {
        $commands = [
            sprintf('rabbitmqctl add_vhost %s', $vhost),
            sprintf('rabbitmqctl add_user %s %s', $user, $password),
            sprintf('rabbitmqctl set_permissions -p %s %s ".*" ".*" ".*"', $vhost, $user),
        ];

        $this->execBatchCommand($commands);
    }

}