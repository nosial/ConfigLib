# ConfigLib

ConfigLib is a PHP library for managing configuration files and storing it
in NCC's data, while providing a command line interface for running functions
such as editing configuration files inline or importing/exporting 
configuration files.

One of the biggest advantages of using something like ConfigLib is that 
it will allow for more complicated software to be configured more easily
by following the documented instructions on how to alter configuration
files, optionally you could use a builtin editor to edit the configuration
file manually.


## Table of contents

<!-- TOC -->
* [ConfigLib](#configlib)
  * [Table of contents](#table-of-contents)
  * [Installation](#installation)
  * [Compile from source](#compile-from-source)
  * [Requirements](#requirements)
  * [Documentation](#documentation)
    * [Storage Location](#storage-location)
    * [Creating a new configuration file](#creating-a-new-configuration-file)
    * [Setting default values](#setting-default-values)
  * [Command-line usage](#command-line-usage)
    * [Editing a configuration file](#editing-a-configuration-file)
      * [Using an external editor](#using-an-external-editor)
      * [Inline command line editor](#inline-command-line-editor)
  * [License](#license)
<!-- TOC -->

## Installation

The library can be installed using ncc:

```bash
ncc install -p "nosial/libs.config=latest@n64"
```

or by adding the following to your project.json file under the `build.dependencies` section:
```json
{
  "name": "net.nosial.configlib",
  "version": "latest",
  "source_type": "remote",
  "source": "nosial/libs.config=latest@n64"
}
```

If you don't have the n64 source configured, you can add it by running the following command:
```bash
ncc source add --name n64 --type gitlab --host git.n64.cc
```

## Compile from source

To compile the library from source, you need to have [ncc](https://git.n64.cc/nosial/ncc) installed, then run the
following command:

```bash
ncc build
```

## Requirements

The library requires PHP 8.0 or higher.

## Documentation

ConfigLib is both a library and a command line tool, the library can be used within your program to create a new
configuration file and load in default entries, either on the first run or automatically during the installation
process.

The goal to ConfigLib is to make it easy to setup configuration parameters for your program and to make it easy
for a user to edit the configuration file without having to manually edit the file. This part of the documentation
will explain both how to implement the library into your program and how to use the command line tool to edit the
configuration file.


### Storage Location

Configuration files are stored as json files in the data directory of ConfigLib which is located at
`/var/ncc/data/net.nosial.configlib`, this directory is created automatically when the library is installed.

### Creating a new configuration file

To create a new configuration file, you can create a new `\ConfigLib\Configuration()` object and pass in the
name of the configuration file, for example:

```php
require 'ncc';
import('net.nosial.configlib');

$config = new \ConfigLib\Configuration('myconfig');
```

This will only initialize the object, to save the configuration file you need to call the `save()` method:

```php
$config->save();
```

### Setting default values

You can set default values for the configuration file which will be created if the values do not exist in the
configuration file, 

```php
require 'ncc';
import('net.nosial.configlib');

$config = new \ConfigLib\Configuration('com.symfony.yaml');

$config->setDefault('database.host', '127.0.0.1');
$config->setDefault('database.port', 3306);
$config->setDefault('database.username', 'root');
$config->setDefault('database.password', null);
$config->setDefault('database.name', 'test');

$config->save();
```

## Command-line usage

The command line interface can be executed by running `configlib` from the command line or by running
`ncc exec --package="net.nosial.configlib` if `configlib` isn't in your global path.

For the rest of this documentation, we will assume that you have the `configlib` command in your global path.

### Editing a configuration file

There are two ways to edit a configuration file using ConfigLib

 1. Using an external editor
 2. Inline command line editor

#### Using an external editor

When you use an external editor, ConfigLib will create a temporary YAML file and open it in the specified editor,
when you save and close the file, ConfigLib will parse the YAML file and save the configuration file. If the YAML
file is invalid, ConfigLib will not save the configuration file.

This is the recommended way to edit configuration files as it allows you to use your preferred editor and it
allows you to use the full power of YAML.

To edit a configuration file using an external editor, run the following command:

```bash
configlib --config <config_name> --editor nano
```

 > Note: Changes will only be applied if you save the file and close the editor.

#### Inline command line editor

The inline command line editor is a simple editor that allows you to edit the configuration file from the command
line, this is useful for automated scripts.

To view the contents of a configuration file, run the following command:

```bash
configlib --config <config_name>
```

To view the value of a specific property, use the `--property` option:

```bash
configlib --config <config_name> --property database.username
```

To edit a property, specify both the `--property` and `--value` options:

```bash
configlib --config <config_name> --property database.username --value root
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details