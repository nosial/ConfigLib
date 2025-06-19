<?php

    namespace ConfigLib;

    use Exception;
    use LogLib2\Logger;
    use RuntimeException;
    use Symfony\Component\Filesystem\Filesystem;
    use Symfony\Component\Yaml\Yaml;

    class Configuration
    {
        /**
         * The logger of the class
         * @var Logger
         */
        private Logger $logger;

        /**
         * The name of the configuration
         * @var string|array
         */
        private string|array $name;

        /**
         * The path to the configuration file
         * @var string|null
         */
        private ?string $path;

        /**
         * The configuration data
         * @var array
         */
        private array $configuration;

        /**
         * Indicates if the current instance is modified
         * @var bool
         */
        private bool $modified;

        /**
         * Public Constructor
         *
         * @param string $name The name of the configuration (e.g. "MyApp" or "net.example.myapp")
         * @param string|null $path The directory where the configuration file will be stored
         */
        public function __construct(string $name='default', ?string $path=null)
        {
            $this->logger = new Logger('net.nosial.configlib');

            // Sanitize $name for a file path
            $name = strtolower($name);
            $name = str_replace(array('/', '\\', '.'), '_', $name);
            $env = getenv(sprintf("CONFIGLIB_%s", strtoupper($name)));
            $this->path = null;

            if($env !== false)
            {
                if(file_exists($env))
                {
                    $this->path = $env;
                }
                else
                {
                    $this->logger->warning(sprintf('Environment variable "%s" points to a non-existent file, resorting to default/builtin configuration', $env));
                }
            }

            if($path !== null)
            {
                if(!is_dir(dirname($path)))
                {
                    throw new RuntimeException(sprintf('Directory "%s" does not exist', dirname($path)));
                }

                if(!is_writable(dirname($path)))
                {
                    throw new RuntimeException(sprintf('Directory "%s" is not writable', dirname($path)));
                }

                $this->path = $path;
            }

            if ($this->path === null)
            {
                $filePath = $name . '.conf';

                // If the CONFIGLIB_PATH environment variable is set, use it as the configuration path
                if(getenv('CONFIGLIB_PATH'))
                {
                    $configDir = getenv('CONFIGLIB_PATH');
                }
                else
                {
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                    {
                        $configDir = getenv('APPDATA') ?: getenv('LOCALAPPDATA');

                        if (!$configDir)
                        {
                            // Fallback to system temporary directory
                            $configDir = sys_get_temp_dir();
                        }

                        $configDir .= DIRECTORY_SEPARATOR . 'ConfigLib';
                    }
                    else
                    {
                        $homeDir = getenv('HOME') ?: '';
                        $configDirs = [];

                        if ($homeDir)
                        {
                            $configDirs[] = $homeDir . DIRECTORY_SEPARATOR . '.configlib';
                            $configDirs[] = $homeDir . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'configlib';
                        }

                        $configDirs[] = '/etc/configlib';
                        $configDirs[] = '/var/lib/configlib';

                        $configDir = null;

                        // Iterate over the list of directories and select the first one that can be created or written to
                        foreach ($configDirs as $dir)
                        {
                            if (file_exists($dir) && is_writable($dir))
                            {
                                $configDir = $dir;
                                break;
                            }
                            elseif (!file_exists($dir) && mkdir($dir, 0755, true))
                            {
                                $configDir = $dir;
                                break;
                            }
                        }

                        if (!$configDir)
                        {
                            $this->logger->warning(sprintf('Unable to find a proper directory to store configuration paths in, using temporary directory instead: %s', sys_get_temp_dir()));
                            $configDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'configlib';
                        }
                    }
                }

                // Ensure the directory exists
                if (!file_exists($configDir))
                {
                    if (!mkdir($configDir, 0755, true) && !is_dir($configDir))
                    {
                        throw new RuntimeException(sprintf('Directory "%s" was not created', $configDir));
                    }
                }

                $this->path = $configDir . DIRECTORY_SEPARATOR . $filePath;
            }

            // Set the name
            $this->name = $name;

            // Default Configuration
            $this->modified = false;

            if(file_exists($this->path))
            {
                try
                {
                    $this->load(true);
                }
                catch(Exception $e)
                {
                    $this->logger->error(sprintf('Unable to load configuration "%s", %s', $this->name, $e->getMessage()), $e);
                    throw new RuntimeException(sprintf('Unable to load configuration "%s"', $this->name), $e->getCode(), $e);
                }
            }
            else
            {
                $this->configuration = [];
            }
        }

        /**
         * Validates a key syntax (e.g. "key1.key2.key3")
         *
         * @param string $input The key to validate
         * @return bool True if the key is valid, false otherwise
         */
        private static function validateKey(string $input): bool
        {
            if (preg_match('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*$/', $input))
            {
                return true;
            }

            return false;
        }

        /**
         * Returns a value from the configuration
         *
         * @param string $key The key to retrieve (e.g. "key1.key2.key3")
         * @param mixed|null $default The default value to return if the key is not found
         * @return mixed The value of the key or the default value
         * @noinspection PhpUnused
         */
        public function get(string $key, mixed $default = null): mixed
        {
            if (!self::validateKey($key))
            {
                return $default;
            }

            $path = explode('.', $key);
            $current = $this->configuration;

            foreach ($path as $key_value)
            {
                if (is_array($current) && array_key_exists($key_value, $current))
                {
                    $current = $current[$key_value];
                }
                else
                {
                    return $default;
                }
            }

            // Return the value at the end of the path, or the default if the value is null
            return $current ?? $default;
        }

        /**
         * Sets a value in the configuration
         *
         * @param string $key The key to set (e.g. "key1.key2.key3")
         * @param mixed $value The value to set
         * @param bool $create If true, the key will be created if it does not exist
         * @return bool True if the value was set, false otherwise
         */
        public function set(string $key, mixed $value, bool $create = false): bool
        {
            // Validate the provided key
            if (!self::validateKey($key))
            {
                return false;
            }

            $path = explode('.', $key);
            $current = &$this->configuration;

            foreach ($path as $keyPart)
            {
                if (!is_array($current))
                {
                    $current = [];
                }

                if (!array_key_exists($keyPart, $current))
                {
                    if ($create)
                    {
                        $current[$keyPart] = [];
                    }
                    else
                    {
                        return false;
                    }
                }

                $current = &$current[$keyPart];
            }

            $current = $value;
            $this->modified = true;
            return true;
        }

        /**
         * Checks if a configuration key exists
         *
         * @param string $key The key to check (e.g. "key1.key2.key3")
         * @return bool True if the key exists, false otherwise
         */
        public function exists(string $key): bool
        {
            // Validate the provided key
            if (!self::validateKey($key))
            {
                return false;
            }

            $path = explode('.', $key);
            $current = $this->configuration;

            foreach ($path as $keyPart)
            {
                if (is_array($current) && array_key_exists($keyPart, $current))
                {
                    $current = $current[$keyPart];
                }
                else
                {
                    return false;
                }
            }

            return true;
        }

        /**
         * Sets the default value for a key if it does not exist
         *
         * @param string $key The key to set (e.g. "key1.key2.key3")
         * @param mixed $value The value to set
         * @param string|null $environmentVariable The environment variable to use as the default if it's set
         * @param bool $override Optional. If True, the environment variable if found will always override the value
         * @return bool True if the value was set, false otherwise
         */
        public function setDefault(string $key, mixed $value, ?string $environmentVariable=null, bool $override=true): bool
        {
            if($environmentVariable !== null)
            {
                $environmentVariable = getenv($environmentVariable);
            }
            else
            {
                $environmentVariable = false;
            }

            if($override && $environmentVariable !== false || strlen($environmentVariable) > 0)
            {
                $this->set($key, $environmentVariable, true);
                return true;
            }

            if($this->exists($key))
            {
                return false;
            }

            return $this->set($key, $value, true);
        }


        /**
         * Clears the current configuration data
         *
         * @return void
         */
        public function clear(): void
        {
            $this->configuration = [];
        }

        /**
         * Saves the Configuration File to the disk
         *
         * @return void
         */
        public function save(): void
        {
            if (!$this->modified)
            {
                return;
            }

            try
            {
                $fs = new Filesystem();

                $fs->dumpFile($this->path, FileFormat::JSON_PRETTY->serialize($this->configuration));
                $fs->chmod($this->path, 0777);
            }
            catch (Exception $e)
            {
                throw new RuntimeException('Unable to write configuration file', $e->getCode(), $e);
            }

            $this->modified = false;
            $this->logger->debug(sprintf('Configuration "%s" saved', $this->name));
        }

        /**
         * Loads the Configuration File from the disk
         *
         * @param bool $force If true, the configuration will be reloaded even if it was not modified
         * @return void
         */
        public function load(bool $force=false): void
        {
            if (!$force && !$this->modified)
            {
                return;
            }

            $fs = new Filesystem();
            if (!$fs->exists($this->path))
            {
                return;
            }

            try
            {
                $this->configuration = FileFormat::fromFile($this->path);
            }
            catch (Exception $e)
            {
                throw new RuntimeException('Unable to read configuration file', $e->getCode(), $e);
            }

            $this->modified = false;
            $this->logger->debug('Loaded configuration file: ' . $this->path);
        }

        /**
         * Returns the name of the configuration
         *
         * @return string The name of the configuration
         * @noinspection PhpUnused
         */
        public function getName(): string
        {
            return $this->name;
        }

        /**
         * Returns the path of the configuration file on disk
         *
         * @return string The path of the configuration file
         */
        public function getPath(): string
        {
            return $this->path;
        }

        /**
         * Returns the configuration
         *
         * @return array The configuration
         * @noinspection PhpUnused
         */
        public function getConfiguration(): array
        {
            return $this->configuration;
        }

        /**
         * Returns a formatted yaml string of the current configuration
         *
         * @return string The configuration in YAML format
         */
        public function toYaml(): string
        {
            return Yaml::dump($this->configuration, 4);
        }

        /**
         * Public Destructor
         */
        public function __destruct()
        {
            if($this->modified)
            {
                try
                {
                    $this->save();
                }
                catch(Exception $e)
                {
                    $this->logger->error(sprintf('Unable to save configuration "%s" to disk, %s', $this->name, $e->getMessage()), $e);
                }
            }
        }

        /**
         * Imports a YAML file into the configuration
         *
         * @param string $filePath The path to the configuration file
         * @return void
         */
        public function import(string $filePath): void
        {
            $fs = new Filesystem();
            if(!$fs->exists($filePath))
            {
                throw new RuntimeException(sprintf('Unable to import configuration file "%s", file does not exist', $filePath));
            }

            $this->configuration = array_replace_recursive($this->configuration, FileFormat::fromFile($filePath));
            $this->modified = true;
        }

        /**
         * Exports the configuration to a YAML file
         *
         * @param string $filePath The path to export the configuration to
         * @param FileFormat $fileFormat The file format to export as
         * @param bool $appendExtension Optional. If True, the appropriate file extension will be appended to the $filePath
         * @return void
         */
        public function export(string $filePath, FileFormat $fileFormat=FileFormat::YAML, bool $appendExtension=true): void
        {
            $fileFormat->toFile($this->configuration, $filePath, $appendExtension);
            chmod($filePath, 0777);
        }
    }