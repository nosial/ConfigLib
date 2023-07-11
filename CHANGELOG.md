# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - Unreleased

### Changed
 * Refactored codebase to be more maintainable, readable & more optimized

### Added
 * ConfigurationLib will now attempt to load configuration files from Environment Variables if they are set, for example,
   if `com.example.application` wants to load `ExampleConfiguration` it will first check if `CONFIGLIB_EXAMPLECONFIGURATION`
   is set, and if so, load that file instead of going through the default process of loading the default configuration file. 
   If the file is not found, it will resort to its default behavior. You can either load an original json configuration
   file which is usually located at `\var\ncc\data\net.nosial.configlib` as one of the .conf files, or you can load a yml
   which is the same one you usually use to import or edit configurations. But the file extension must be either `.yml`
   or `.yaml` if you want ConfigLib to load it as a yml file, otherwise by default it assumes it is a json file.


## [1.0.0] - 2023-02-23

### Added
 * First Release
