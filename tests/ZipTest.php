<?php

use Illuminate\Support\Facades\File;
use Motekar\LaravelZip\Repositories\ZipRepository;
use Motekar\LaravelZip\ZipBuilder;

use function Motekar\LaravelZip\Support\zip;

beforeEach(function () {
    $this->targetPath = getTempPath('test.zip');
    File::delete($this->targetPath);
});

it('should create archive correctly', function () {
    $zip = zip()->make($this->targetPath);

    expect($zip->getArchiveType())->toBe(ZipRepository::class)
        ->and($zip->getFilePath())->toBe($this->targetPath);

    $zip->close();
});

it('should throw exception when directory creation fails', function () {
    $path = getTempPath(time());

    File::partialMock()->shouldReceive('makeDirectory')
        ->with($path, 0755, true)
        ->andReturn(false);

    $zip = zip();

    expect(fn () => $zip->make($path . DIRECTORY_SEPARATOR . 'createMe.zip'))
        ->toThrow(RuntimeException::class, 'Failed to create folder');
});

it('should add and get files correctly', function () {
    $zip = zip()
        ->make($this->targetPath)
        ->add(getTestSupportPath('foo.txt'))
        ->add(getTestSupportPath('bar.txt'))
        ->save();

    expect($zip->getFileContent('foo.txt'))->toBe('foo')
        ->and($zip->getFileContent('bar.txt'))->toBe('bar');

    $zip->close();
});

it('should add and get files with array correctly', function () {
    $zip = zip()
        ->make($this->targetPath)
        ->add([
            getTestSupportPath('foo.txt'),
            getTestSupportPath('bar.txt'),
        ])
        ->save();

    expect($zip->getFileContent('foo.txt'))->toBe('foo')
        ->and($zip->getFileContent('bar.txt'))->toBe('bar');

    $zip->close();
});

it('should add and get files with custom filename array correctly', function () {
    $zip = zip()
        ->make($this->targetPath)
        ->add([
            'custom.foo' => getTestSupportPath('foo.txt'),
            'custom.bar' => getTestSupportPath('bar.txt'),
        ])
        ->save();

    expect($zip->getFileContent('custom.foo'))->toBe('foo')
        ->and($zip->getFileContent('custom.bar'))->toBe('bar');

    $zip->close();
});

it('should add and get files with sub folder correctly', function () {
    $zip = zip()
        ->make($this->targetPath)
        ->folder('foo')
        ->add(getTestSupportPath('foo'))
        ->save();

    expect($zip->getFileContent('foo/foo.txt'))->toBe('foo/foo')
        ->and($zip->getFileContent('foo/bar.txt'))->toBe('foo/bar')
        ->and($zip->getFileContent('foo/bar/foo.txt'))->toBe('foo/bar/foo');

    $zip->close();
});

it('should throw exception when getting content of missing file', function () {
    $zip = zip()
        ->make($this->targetPath)
        ->add(getTestSupportPath('bar.txt'))
        ->save();

    expect(fn () => $zip->getFileContent('baz'))
        ->toThrow(Exception::class, 'The file "baz" cannot be found');

    $zip->close();
});

it('should remove files correctly', function () {
    $zip = zip()
        ->make($this->targetPath)
        ->add([
            getTestSupportPath('foo.txt'),
            getTestSupportPath('bar.txt'),
        ])
        ->save();

    expect($zip->contains('foo.txt'))->toBeTrue();

    $zip
        ->remove('foo.txt')
        ->save();

    expect($zip->contains('foo.txt'))->toBeFalse();

    $zip
        ->add([
            'foo2.txt' => getTestSupportPath('foo.txt'),
            'bar2.txt' => getTestSupportPath('bar.txt'),
        ])
        ->save();

    expect($zip->contains('foo2.txt'))->toBeTrue()
        ->and($zip->contains('bar2.txt'))->toBeTrue();

    $zip
        ->remove(['foo2.txt', 'bar2.txt'])
        ->save();

    expect($zip->contains('foo2.txt'))->toBeFalse()
        ->and($zip->contains('bar2.txt'))->toBeFalse();

    $zip->close();
});

it('should extract whitelist files correctly', function () {
    $zip = zip()
        ->make($this->targetPath)
        ->add([
            getTestSupportPath('foo.txt'),
            getTestSupportPath('foo.log'),
        ])
        ->save();
    $extractPath = getTempPath('extracted');

    $zip->extractTo($extractPath, ['foo'], ZipBuilder::WHITELIST);

    expect(File::exists($extractPath . '/foo.txt'))->toBe(true)
        ->and(File::exists($extractPath . '/foo.log'))->toBe(true);

    $zip->close();
});

it('should throw exception when directory creation fails during extraction', function () {
    $zip = zip()
        ->make($this->targetPath)
        ->add([
            getTestSupportPath('foo.txt'),
            getTestSupportPath('foo.log'),
        ])
        ->save();
    $extractPath = getTempPath('extracted');

    File::partialMock()->shouldReceive('makeDirectory')
        ->with($extractPath, 0755, true)
        ->andReturn(false);

    File::partialMock()->shouldNotReceive('put')
        ->with(realpath('') . DIRECTORY_SEPARATOR . 'foo.log', 'foo.log');

    expect(fn () => $zip->extractTo($extractPath, ['foo'], ZipBuilder::WHITELIST))
        ->toThrow(RuntimeException::class, 'Failed to create folder');
});

it('should navigate folders and return home correctly', function () {
    $zip = zip()->make($this->targetPath);

    $zip->folder('foo/bar');
    expect($zip->getCurrentFolderPath())->toBe('foo/bar');

    $zip->add(getTestSupportPath('foo.txt'))->save();
    expect($zip->getFileContent('foo/bar/foo.txt'))->toBe('foo');

    $zip->home()->add(getTestSupportPath('bar.txt'))->save();
    expect($zip->getFileContent('bar.txt'))->toBe('bar');

    $zip->folder('test')->add(getTestSupportPath('foo/bar/foo.txt'))->save();
    expect($zip->getFileContent('test/foo.txt'))->toBe('foo/bar/foo');

    $zip->close();
});

it('should list files correctly', function () {
    $zip = zip()->make($this->targetPath);

    // Test empty archive
    expect($zip->listFiles())->toBeEmpty();

    // Add files and test listing
    $zip->add([
        getTestSupportPath('foo.txt'),
        getTestSupportPath('bar.txt'),
    ])->save();
    expect($zip->listFiles())->toBe(['foo.txt', 'bar.txt']);

    // Test empty subdirectory
    if (! File::exists(getTestSupportPath('emptyDir'))) {
        File::makeDirectory(getTestSupportPath('emptyDir'));
    }
    $zip->folder('emptyDir')->add(getTestSupportPath('emptyDir'))->save();
    expect($zip->listFiles())->toBe(['foo.txt', 'bar.txt']);

    // Test non-empty subdirectory
    $zip->folder('foo')->add(getTestSupportPath('foo'))->save();
    expect($zip->listFiles())->toBe([
        'foo.txt',
        'bar.txt',
        'foo/bar.txt',
        'foo/foo.txt',
        'foo/bar/foo.txt',
    ]);

    $zip->close();
});

it('should list files with regex filter correctly', function () {
    $zip = zip()->make($this->targetPath);

    // Add root level files
    $zip->add([
        getTestSupportPath('foo.txt'),
        getTestSupportPath('foo.log'),
    ])->save();

    // Add subdir files
    $zip->folder('subDir')->add(getTestSupportPath('foo'))->save();

    // Test regex filter for .txt files
    expect($zip->listFiles('/\.txt$/i'))->toBe([
        'foo.txt',
        'subDir/bar.txt',
        'subDir/foo.txt',
        'subDir/bar/foo.txt',
    ]);

    $zip->close();
});

it('should throw exception when listing files with invalid regex filter', function () {
    $zip = zip()->make($this->targetPath)
        ->add(getTestSupportPath('foo.txt'))
        ->save();

    $invalidPattern = 'asdasd';
    expect(fn () => $zip->listFiles($invalidPattern))
        ->toThrow(RuntimeException::class, "regular expression match on 'foo.txt' failed with error. Please check if pattern is valid regular expression.");

    $zip->close();
});
