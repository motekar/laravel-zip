<?php

namespace Motekar\LaravelZip\Repositories;

/**
 * RepositoryInterface that needs to be implemented by every Repository
 *
 * Class RepositoryInterface
 */
interface RepositoryInterface
{
    /**
     * Construct with a given path
     */
    public function __construct(string $filePath, bool $create = false, ?object $archiveImpl = null);

    /**
     * Add a file to the opened Archive
     */
    public function addFile(string $pathToFile, string $pathInArchive);

    /**
     * Add a file to the opened Archive using its contents
     */
    public function addFromString(string $name, string $content);

    /**
     * Add an empty directory
     */
    public function addEmptyDir(string $dirName);

    /**
     * Remove a file permanently from the Archive
     */
    public function removeFile(string $pathInArchive);

    /**
     * Get the content of a file
     */
    public function getFileContent(string $pathInArchive): string;

    /**
     * Get the stream of a file
     */
    public function getFileStream(string $pathInArchive): mixed;

    /**
     * Will loop over every item in the archive and will execute the callback on them
     * Will provide the filename for every item
     */
    public function each(callable $callback);

    /**
     * Checks whether the file is in the archive
     */
    public function fileExists(string $fileInArchive): bool;

    /**
     * Sets the password to be used for decompressing
     * function named usePassword for clarity
     */
    public function usePassword(string $password): bool;

    /**
     * Returns the status of the archive as a string
     */
    public function getStatus(): string;

    /**
     * Saves the archive
     */
    public function save();

    /**
     * Closes the archive and saves it
     */
    public function close();
}
