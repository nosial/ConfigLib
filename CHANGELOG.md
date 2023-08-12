# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
