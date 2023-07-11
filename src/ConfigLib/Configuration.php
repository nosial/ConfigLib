<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace ConfigLib;

    use Exception;
    use LogLib\Log;
    use ncc\Runtime;
    use RuntimeException;
    use Symfony\Component\Filesystem\Filesystem;
    use Symfony\Component\Yaml\Yaml;

    class Configuration
    {
        /**
         * The name of the configuration
         *
         * @var string
         */
        private $name;

        /**
         * The path to the configuration file
         *
         * @var string
         */
        private $path;

        /**
         * The configuration data
         *
         * @var array
         */
        private $configuration;

        /**
         * Indicates if the current instance is modified
         *
         * @var bool
         */
        private $modified;

        /**
         * Public Constructor
         *
         * @param string $name The name of the configuration (e.g. "MyApp" or "net.example.myapp")
         */
        public function __construct(string $name='default')
        {
            // Sanitize $name for a file path
            $name = strtolower($name);
            $name = str_replace(array('/', '\\', '.'), '_', $name);

            if(!getenv(sprintf("CONFIGLIB_%s", strtoupper($name))))
            {
                $environment_config = sprintf('CONFIGLIB_%s', strtoupper($name));
                if(file_exists($environment_config))
                {
                    $this->path = $environment_config;
                }
                else
                {
                    Log::warning('net.nosial.configlib', sprintf('Environment variable "%s" points to a non-existent file, resorting to default/builtin configuration', $environment_config));
                }
            }

            if($this->path === null)
            {
                // Figure out the path to the configuration file
                try
                {
                    $this->path = Runtime::getDataPath('net.nosial.configlib') . DIRECTORY_SEPARATOR . $name . '.conf';
                }
                catch (Exception $e)
                {
                    throw new RuntimeException('Unable to load package "net.nosial.configlib"', $e);
                }
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
                    Log::error('net.nosial.configlib', sprintf('Unable to load configuration "%s", %s', $this->name, $e->getMessage()));
                    throw new RuntimeException(sprintf('Unable to load configuration "%s"', $this->name), $e);
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
         * @param string $input
         * @return bool
         */
        private static function validateKey(string $input): bool
        {
            $pattern = '/^([a-zA-Z]+\.?)+$/';

            if (preg_match($pattern, $input))
            {
                return true;
            }

            return false;
        }

        /**
         * Attempts to convert a string to the correct type (int, float, bool, string)
         *
         * @param $input
         * @return float|int|mixed|string
         * @noinspection PhpUnusedPrivateMethodInspection
         */
        private static function cast($input): mixed
        {
            if (is_numeric($input))
            {
                if(str_contains($input, '.'))
                {
                    return (float)$input;
                }

                if(ctype_digit($input))
                {
                    return (int)$input;
                }
            }
            elseif (in_array(strtolower($input), ['true', 'false']))
            {
                return filter_var($input, FILTER_VALIDATE_BOOLEAN);
            }

            return (string)$input;
        }

        /**
         * Returns a value from the configuration
         *
         * @param string $key The key to retrieve (e.g. "key1.key2.key3")
         * @param mixed|null $default The default value to return if the key is not found
         * @return mixed The value of the key or the default value
         * @noinspection PhpUnused
         */
        public function get(string $key, mixed $default=null): mixed
        {
            if(!self::validateKey($key))
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

            // Return the value at the end of the path
            return $current;
        }

        /**
         * Sets a value in the configuration
         *
         * @param string $key The key to set (e.g. "key1.key2.key3")
         * @param mixed $value The value to set
         * @param bool $create If true, the key will be created if it does not exist
         * @return bool True if the value was set, false otherwise
         */
        public function set(string $key, mixed $value, bool $create=false): bool
        {
            if(!self::validateKey($key))
            {
                return false;
            }

            $path = explode('.', $key);
            $current = &$this->configuration;

            // Navigate to the parent of the value to set
            foreach ($path as $key_value)
            {
                if (is_array($current) && array_key_exists($key_value, $current))
                {
                    $current = &$current[$key_value];
                }
                elseif($create)
                {
                    $current[$key_value] = [];
                    $current = &$current[$key_value];
                }
                else
                {
                    return false;
                }

            }

            $current = $value;
            $this->modified = true;

            return true;
        }

        /**
         * Sets the default value for a key if it does not exist
         *
         * @param string $key
         * @param mixed $value
         * @return bool
         */
        public function setDefault(string $key, mixed $value): bool
        {
            if($this->exists($key))
            {
                return false;
            }

            return $this->set($key, $value, true);
        }

        /**
         * Checks if the given key exists in the configuration
         *
         * @param string $key
         * @return bool
         */
        public function exists(string $key): bool
        {
            if(!self::validateKey($key))
            {
                return false;
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
                    return false;
                }
            }

            return true;
        }

        /**
         * Clears the current configuration data
         *
         * @return void
         * @noinspection PhpUnused
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
                $json = json_encode($this->configuration, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $fs = new Filesystem();

                $fs->dumpFile($this->path, $json);
                $fs->chmod($this->path, 0777);
            }
            catch (Exception $e)
            {
                throw new RuntimeException('Unable to write configuration file', $e);
            }

            $this->modified = false;
            Log::debug('net.nosial.configlib', sprintf('Configuration "%s" saved', $this->name));
        }

        /**
         * Loads the Configuration File from the disk
         *
         * @param bool $force
         * @return void
         * @noinspection PhpUnused
         */
        public function load(bool $force=false): void
        {
            if (!$force && !$this->modified)
            {
                return;
            }

            // If the configuration file is a YAML file, import it instead
            if(str_ends_with($this->path, '.yaml') || str_ends_with($this->path, '.yml'))
            {
                $this->import($this->path);
                return;
            }

            $fs = new Filesystem();

            if (!$fs->exists($this->path))
            {
                return;
            }

            try
            {
                $json = file_get_contents($this->path);
                $this->configuration = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            }
            catch (Exception $e)
            {
                throw new RuntimeException('Unable to read configuration file', $e);
            }

            $this->modified = false;
            Log::debug('net.nosial.configlib', 'Loaded configuration file: ' . $this->path);
        }

        /**
         * Returns the name of the configuration
         *
         * @return string
         * @noinspection PhpUnused
         */
        public function getName(): string
        {
            return $this->name;
        }

        /**
         * Returns the path of the configuration file on disk
         *
         * @return string
         */
        public function getPath(): string
        {
            return $this->path;
        }

        /**
         * Returns the configuration
         *
         * @return array
         * @noinspection PhpUnused
         */
        public function getConfiguration(): array
        {
            return $this->configuration;
        }

        /**
         * Returns a formatted yaml string of the current configuration
         *
         * @return string
         */
        public function toYaml(): string
        {
            return Yaml::dump($this->configuration, 4, 2);
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
                    Log::error('net.nosial.configlib', sprintf('Unable to save configuration "%s" to disk, %s', $this->name, $e->getMessage()));
                }
            }
        }

        /**
         * Imports a YAML file into the configuration
         *
         * @param string $path
         * @return void
         */
        public function import(string $path): void
        {
            $fs = new Filesystem();

            if(!$fs->exists($path))
            {
                throw new RuntimeException(sprintf('Unable to import configuration file "%s", file does not exist', $path));
            }

            $yaml = file_get_contents($path);
            $data = Yaml::parse($yaml);

            $this->configuration = array_replace_recursive($this->configuration, $data);
            $this->modified = true;
        }

        /**
         * Exports the configuration to a YAML file
         *
         * @param string $path
         * @return void
         */
        public function export(string $path): void
        {
            $fs = new Filesystem();
            $fs->dumpFile($path, $this->toYaml());
            $fs->chmod($path, 0777);
        }
    }