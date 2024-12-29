<?php

namespace Motekar\LaravelZip\Repositories;

use Exception;
use ZipArchive;

class ZipRepository implements RepositoryInterface
{
    private string $filePath;

    private ZipArchive $archive;

    /**
     * @throws \Exception
     */
    public function __construct(string $filePath, bool $create = false, ?object $archiveImpl = null)
    {
        //Check if ZipArchive is available
        if (! class_exists('ZipArchive')) {
            throw new \Exception('Error: Your PHP version is not compiled with zip support');
        }
        $this->filePath = $filePath;
        $this->archive = $archiveImpl ? $archiveImpl : new ZipArchive;

        $res = $this->archive->open($filePath, ($create ? ZipArchive::CREATE : null));
        if ($res !== true) {
            throw new Exception("Error: Failed to open {$filePath}! Error: " . $this->getErrorMessage($res));
        }
    }

    public function addFile(string $pathToFile, string $pathInArchive)
    {
        $this->archive->addFile($pathToFile, $pathInArchive);
    }

    public function addEmptyDir($dirName)
    {
        $this->archive->addEmptyDir($dirName);
    }

    public function addFromString(string $name, string $content)
    {
        $this->archive->addFromString($name, $content);
    }

    public function removeFile($pathInArchive)
    {
        $this->archive->deleteName($pathInArchive);
    }

    public function getFileContent(string $pathInArchive): string
    {
        return $this->archive->getFromName($pathInArchive);
    }

    public function getFileStream(string $pathInArchive): mixed
    {
        return $this->archive->getStream($pathInArchive);
    }

    public function each(callable $callback)
    {
        for ($i = 0; $i < $this->archive->numFiles; $i++) {
            //skip if folder
            $stats = $this->archive->statIndex($i);
            if ($stats['size'] === 0 && $stats['crc'] === 0) {
                continue;
            }
            call_user_func_array($callback, [
                'file' => $this->archive->getNameIndex($i),
                'stats' => $this->archive->statIndex($i),
            ]);
        }
    }

    public function fileExists(string $fileInArchive): bool
    {
        return $this->archive->locateName($fileInArchive) !== false;
    }

    public function usePassword(string $password): bool
    {
        return $this->archive->setPassword($password);
    }

    public function getStatus(): string
    {
        return $this->archive->getStatusString();
    }

    public function save()
    {
        @$this->archive->close();
        $this->archive->open($this->filePath);
    }

    public function close()
    {
        @$this->archive->close();
    }

    private function getErrorMessage($resultCode)
    {
        switch ($resultCode) {
            case ZipArchive::ER_EXISTS:
                return 'ZipArchive::ER_EXISTS - File already exists.';
            case ZipArchive::ER_INCONS:
                return 'ZipArchive::ER_INCONS - Zip archive inconsistent.';
            case ZipArchive::ER_MEMORY:
                return 'ZipArchive::ER_MEMORY - Malloc failure.';
            case ZipArchive::ER_NOENT:
                return 'ZipArchive::ER_NOENT - No such file.';
            case ZipArchive::ER_NOZIP:
                return 'ZipArchive::ER_NOZIP - Not a zip archive.';
            case ZipArchive::ER_OPEN:
                return 'ZipArchive::ER_OPEN - Can\'t open file.';
            case ZipArchive::ER_READ:
                return 'ZipArchive::ER_READ - Read error.';
            case ZipArchive::ER_SEEK:
                return 'ZipArchive::ER_SEEK - Seek error.';
            default:
                return "An unknown error [{$resultCode}] has occurred.";
        }
    }
}
