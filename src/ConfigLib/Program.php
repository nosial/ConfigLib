<?php

    namespace ConfigLib;

    use Exception;
    use JetBrains\PhpStorm\NoReturn;
    use ncc\Exceptions\InvalidPackageNameException;
    use ncc\Exceptions\InvalidScopeException;
    use ncc\Exceptions\PackageLockException;
    use ncc\Exceptions\PackageNotFoundException;
    use ncc\Runtime;
    use OptsLib\Parse;
    use RuntimeException;
    use Symfony\Component\Filesystem\Filesystem;
    use Symfony\Component\Process\Process;
    use Symfony\Component\Yaml\Exception\ParseException;
    use Symfony\Component\Yaml\Yaml;

    class Program
    {
        /**
         * Main entry point of the program
         *
         * @return void
         */
        #[NoReturn] public static function main(): void
        {
            $args = Parse::getArguments();

            if(isset($args['help']) || isset($args['h']))
            {
                self::help();
            }

            if(isset($args['conf']) || isset($args['config']))
            {
                $configuration_name = $args['conf'] ?? $args['config'] ?? null;
                $property = $args['prop'] ?? $args['property'] ?? null;
                $value = $args['val'] ?? $args['value'] ?? null;
                $editor = $args['editor'] ?? @$args['e'] ?? null;
                $export = $args['export'] ?? null;
                $import = $args['import'] ?? null;

                if($configuration_name === null)
                {
                    print('You must specify a configuration name' . PHP_EOL);
                    exit(1);
                }

                $configuration = new Configuration($configuration_name);

                // Check if the configuration exists first.
                if(!file_exists($configuration->getPath()))
                {
                    print(sprintf('Configuration \'%s\' does not exist, aborting' . PHP_EOL, $configuration->getName()));
                    exit(1);
                }

                if($import !== null)
                {
                    try
                    {
                        $configuration->import((string)$import);
                        $configuration->save();
                    }
                    catch (Exception $e)
                    {
                        print($e->getMessage() . PHP_EOL);
                        exit(1);
                    }

                    print(sprintf('Configuration \'%s\' imported from \'%s\'' . PHP_EOL, $configuration->getName(), $import));
                    exit(0);
                }

                if($export !== null)
                {
                    if(!is_string($export))
                    {
                        $export = sprintf('%s.yml', $configuration->getName());
                    }

                    try
                    {
                        $configuration->export($export);
                    }
                    catch (Exception $e)
                    {
                        print($e->getMessage() . PHP_EOL);
                        exit(1);
                    }

                    print(sprintf('Configuration \'%s\' exported to \'%s\'' . PHP_EOL, $configuration->getName(), $export));
                    exit(0);
                }

                if($editor !== null)
                {
                    try
                    {
                        self::edit($args, $configuration);
                    }
                    catch(Exception $e)
                    {
                        print($e->getMessage() . PHP_EOL);
                        exit(1);
                    }
                }

                if($property === null)
                {
                    print($configuration->toYaml() . PHP_EOL);
                }
                else
                {
                    if($value === null)
                    {
                        print(Yaml::dump($configuration->get($property), 4, 2) . PHP_EOL);
                        return;
                    }

                    $configuration->set($property, $value);

                    try
                    {
                        $configuration->save();
                    }
                    catch (Exception $e)
                    {
                        print($e->getMessage() . PHP_EOL);
                        exit(1);
                    }
                }

                return;
            }

            self::help();
        }

        /**
         * Prints out the Help information for the program
         *
         * @return void
         */
        #[NoReturn] private static function help(): void
        {
            print('ConfigLib v' . Runtime::getConstant('net.nosial.configlib', 'version') . PHP_EOL . PHP_EOL);

            print('Usage: configlib [options]' . PHP_EOL);
            print('  -h, --help                        Displays the help menu' . PHP_EOL);
            print('  --conf, --config <name>           The name of the configuration' . PHP_EOL);
            print('  --prop, --property <property>     The property name to select/read (eg; foo.bar.baz) (Inline)' . PHP_EOL);
            print('  --val,  --value <value>           The value to set the property (Inline)' . PHP_EOL);
            print('  -e, --editor <editor>             (Optional) The editor to use (eg; nano, vim, notepad) (External)' . PHP_EOL);
            print('  --export <file>                   (Optional) Exports the configuration to a file' . PHP_EOL);
            print('  --import <file>                   (Optional) Imports the configuration from a file' . PHP_EOL);
            print('  --nc                              (Optional) Disables type casting (eg; \'true\' > True) will always be a string' . PHP_EOL);

            print('Examples:' . PHP_EOL . PHP_EOL);
            print(' configlib --conf test                           View the configuration' . PHP_EOL);
            print(' configlib --conf test --prop foo                View a specific property' . PHP_EOL);
            print(' configlib --conf test --prop foo --val bar      Set a specific property' . PHP_EOL);
            print(' configlib --conf test --editor nano             Edit the configuration' . PHP_EOL);
            print(' configlib --conf test --export out.json         Export the configuration' . PHP_EOL);
            print(' configlib --conf test --import in.json          Import a configuration' . PHP_EOL);

            exit(0);
        }

        /**
         * Edits an existing configuration file or creates a new one if it doesn't exist
         *
         * @param array $args
         * @param Configuration $configuration
         * @return void
         * @throws InvalidPackageNameException
         * @throws InvalidScopeException
         * @throws PackageLockException
         * @throws PackageNotFoundException
         */
        #[NoReturn] private static function edit(array $args, Configuration $configuration): void
        {
            $editor = $args['editor'] ?? $args['e'] ?? 'vi';

            if($editor === null)
            {
                print('No editor specified' . PHP_EOL);
                exit(1);
            }

            // Determine the temporary path to use
            if(file_exists(DIRECTORY_SEPARATOR . 'tmp'))
            {
                $tempPath = DIRECTORY_SEPARATOR . 'tmp';
            }
            else
            {
                if(!file_exists(Runtime::getDataPath('net.nosial.configlib') . DIRECTORY_SEPARATOR . 'tmp'))
                {
                    if (!mkdir($concurrentDirectory = Runtime::getDataPath('net.nosial.configlib') . DIRECTORY_SEPARATOR . 'tmp', 0777, true) && !is_dir($concurrentDirectory))
                    {
                        throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                    }

                    if(!file_exists(Runtime::getDataPath('net.nosial.configlib') . DIRECTORY_SEPARATOR . 'tmp'))
                    {
                        print('Unable to create the temporary path to use' . PHP_EOL);
                        exit(1);
                    }
                }

                $tempPath = Runtime::getDataPath('net.nosial.configlib') . DIRECTORY_SEPARATOR . 'tmp';
            }

            $fs = new Filesystem();

            try
            {
                // Convert the configuration from JSON to YAML for editing purposes
                $tempFile = $tempPath . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.yaml';
                $fs->dumpFile($tempFile, $configuration->toYaml());
                $original_hash = hash_file('sha1', $tempFile);

                // Open the editor
                $process = new Process([$editor, $tempFile]);
                $process->setTimeout(0);
                $process->setTty(true);
                $process->run();
            }
            catch(Exception $e)
            {
                print('Unable to open the editor, ' . $e->getMessage() . PHP_EOL);
                exit(1);
            }

            // Check if the file has changed and if so, update the configuration
            if($fs->exists($tempFile))
            {
                $new_hash = hash_file('sha1', $tempFile);
                if($original_hash !== $new_hash)
                {
                    // Convert the YAML back to JSON
                    $yaml = file_get_contents($tempFile);

                    try
                    {
                        $json = Yaml::parse($yaml);
                    }
                    catch (ParseException $e)
                    {
                        print('Unable to parse the YAML file, ' . $e->getMessage() . PHP_EOL);
                        exit(1);
                    }

                    try
                    {
                        $path = $configuration->getPath();
                        $fs->dumpFile($path, json_encode($json, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
                    }
                    catch(Exception $e)
                    {
                        print('Unable to save the configuration, ' . $e->getMessage() . PHP_EOL);
                        exit(1);
                    }

                    print('Configuration updated' . PHP_EOL);
                }
            }

            // Remove the temporary file
            if($fs->exists($tempFile))
            {
                $fs->remove($tempFile);
            }

            exit(0);
        }
    }