# Laravel Zip - Create and Manage Zip Archives in Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/motekar/laravel-zip.svg?style=flat-square)](https://packagist.org/packages/motekar/laravel-zip)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/motekar/laravel-zip/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/motekar/laravel-zip/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/motekar/laravel-zip/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/motekar/laravel-zip/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/motekar/laravel-zip.svg?style=flat-square)](https://packagist.org/packages/motekar/laravel-zip)

This package provides a simple and intuitive way to create and manage Zip archives in Laravel applications. It wraps PHP's ZipArchive class with additional convenience methods and fluent syntax.

## Quick Example

```php
use Motekar\LaravelZip\Facades\Zip;

Zip::make(storage_path('images.zip'))
    ->add(glob(storage_path('images/*')))
    ->close();
```

This code creates a zip file named `images.zip` in the storage directory, containing all files from the `storage/images/` folder.

## Installation

Install the package via Composer:

```bash
composer require motekar/laravel-zip
```

## Usage

```php
use Motekar\LaravelZip\Facades\Zip;

// Create a zip file and add files
$zip = Zip::make('test.zip')
    ->folder('test')
    ->add('composer.json');

// Add a file with a specific name
$zip = Zip::make('test.zip')
    ->folder('test')
    ->add('composer.json', 'test');

// Remove a file from the archive
$zip->remove('composer.lock');

// Add multiple files to a specific folder
$zip->folder('mySuperPackage')->add([
    'vendor',
    'composer.json'
]);

// Get file content from the archive
$content = $zip->getFileContent('mySuperPackage/composer.json');

// Extract specific files using whitelist
$zip->make('test.zip')
    ->extractTo('', ['mySuperPackage/composer.json'], Zipper::WHITELIST)
    ->close();
```

**Important Notes:**
1. Always call `->close()` at the end to write changes to disk
2. Most methods are chainable except:
   - `getFileContent`
   - `getStatus`
   - `close`
   - `extractTo`

## API Reference

### make($pathToFile)
Creates or opens a zip archive. If the file doesn't exist, it creates a new one. Returns the Zipper instance for method chaining.

### add($filesOrFolder)
Adds files or folders to the archive. Accepts:
- An array of file paths
- A single folder path (all files in the folder will be added)

### addString($filename, $content)
Adds a file to the archive using string content.

### remove($files)
Removes files from the archive. Accepts:
- A single file path
- An array of file paths

### folder($folder)
Sets the working folder for subsequent operations.

### listFiles($regexFilter = null)
Lists files in the archive. Optionally filters files using a regex pattern.

**Note:** Ignores the folder set with `folder()`

**Examples:**
```php
// Get all .log files
$logFiles = Zip::make('test.zip')->listFiles('/\.log$/i');

// Get all non-.log files
$notLogFiles = Zip::make('test.zip')->listFiles('/^(?!.*\.log).*$/i');
```

### home()
Resets the folder pointer to the root.

### getFileContent($filePath)
Returns the content of a file from the archive or false if not found.

### getStatus()
Returns the opening status of the zip archive as an integer.

### close()
Writes all changes and closes the archive.

### extractTo($path, $files = [], $flags = 0)
Extracts archive contents to the specified path. Supports:
- **Zipper::WHITELIST**: Extract only specified files
- **Zipper::BLACKLIST**: Extract all except specified files
- **Zipper::EXACT_MATCH**: Match file names exactly

**Examples:**
```php
use Motekar\LaravelZip\Facades\Zip;
use Motekar\LaravelZip\ZipManager;

// Whitelist example
Zip::make('test.zip')
    ->extractTo('public', ['vendor'], ZipManager::WHITELIST);

// Blacklist example
Zip::make('test.zip')
    ->extractTo('public', ['vendor'], ZipManager::BLACKLIST);

// Exact match example
Zip::make('test.zip')
    ->folder('vendor')
    ->extractTo('public', ['composer', 'bin/phpunit'], ZipManager::WHITELIST | ZipManager::EXACT_MATCH);
```

### extractMatchingRegex($path, $regex)
Extracts files matching a regular expression pattern.

**Examples:**
```php
// Extract PHP files
Zip::make('test.zip')
    ->folder('src')
    ->extractMatchingRegex($path, '/\.php$/i');

// Exclude test files
Zip::make('test.zip')
    ->folder('src')
    ->extractMatchingRegex($path, '/^(?!.*test\.php).*$/i');
```

**Important Notes:**
1. PHP's ZipArchive uses '/' as the directory separator
2. Windows users should use '/' in patterns instead of '\'

## Credits

- [Fauzie Rofi](https://github.com/fauzie811)
- [Nils Plaschke](http://nilsplaschke.de)
- [All Contributors](../../contributors)

## License

This package is open-source software licensed under the [Apache Version 2.0 license](LICENSE.md).
