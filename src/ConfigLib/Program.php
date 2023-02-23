<?php

    namespace ConfigLib;

    use Exception;
    use OptsLib\Parse;
    use Symfony\Component\Filesystem\Filesystem;
    use Symfony\Component\Process\Process;

    class Program
    {
        /**
         * Main entry point of the program
         *
         * @return void
         */
        public static function main(): void
        {
            $args = Parse::getArguments();

            if(isset($args['help']) || isset($args['h']))
                self::help();

            if(isset($args['version']) || isset($args['v']))
                self::version();

            if(isset($args['name']) || isset($args['n']))
            {
                $configuration_name = $args['name'] ?? $args['n'] ?? null;
                $property = $args['property'] ?? $args['p'] ?? null;
                $value = $args['value'] ?? $args['v'] ?? null;
                $editor = $args['editor'] ?? $args['e'] ?? null;

                if($configuration_name === null)
                {
                    print('You must specify a configuration name' . PHP_EOL);
                    exit(1);
                }

                $configuration = new Configuration($configuration_name);

                if($editor !== null)
                {
                    self::edit($args);
                    return;
                }

                if($property === null)
                {
                    print(json_encode($configuration->getConfiguration(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
                }
                else
                {
                    if($value === null)
                    {
                        print(json_encode($configuration->get($property, '(not set)'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
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
        private static function help(): void
        {
            print('Usage: configlib [options]' . PHP_EOL);
            print('  -h, --help             Displays the help menu' . PHP_EOL);
            print('  -v, --version          Displays the version of the program' . PHP_EOL);
            print('  -n, --name   <name>    The name of the configuration' . PHP_EOL);
            print('  -p, --path   <path>    The property name to select/read (eg; foo.bar.baz) (Inline)' . PHP_EOL);
            print('  -v, --value  <value>   The value to set the property (Inline)' . PHP_EOL);
            print('  -e, --editor <editor>  (Optional) The editor to use (eg; nano, vim, notepad) (External)' . PHP_EOL);
            print('  --nc                   (Optional) Disables type casting (eg; \'true\' > True) will always be a string' . PHP_EOL);
            print('  --export <file>        (Optional) Exports the configuration to a file' . PHP_EOL);
            print('  --import <file>        (Optional) Imports the configuration from a file' . PHP_EOL);
            print('Examples:' . PHP_EOL);
            print('  configlib -n com.example.package' . PHP_EOL);
            print('  configlib -n com.example.package -e nano' . PHP_EOL);
            print('  configlib -n com.example.package -p foo.bar.baz -v 123' . PHP_EOL);
            print('  configlib -n com.example.package -p foo.bar.baz -v 123 --nc' . PHP_EOL);
            print('  configlib -n com.example.package --export config.json' . PHP_EOL);
            print('  configlib -n com.example.package --import config.json' . PHP_EOL);

            exit(0);
        }

        /**
         * Edits an existing configuration file or creates a new one if it doesn't exist
         *
         * @param array $args
         * @return void
         */
        private static function edit(array $args): void
        {
            $editor = $args['editor'] ?? 'vi';
            if(isset($args['e']))
                $editor = $args['e'];

            $name = $args['name'] ?? 'default';

            if($editor == null)
            {
                print('No editor specified' . PHP_EOL);
                exit(1);
            }

            // Determine the temporary path to use
            $tempPath = null;

            if(function_exists('ini_get'))
            {
                $tempPath = ini_get('upload_tmp_dir');
                if($tempPath == null)
                    $tempPath = ini_get('session.save_path');
                if($tempPath == null)
                    $tempPath = ini_get('upload_tmp_dir');
                if($tempPath == null)
                    $tempPath = sys_get_temp_dir();
            }

            if($tempPath == null && function_exists('sys_get_temp_dir'))
                $tempPath = sys_get_temp_dir();

            if($tempPath == null)
            {
                print('Unable to determine the temporary path to use' . PHP_EOL);
                exit(1);
            }

            // Prepare the temporary file

            try
            {
                $configuration = new Configuration($name);
            }
            catch (Exception $e)
            {
                print($e->getMessage() . PHP_EOL);
                exit(1);
            }

            $fs = new Filesystem();
            $tempFile = $tempPath . DIRECTORY_SEPARATOR . $name . '.conf';
            $fs->copy($configuration->getPath(), $tempFile);
            $original_hash = hash_file('sha1', $tempFile);

            // Open the editor
            try
            {
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
            finally
            {
                $fs->remove($tempFile);
            }

            // Check if the file has changed and if so, update the configuration
            if($fs->exists($tempFile))
            {
                $new_hash = hash_file('sha1', $tempFile);
                if($original_hash != $new_hash)
                {
                    $fs->copy($tempFile, $configuration->getPath());
                }
                else
                {
                    print('No changes detected' . PHP_EOL);
                }

                $fs->remove($tempFile);
            }

        }

        /**
         * Prints out the version of the program
         *
         * @return void
         */
        private static function version(): void
        {
            print('ConfigLib v1.0.0' . PHP_EOL);
            exit(0);
        }
    }