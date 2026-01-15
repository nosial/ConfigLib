<?php

    namespace ConfigLib;

    use Exception;
    use fslib\IO;
    use fslib\IOException;
    use InvalidArgumentException;
    use RuntimeException;
    use SebastianBergmann\LinesOfCode\IllogicalValuesException;
    use Symfony\Component\Yaml\Yaml;

    enum FileFormat
    {
        case JSON;
        case JSON_PRETTY;
        case YAML;
        case SERIALIZED;

        /**
         * Serializes the given array input to one of supported FileFormats
         *
         * @param array $data The array data to serialize
         * @return string The serialized data in a String format
         */
        public function serialize(array $data): string
        {
            return match($this)
            {
                self::JSON => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                self::JSON_PRETTY => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                self::YAML => Yaml::dump($data),
                self::SERIALIZED => serialize($data)
            };
        }

        /**
         * Produces a serialized file based off the given input
         *
         * @param array $data The array data to serialize
         * @param string $filePath The file path to write to (eg; /home/output or /home/output.json if $appendExtension is False)
         * @param bool $appendExtension If True, the file extension will be appended to the file Path, for example if the $filePath is "/home/output" it will become "/home/output.json" based off the FileFormat
         * @return void
         */
        public function toFile(array $data, string $filePath, bool $appendExtension=true): void
        {
            if(!str_ends_with($filePath, $this->getExtension()) && $appendExtension)
            {
                $filePath .= $this->getExtension();
            }
            elseif(!str_ends_with($filePath, $this->getExtension(false)) && $appendExtension)
            {
                $filePath .= $this->getExtension(false);
            }

            try
            {
                IO::writeFile($filePath, $this->serialize($data));
            }
            catch(IOException $e)
            {
                throw new RuntimeException(sprintf("Unable to write to file: %s", $filePath), 0, $e);
            }
        }

        /**
         * Unserializes the given string data back to an array based off one of the supported FileFormats
         *
         * @param string $data The string format to unserialize
         * @return array The constructed array from the serialized content
         */
        public function unserialize(string $data): array
        {
            return match($this)
            {
                self::JSON, self::JSON_PRETTY => json_decode($data, true),
                self::YAML => Yaml::parse($data),
                self::SERIALIZED => unserialize($data)
            };
        }

        /**
         * Returns the file extension based off one of the supported FileFormats
         *
         * @param bool $withPrefix if True, the file extension will be prefixed with ".", eg; ".json", False: "json"
         * @return string The file extension
         */
        public function getExtension(bool $withPrefix=true): string
        {
            return match($this)
            {
                self::JSON, self::JSON_PRETTY => $withPrefix ? '.json' : 'json',
                self::YAML => $withPrefix ? '.yml' : 'yml',
                self::SERIALIZED => $withPrefix ? '.ser' : 'ser'
            };
        }

        /**
         * Unserialize a file based off the given file format or based off it's file extension
         *
         * @param string $filePath The file path to read
         * @param FileFormat|null $fileFormat The file format to read the file as, if null this value will be detected based off the file extension
         * @return array The unserailized data
         */
        public static function fromFile(string $filePath, ?FileFormat $fileFormat=null): array
        {
            if(!IO::exists($filePath))
            {
                throw new InvalidArgumentException(sprintf("The file path %s does exist", $filePath));
            }

            if(!IO::isReadable($filePath))
            {
                throw new RuntimeException(sprintf("No read access to %s", $filePath));
            }

            // If the file format is null, we try to detect it based off the extension.
            if($fileFormat === null)
            {
                $fileExtension = pathinfo($filePath)['extension'];
                if($fileExtension === null || strlen($fileExtension) === 0)
                {
                    throw new RuntimeException(sprintf("Unable to determine file extension of %s", $fileExtension));
                }

                $fileExtension = strtolower($fileExtension);
                if($fileExtension === 'json' || $fileExtension == 'conf')
                {
                    $fileFormat = self::JSON;
                }
                elseif($fileExtension === 'yaml' || $fileExtension === 'yml')
                {
                    $fileFormat = self::YAML;
                }
                elseif($fileExtension === 'ser' || $fileExtension === 'serialized')
                {
                    $fileFormat = self::SERIALIZED;
                }
                else
                {
                    throw new IllogicalValuesException(sprintf("Unable to determine the file format type based off the extension of %s", $filePath));
                }
            }

            try
            {
                return $fileFormat->unserialize(IO::readFile($filePath));
            }
            catch(Exception $e)
            {
                throw new RuntimeException(sprintf("Unable to read file: %s", $filePath), 0, $e);
            }
        }
    }
