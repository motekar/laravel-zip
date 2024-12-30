<?php

namespace Motekar\LaravelZip;

use Exception;
use Illuminate\Support\Facades\File;
use Motekar\LaravelZip\Repositories\RepositoryInterface;

/**
 * This class is a wrapper around the ZipArchive methods with some handy functions
 */
class ZipManager
{
    /**
     * Constant for extracting
     */
    const WHITELIST = 1;

    /**
     * Constant for extracting
     */
    const BLACKLIST = 2;

    /**
     * Constant for matching only strictly equal file names
     */
    const EXACT_MATCH = 4;

    /**
     * Represents the current location in the archive
     */
    private string $currentFolder = '';

    /**
     * Handler to the archive
     */
    private ?RepositoryInterface $repository = null;

    /**
     * The path to the current zip file
     */
    private string $filePath;

    /**
     * Create a new zip Archive if the file does not exists
     * opens a zip archive if the file exists
     *
     * @param  string  $filePath  The file to open
     * @param  RepositoryInterface|string  $type  The type of the archive
     *
     * @throws \RuntimeException
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function make(string $filePath, RepositoryInterface|string $type = 'zip'): static
    {
        $new = $this->createArchiveFile($filePath);

        $objectOrName = $type;
        if (is_string($type)) {
            $namespace = (new \ReflectionClass(RepositoryInterface::class))->getNamespaceName();
            $objectOrName = $namespace . '\\' . ucwords($type) . 'Repository';
        }

        if (! is_subclass_of($objectOrName, RepositoryInterface::class)) {
            throw new \InvalidArgumentException("Class for '{$objectOrName}' must implement RepositoryInterface interface");
        }

        try {
            if (is_string($objectOrName)) {
                $this->repository = new $objectOrName($filePath, $new);
            } else {
                $this->repository = $type;
            }
        } catch (Exception $e) {
            throw new Exception(sprintf(
                'Failed to initialize repository: %s. Error: %s',
                is_string($objectOrName) ? $objectOrName : get_class($objectOrName),
                $e->getMessage()
            ), 0, $e);
        }

        $this->filePath = $filePath;

        return $this;
    }

    /**
     * Extracts the opened zip archive to the specified location <br/>
     * you can provide an array of files and folders and define if they should be a white list
     * or a black list to extract. By default this method compares file names using "string starts with" logic
     *
     * @param  $path  string The path to extract to
     * @param  array  $files  An array of files
     * @param  int  $methodFlags  The Method the files should be treated
     *
     * @throws \Exception
     */
    public function extractTo(string $path, array $files = [], int $methodFlags = self::BLACKLIST)
    {
        if (! File::exists($path) && ! File::makeDirectory($path, 0755, true)) {
            throw new \RuntimeException('Failed to create folder');
        }

        if ($methodFlags & self::EXACT_MATCH) {
            $matchingMethod = fn ($haystack) => in_array($haystack, $files, true);
        } else {
            $matchingMethod = fn ($haystack) => collect($files)->contains(fn ($f) => str_starts_with($haystack, $f));
        }

        if ($methodFlags & self::WHITELIST) {
            $this->extractFilesInternal($path, $matchingMethod);
        } else {
            // blacklist - extract files that do not match with $matchingMethod
            $this->extractFilesInternal($path, function ($filename) use ($matchingMethod) {
                return ! $matchingMethod($filename);
            });
        }
    }

    /**
     * Extracts matching files/folders from the opened zip archive to the specified location.
     *
     * @param  string  $extractToPath  The path to extract to
     * @param  string  $regex  regular expression used to match files. See @link http://php.net/manual/en/reference.pcre.pattern.syntax.php
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function extractMatchingRegex(string $extractToPath, string $regex)
    {
        if (empty($regex)) {
            throw new \InvalidArgumentException('Missing pass valid regex parameter');
        }

        $this->extractFilesInternal($extractToPath, function ($filename) use ($regex) {
            $match = preg_match($regex, $filename);
            if ($match === 1) {
                return true;
            } elseif ($match === false) {
                //invalid pattern for preg_match raises E_WARNING and returns FALSE
                //so if you have custom error_handler set to catch and throw E_WARNINGs you never end up here
                //but if you have not - this will throw exception
                throw new \RuntimeException("regular expression match on '{$filename}' failed with error. Please check if pattern is valid regular expression.");
            }

            return false;
        });
    }

    /**
     * Gets the content of a single file if available
     *
     * @param  $filePath  string The full path (including all folders) of the file in the zip
     * @return mixed returns the content or throws an exception
     *
     * @throws \Exception
     */
    public function getFileContent(string $filePath): mixed
    {
        if ($this->repository->fileExists($filePath) === false) {
            throw new Exception(sprintf('The file "%s" cannot be found', $filePath));
        }

        return $this->repository->getFileContent($filePath);
    }

    /**
     * Add one or multiple files to the zip.
     *
     * @param  array|string  $pathToAdd  An array or string of files and folders to add
     */
    public function add(array|string $pathToAdd, ?string $fileName = null): static
    {
        if (is_array($pathToAdd)) {
            foreach ($pathToAdd as $key => $dir) {
                if (! is_int($key)) {
                    $this->add($dir, $key);
                } else {
                    $this->add($dir);
                }
            }
        } elseif (File::isFile($pathToAdd)) {
            $this->addFile($pathToAdd, $fileName);
        } else {
            $this->addDir($pathToAdd);
        }

        return $this;
    }

    /**
     * Add an empty directory
     */
    public function addEmptyDir(string $dirName): static
    {
        $this->repository->addEmptyDir($dirName);

        return $this;
    }

    /**
     * Add a file to the zip using its contents
     *
     * @param  $filename  string The name of the file to create
     * @param  $content  string The file contents
     */
    public function addString(string $filename, string $content): static
    {
        $this->addFromString($filename, $content);

        return $this;
    }

    /**
     * Gets the status of the zip.
     *
     * @return string The status of the internal zip file
     */
    public function getStatus(): string
    {
        return $this->repository->getStatus();
    }

    /**
     * Remove a file or array of files and folders from the zip archive
     *
     * @param  $fileToRemove  array|string The path/array to the files in the zip
     */
    public function remove(array|string $fileToRemove): static
    {
        if (is_array($fileToRemove)) {
            $self = $this;
            $this->repository->each(function ($file, $stats = null) use ($fileToRemove, $self) {
                if (in_array($file, $fileToRemove)) {
                    $self->getRepository()->removeFile($file);
                }
            });
        } else {
            $this->repository->removeFile($fileToRemove);
        }

        return $this;
    }

    /**
     * Returns the path of the current zip file if there is one.
     *
     * @return string The path to the file
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Sets the password to be used for decompressing
     */
    public function usePassword(string $password): bool
    {
        return $this->repository->usePassword($password);
    }

    /**
     * Closes the zip file and frees all handles
     */
    public function close()
    {
        if ($this->repository !== null) {
            $this->repository->close();
            $this->repository = null;
        }
        $this->filePath = '';
    }

    /**
     * Sets the internal folder to the given path.<br/>
     * Useful for extracting only a segment of a zip file.
     */
    public function folder(string $path): static
    {
        // Normalize path by removing trailing slashes
        $this->currentFolder = rtrim($path, '/\\');

        return $this;
    }

    /**
     * Resets the internal folder to the root of the zip file.
     */
    public function home(): static
    {
        $this->currentFolder = '';

        return $this;
    }

    /**
     * Saves the archive file
     */
    public function save(): static
    {
        $this->repository->save();

        return $this;
    }

    /**
     * Deletes the archive file
     */
    public function delete()
    {
        if ($this->repository !== null) {
            $this->repository->close();
        }

        File::delete($this->filePath);
        $this->filePath = '';
    }

    /**
     * Get the type of the Archive
     */
    public function getArchiveType(): string
    {
        return get_class($this->repository);
    }

    /**
     * Get the current internal folder pointer
     */
    public function getCurrentFolderPath(): string
    {
        return $this->currentFolder;
    }

    /**
     * Checks if a file is present in the archive
     */
    public function contains(string $fileInArchive): bool
    {
        return $this->repository->fileExists($fileInArchive);
    }

    public function getRepository(): RepositoryInterface
    {
        return $this->repository;
    }

    /**
     * Gets the path to the internal folder
     */
    public function getInternalPath(): string
    {
        return empty($this->currentFolder) ? '' : $this->currentFolder . '/';
    }

    /**
     * List all files that are within the archive
     *
     * @param  string|null  $regexFilter  regular expression to filter returned files/folders. See @link http://php.net/manual/en/reference.pcre.pattern.syntax.php
     *
     * @throws \RuntimeException
     */
    public function listFiles(?string $regexFilter = null): array
    {
        $filesList = [];
        if ($regexFilter) {
            $filter = function ($file, $stats = null) use (&$filesList, $regexFilter) {
                // push/pop an error handler here to to make sure no error/exception thrown if $expected is not a regex
                set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline): bool => true);
                $match = preg_match($regexFilter, $file);
                restore_error_handler();

                if ($match === 1) {
                    $filesList[] = $file;
                } elseif ($match === false) {
                    throw new \RuntimeException("regular expression match on '{$file}' failed with error. Please check if pattern is valid regular expression.");
                }
            };
        } else {
            $filter = function ($file, $stats = null) use (&$filesList) {
                $filesList[] = $file;
            };
        }
        $this->repository->each($filter);

        return $filesList;
    }

    private function getCurrentFolderWithTrailingSlash(): string
    {
        if (empty($this->currentFolder)) {
            return '';
        }

        $lastChar = mb_substr($this->currentFolder, -1);
        if ($lastChar !== '/' && $lastChar !== '\\') {
            return $this->currentFolder . '/';
        }

        return $this->currentFolder;
    }

    //---------------------PRIVATE FUNCTIONS-------------

    /**
     * @throws \Exception
     */
    private function createArchiveFile(string $pathToZip): bool
    {
        if (! File::exists($pathToZip)) {
            $dirname = dirname($pathToZip);
            if (! File::exists($dirname) && ! File::makeDirectory($dirname, 0755, true)) {
                throw new \RuntimeException('Failed to create folder');
            } elseif (! File::isWritable($dirname)) {
                throw new Exception(sprintf('The path "%s" is not writeable', $pathToZip));
            }

            return true;
        }

        return false;
    }

    private function addDir(string $pathToDir)
    {
        // First go over the files in this directory and add them to the repository.
        foreach (File::files($pathToDir) as $file) {
            $this->addFile($pathToDir . DIRECTORY_SEPARATOR . basename($file));
        }

        // Now let's visit the subdirectories and add them, too.
        foreach (File::directories($pathToDir) as $dir) {
            // Skip symbolic links to prevent infinite recursion
            if (is_link($dir)) {
                continue;
            }

            $old_folder = $this->currentFolder;
            $this->currentFolder = empty($this->currentFolder) ? basename($dir) : $this->currentFolder . '/' . basename($dir);
            $this->addDir($pathToDir . '/' . basename($dir));
            $this->currentFolder = $old_folder;
        }
    }

    /**
     * Add the file to the zip
     */
    private function addFile(string $pathToAdd, ?string $fileName = null)
    {
        if (! $fileName) {
            $info = pathinfo($pathToAdd);
            $fileName = isset($info['extension']) ?
                $info['filename'] . '.' . $info['extension'] :
                $info['filename'];
        }

        $this->repository->addFile($pathToAdd, $this->getInternalPath() . $fileName);
    }

    /**
     * Add the file to the zip from content
     */
    private function addFromString(string $filename, string $content)
    {
        $this->repository->addFromString($this->getInternalPath() . $filename, $content);
    }

    private function extractFilesInternal(string $path, callable $matchingMethod)
    {
        $self = $this;
        $this->repository->each(function ($file, $stats = null) use ($path, $matchingMethod, $self) {
            $currentPath = $self->getCurrentFolderWithTrailingSlash();
            if (! empty($currentPath) && ! str_starts_with($file, $currentPath)) {
                return;
            }

            $filename = str_replace($self->getInternalPath(), '', $file);
            if ($matchingMethod($filename)) {
                $self->extractOneFileInternal($file, $path);
            }
        });
    }

    /**
     * Normalizes a path similar to realpath but works for non-existent paths
     *
     * @param  string  $path  The path to normalize
     * @return string The normalized absolute path
     */
    private function normalizePath(string $path): string
    {
        // Convert to absolute path
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $absolutes = [];

        foreach ($parts as $part) {
            if ($part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }

    /**
     * @throws \RuntimeException
     */
    private function extractOneFileInternal(string $fileName, string $path)
    {
        $tmpPath = str_replace($this->getInternalPath(), '', $fileName);

        // Prevent Zip traversal attacks
        $normalizedPath = $this->normalizePath($path);
        $targetPath = $this->normalizePath($path . DIRECTORY_SEPARATOR . $tmpPath);

        if (strpos($targetPath, $normalizedPath) !== 0) {
            throw new \RuntimeException('Invalid path detected - possible path traversal attempt');
        }

        // We need to create the directory first in case it doesn't exist
        $dir = pathinfo($targetPath, PATHINFO_DIRNAME);
        if (! File::exists($dir) && ! File::makeDirectory($dir, 0755, true, true)) {
            throw new \RuntimeException('Failed to create folders');
        }

        $toPath = $path . DIRECTORY_SEPARATOR . $tmpPath;
        $fileStream = $this->getRepository()->getFileStream($fileName);
        File::put($toPath, $fileStream);
    }
}
