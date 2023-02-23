<?php

    /** @noinspection PhpMissingFieldTypeInspection */

    namespace ConfigLib;

    use Exception;
    use LogLib\Log;
    use ncc\Runtime;
    use RuntimeException;
    use Symfony\Component\Filesystem\Exception\IOException;
    use Symfony\Component\Filesystem\Filesystem;

    class Configuration
    {
        /**
         * The name of the configuration
         *
         * @var string
         */
        private $Name;

        /**
         * The path to the configuration file
         *
         * @var string
         */
        private $Path;

        /**
         * The configuration data
         *
         * @var array
         */
        private $Configuration;

        /**
         * Indicates if the current instance is modified
         *
         * @var bool
         */
        private $Modified;

        /**
         * Public Constructor
         *
         * @param string $name The name  of the configuration (e.g. "MyApp" or "net.example.myapp")
         */
        public function __construct(string $name='default')
        {
            // Sanitize $name for file path
            $name = strtolower($name);
            $name = str_replace('/', '_', $name);
            $name = str_replace('\\', '_', $name);
            $name = str_replace('.', '_', $name);

            // Figure out the path to the configuration file
            try
            {
                /** @noinspection PhpUndefinedClassInspection */
                $this->Path = Runtime::getDataPath('net.nosial.configlib') . DIRECTORY_SEPARATOR . $name . '.conf';
            }
            catch (Exception $e)
            {
                throw new RuntimeException('Unable to load package "net.nosial.configlib"', $e);
            }

            // Set the name
            $this->Name = $name;

            // Default Configuration
            $this->Modified = false;


            if(file_exists($this->Path))
            {
                try
                {
                    $this->load(true);
                }
                catch(Exception $e)
                {
                    Log::error('net.nosial.configlib', sprintf('Unable to load configuration "%s", %s', $this->Name, $e->getMessage()));
                    throw new RuntimeException(sprintf('Unable to load configuration "%s"', $this->Name), $e);
                }
            }
            else
            {
                $this->Configuration = [];
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
                return true;

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
                if (str_contains($input, '.'))
                    return (float)$input;

                if (ctype_digit($input))
                    return (int)$input;
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
                return $default;

            $path = explode('.', $key);
            $current = $this->Configuration;

            foreach ($path as $key)
            {
                if (is_array($current) && array_key_exists($key, $current))
                {
                    $current = $current[$key];
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
                return false;

            $path = explode('.', $key);
            $current = &$this->Configuration;

            // Navigate to the parent of the value to set
            foreach ($path as $key)
            {
                if (is_array($current) && array_key_exists($key, $current))
                {
                    $current = &$current[$key];
                }
                else
                {
                    if ($create)
                    {
                        $current[$key] = [];
                        $current = &$current[$key];
                    }
                    else
                    {
                        return false;
                    }
                }
            }

            // Set the value
            $current = $value;

            $this->Modified = true;
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
                return false;

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
                return false;
            if(!isset($this->Configuration[$key]))
                return false;

            $path = explode('.', $key);
            $current = $this->Configuration;

            foreach ($path as $key)
            {
                if (is_array($current) && array_key_exists($key, $current))
                {
                    $current = $current[$key];
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
            $this->Configuration = [];
        }

        /**
         * Saves the Configuration File to the disk
         *
         * @return void
         * @throws Exception
         */
        public function save(): void
        {
            if (!$this->Modified)
                return;

            $json = json_encode($this->Configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $fs = new Filesystem();

            try
            {
                $fs->dumpFile($this->Path, $json);
            }
            catch (IOException $e)
            {
                throw new Exception('Unable to write configuration file', $e);
            }

            $this->Modified = false;
            Log::debug('net.nosial.configlib', sprintf('Configuration "%s" saved', $this->Name));
        }

        /**
         * Loads the Configuration File from the disk
         *
         * @param bool $force
         * @return void
         * @throws Exception
         * @noinspection PhpUnused
         */
        public function load(bool $force=false): void
        {
            if (!$force && !$this->Modified)
                return;

            $fs = new Filesystem();

            if (!$fs->exists($this->Path))
                return;

            try
            {
                $json = file_get_contents($this->Path);
            }
            catch (IOException $e)
            {
                throw new Exception('Unable to read configuration file', $e);
            }

            $this->Configuration = json_decode($json, true);
            $this->Modified = false;

            Log::debug('net.nosial.configlib', 'Loaded configuration file: ' . $this->Path);
        }



        /**
         * Returns the name of the configuration
         *
         * @return string
         * @noinspection PhpUnused
         */
        public function getName(): string
        {
            return $this->Name;
        }

        /**
         * Returns the path of the configuration file on disk
         *
         * @return string
         */
        public function getPath(): string
        {
            return $this->Path;
        }

        /**
         * @return array
         * @noinspection PhpUnused
         */
        public function getConfiguration(): array
        {
            return $this->Configuration;
        }

        /**
         * Public Destructor
         */
        public function __destruct()
        {
            if($this->Modified)
            {
                try
                {
                    $this->save();
                }
                catch(Exception $e)
                {
                    Log::error('net.nosial.configlib', sprintf('Unable to save configuration "%s" to disk, %s', $this->Name, $e->getMessage()));
                }
            }
        }
    }