<?php

namespace PetrKnap\Php\MigrationTool;

use PetrKnap\Php\MigrationTool\Exception\MismatchException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract migration tool
 *
 * @author   Petr Knap <dev@petrknap.cz>
 * @since    2016-06-22
 * @license  https://github.com/petrknap/php-migrationtool/blob/master/LICENSE MIT
 */
abstract class AbstractMigrationTool implements MigrationToolInterface, LoggerAwareInterface
{
    const MESSAGE__FOUND_UNSUPPORTED_FILE__PATH = 'Found unsupported file {path}';
    const MESSAGE__FOUND_MIGRATION_FILES__COUNT_PATH_PATTERN = 'Found {count} migration files in {path} matching {pattern}';
    const MESSAGE__MIGRATION_FILE_APPLIED__PATH = 'Migration file {path} applied';
    const MESSAGE__THERE_IS_NOTHING_MATCHING_PATTERN__PATH_PATTERN = 'In {path} is nothing matching {pattern}';
    const MESSAGE__THERE_IS_NOTHING_TO_MIGRATE__PATH_PATTERN = 'In {path} is nothing matching {pattern} to migrate';
    const MESSAGE__DETECTED_GAPE_BEFORE_MIGRATION__ID = 'Detected gape before migration {id}';
    const MESSAGE__DONE = 'Database is now up-to-date';

    /**
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $filePattern;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param string $directory
     * @param string $filePattern
     */
    public function __construct($directory, $filePattern = '/^.*$/i')
    {
        $this->directory = $directory;
        $this->filePattern = $filePattern;
    }

    /**
     * Interpolates context values into the message placeholders for exceptions
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    protected function interpolate($message, array $context = [])
    {
        $replace = [];
        foreach ($context as $key => $val) {
            // check that the value can be casted to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if (null === $this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * @inheritdoc
     */
    public function migrate()
    {
        $migrationFiles = $this->getMigrationFiles();
        $migrationFilesToMigrate = [];
        foreach ($migrationFiles as $migrationId => $migrationFile) {
            if ($this->isMigrationApplied($migrationFile)) {
                if (!empty($migrationFilesToMigrate)) {
                    $context = [
                        'id' => $migrationId,
                    ];

                    $this->getLogger()->critical(
                        self::MESSAGE__DETECTED_GAPE_BEFORE_MIGRATION__ID,
                        $context
                    );

                    throw new MismatchException(
                        sprintf(
                            "%s\nFiles to migrate:\n\t%s",
                            $this->interpolate(
                                self::MESSAGE__DETECTED_GAPE_BEFORE_MIGRATION__ID,
                                $context
                            ),
                            implode("\n\t", $migrationFilesToMigrate)
                        )
                    );
                }
            } else {
                $migrationFilesToMigrate[] = $migrationFile;
            }
        }

        if (empty($migrationFilesToMigrate)) {
            $context = [
                'path' => $this->directory,
                'pattern' => $this->filePattern,
            ];

            $this->getLogger()->notice(
                self::MESSAGE__THERE_IS_NOTHING_TO_MIGRATE__PATH_PATTERN,
                $context
            );
        } else {
            foreach ($migrationFilesToMigrate as $migrationFile) {
                $this->applyMigrationFile($migrationFile);

                $this->getLogger()->info(
                    self::MESSAGE__MIGRATION_FILE_APPLIED__PATH,
                    [
                        'path' => $migrationFile,
                    ]
                );
            }
        }

        $this->getLogger()->info(
            self::MESSAGE__DONE
        );
    }

    /**
     * Returns list of paths to migration files
     *
     * @return string[]
     */
    protected function getMigrationFiles()
    {
        $directoryIterators = [];
        if (is_array($this->directory)) {
            foreach ($this->directory as $moduleName => $directory) {
                $directoryIterators[$moduleName] = new \DirectoryIterator($directory);
            }
        } else {
            $directoryIterators[''] = new \DirectoryIterator($this->directory);
        }
        $migrationFiles = [];
        foreach ($directoryIterators as $moduleName => $directoryIterator) {
            foreach ($directoryIterator as $fileInfo) {
                /** @var \SplFileInfo $fileInfo */
                if ($fileInfo->isFile()) {
                    if (preg_match($this->filePattern, $fileInfo->getRealPath())) {
                        $migrationFiles[$this->getMigrationId($fileInfo->getRealPath(), $moduleName)] = $fileInfo->getRealPath();
                    } else {
                        $context = [
                            'path' => $fileInfo->getRealPath(),
                        ];

                        $this->getLogger()->notice(
                            self::MESSAGE__FOUND_UNSUPPORTED_FILE__PATH,
                            $context
                        );
                    }
                }
            }
        }
        
        ksort($migrationFiles);

        if (empty($migrationFiles)) {
            $context = [
                'path' => $this->directory,
                'pattern' => $this->filePattern,
            ];

            $this->getLogger()->warning(
                self::MESSAGE__THERE_IS_NOTHING_MATCHING_PATTERN__PATH_PATTERN,
                $context
            );
        }

        $this->getLogger()->info(
            self::MESSAGE__FOUND_MIGRATION_FILES__COUNT_PATH_PATTERN,
            [
                'count' => count($migrationFiles),
                'path' => $this->directory,
                'pattern' => $this->filePattern,
            ]
        );

        return $migrationFiles;
    }

    /**
     * @param string $pathToMigrationFile
     * @return string
     */
    protected function getMigrationId($pathToMigrationFile, $moduleName = '') // back compatibility
    {
        $fileInfo = new \SplFileInfo($pathToMigrationFile);
        $basenameParts = explode(' ', $fileInfo->getBasename('.' . $fileInfo->getExtension()));
        return $basenameParts[0] . ($moduleName === '' ? '' : '.' . $moduleName);
    }

    /**
     * @param string $pathToMigrationFile
     * @return bool
     */
    abstract protected function isMigrationApplied($pathToMigrationFile);

    /**
     * @param $pathToMigrationFile
     * @return void
     */
    abstract protected function applyMigrationFile($pathToMigrationFile);
}
