<?php


use Monolog\Logger;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Yaml;

class ConfigChanger
{
    const CLIENTS_FOLDER = '/var/www/sandbox/clients';
//    const CLIENTS_FOLDER = '/var/www/mbh';

    const CLIENT_CONFIG_FILE_NAME = 'parameters.yml';

    /** @var  array */
    protected $options;

    /** @var  Logger */
    protected $logger;

    /** @var  Filesystem */
    protected $fs;

    /** @var  QuestionHelper */
    protected $questionHelper;

    protected $input;

    protected $output;

    public function __construct(Logger $logger, Input $input, Output $output)
    {
        $this->logger = $logger;
        $this->fs = new Filesystem();
        $this->input = $input;
        $this->output = $output;
    }


    public function configChange(array $options = []): void
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);



        $folders = $this->getClientsFolders();
        /** @var SplFileInfo $folder */
        foreach ($folders as $folder) {
            if (!$this->isClientUpdatable($folder->getFilename(), $this->options['clients_updatable'])) {
                continue;
            }
            $this->syncParameters($folder);
        }
    }


    private function syncParameters(SplFileInfo $dir)
    {
        $parameters = $this->getConfigFile($dir);

        if (!empty($parameters)) {
            /** @var SplFileInfo $file */
            $file = $parameters['file'];
            $oldParameters = $parameters['parsed_file'];
            $newParameters = $this->options['change_parameters'];

            $result = array_replace_recursive($oldParameters, $newParameters);

            $yaml = Yaml::dump($result, 5);
            $oldYaml = Yaml::dump($oldParameters,5);
            $newYaml = Yaml::dump($newParameters,5);

            $this->logger->addInfo('Получен файл '.$file->getRealPath());
            $this->logger->addInfo('Origin config ',$oldParameters);
            $this->logger->addInfo('new config ',$newParameters);
            $this->logger->addInfo('result config ',$result);

            $table = new Table($this->output);
            $table
                ->setHeaders(['oldConfig', 'newConfig', 'resultConfig'])
                ->setRows(
                    [
                        [$oldYaml, $newYaml, $yaml]
                    ]
                );
            $table->render();

            $helper = new QuestionHelper();
            $message = 'Вы собираетесь изменить конфиг для клиента '.$dir." y/N \n";
            $question = new ConfirmationQuestion(
                $message,
                false
            );
            if ($helper->ask($this->input, $this->output, $question)) {
                try {
                    $this->fs->dumpFile($file->getRealPath(), $yaml);
                    $message = 'File was dumped';
                    $this->logger->addInfo($message);
                } catch (IOException $exception) {
                    $message = 'Error of file dumping';
                    $this->logger->addAlert($message);
                }

            } else {
                $message = 'File dump was canceled';
            }

            $this->output->writeln($message);

        }

    }


    /**
     * @param string $dir
     * @return array
     * @internal param string $fileName
     */
    private function getConfigFile(string $dir): array
    {
        $result = [];
        $finder = new Finder();
        $configDir = ltrim($this->options['client_config_file_dir'], '/');
        $files = $finder->files()->in($dir.'/'.$this->options['client_config_file_dir'])->name($this->options['client_config_file_name']);
        foreach ($files as $file) {
            $parsedFile = Yaml::parse($file->getContents());
            if ($parsedFile && is_array($parsedFile)) {
                $result = [
                    'parsed_file' => $parsedFile,
                    'file' => $file
                    ];
            }
            break;
        }

        return $result;
    }

    private function isClientUpdatable(string $clientName, array $clients): bool
    {
        if (!empty($clients)) {
            return in_array($clientName, $clients);
        }

        return true;
    }


    private function getClientsFolders(): array
    {
        $finder = new Finder();
        $foldersFinder = $finder->depth(0)->in($this->options['clients_folder'])->directories();
        /** @var SplFileInfo[] $folders */
        $folders = iterator_to_array($foldersFinder->getIterator());

        return $folders;
    }


    public function configureOptions(OptionsResolver $resolver)
    {

        $resolver->setDefaults(
            [
                'clients_folder' => self::CLIENTS_FOLDER,
                'clients_updatable' => [],
                'client_config_file_name' => self::CLIENT_CONFIG_FILE_NAME,
                'client_config_file_dir' => '',
                'change_parameters' => [
                    'parameters' => [
                        'mailer_transport' => 'ssmm',
                        'bubu' => 'mumu'
                    ]
                ]
            ]
        );
    }

}