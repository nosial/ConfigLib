# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.9] - Ongoing

This update introduces minor changes and improvements

### Changed 
 - Refactor Configuration class to use FileFormat for serialization and deserialization


## [1.1.8] - 2025-03-21

This update introduces minor changes

### Changed
 - Disabled tty mode for the main execution points to prevent issues with docker environments
 - Refactor exception handling in Configuration class to include error codes


## [1.1.7] - 2025-03-14

This update introduces minor changes

### Changed
 - Updated remote references for dependencies
 - Updated Library to use net.nosial.loglib2 instead of the now deprecated net.nosial.loglib


## [1.1.6] - 2025-01-07

This update introduces minor improvements

### Changed
 - Changed properties to become typed properties

### Added
 - Added a new constructor parameter called `path` which is an optional parameter that allows you to specify the path to
   the configuration files directory. If not specified the library will proceed with resolving
   the path to the configuration files directory using the default method. This will override
   the `CONFIGLIB_PATH` environment variable if it is set.


## [1.1.5] - 2024-12-27

This update introduces minor improvements

### Added
 - Add support for CONFIGLIB_PATH environment variable to specify the path to the configuration files directory


## [1.1.4] - 2024-10-29

This update introduces a minor bug fix

### Fixed
- Fixed regex pattern for configuration properties being considered invalid when they contain an underscore.



## [1.1.3] - 2024-10-13

This update introduces a new build system



## [1.1.2] - 2024-09-27

 > This change has been reverted

This update fixes a critical bug where configuration files may not be found when using different user accounts,
especially when the configuration file is located in a directory that is not accessible by the user account running the
application. This was fixed by changing the way the configuration file path is resolved including by adding a setup
execution unit that will be executed post-installation to ensure that the configuration file is accessible by the user
account running the application.


## [1.1.1] - 2024-09-26

This update introduces a minor bug fix

### Fixed
 - Fixed issue where keys containing underscores are considered to be invalid


## [1.1.0] - 2024-09-23

This update introduces changes for PHP 8.3 & NCC 2.1.0+ compatibility

### Added
 - Added PhpUnit tests for the library
 - Added new option `--path` to the CLI interface to display the path to the configuration file

### Changed
 - Updated the codebase to be compatible with PHP 8.3
 - Updated the codebase to be compatible with NCC 2.1.0+
 - Updated Makefile

### Fixed
 - Fixed regex patterns to be more robust



## [1.0.4] - 2023-08-12

This update introduces minor improvements

### Added
 - Added the ability to override configuration properties with environment variables using the format
   `CONFIGLIB_<CONFIG_NAME>_<PROPERTY_NAME>`

### Fixed
 - Corrected a few lines of code in regards to missing variable definitions



## [1.0.3] - 2023-07-13

### Fixed
 - Fixed `Fatal error: Uncaught TypeError: array_replace_recursive(): Argument #1 ($array) must be of type array, null given in /var/ncc/packages/net.nosial.configlib=1.0.2/src/ConfigLib/Configuration.php:331`



## [1.0.2] - 2023-07-11

### Fixed
 - Fixed issue [#1](https://git.n64.cc/nosial/libs/config/-/issues/1) in Configuration->__construct() where the name of
   an environment variable was being used instead of its value when determining the configuration file path. This
   incorrect handling resulted in warnings about non-existent files and hindered the proper loading of configuration
   files. With this fix, environment variables should now correctly guide the path to the desired configuration files,
   improving the flexibility and functionality of the configuration library.



## [1.0.1] - 2023-07-11

### Changed
 - Refactored codebase to be more maintainable, readable & more optimized

### Added
 - ConfigurationLib will now attempt to load configuration files from Environment Variables if they are set, for example,
   if `com.example.application` wants to load `ExampleConfiguration` it will first check if `CONFIGLIB_EXAMPLECONFIGURATION`
   is set, and if so, load that file instead of going through the default process of loading the default configuration file. 
   If the file is not found, it will resort to its default behavior. You can either load an original json configuration
   file which is usually located at `\var\ncc\data\net.nosial.configlib` as one of the .conf files, or you can load a yml
   which is the same one you usually use to import or edit configurations. But the file extension must be either `.yml`
   or `.yaml` if you want ConfigLib to load it as a yml file, otherwise by default it assumes it is a json file.



## [1.0.0] - 2023-02-23

### Added
 - First Release
