<?php

use Illuminate\Support\Facades\File;
use Motekar\LaravelZip\Repositories\ZipRepository;
use Motekar\LaravelZip\ZipBuilder;

use function Motekar\LaravelZip\Support\zip;

beforeEach(function () {
    $this->targetPath = getTempPath('test.zip');
    File::delete($this->targetPath);
});

test('make creates archive correctly', function () {
    $zip = zip()->make($this->targetPath);

    expect($zip->getArchiveType())->toBe(ZipRepository::class)
        ->and($zip->getFilePath())->toBe($this->targetPath);

    $zip->close();
});

test('make throws exception when directory creation fails', function () {
    $path = getTempPath(time());

    File::partialMock()->shouldReceive('makeDirectory')
        ->with($path, 0755, true)
        ->andReturn(false);

    $zip = zip();

    expect(fn () => $zip->make($path . DIRECTORY_SEPARATOR . 'createMe.zip'))
        ->toThrow(RuntimeException::class, 'Failed to create folder');
});

test('add and get files work correctly', function () {
    $zip = zip()
        ->make($this->targetPath)
        ->add(getTestSupportPath('foo.txt'))
        ->add(getTestSupportPath('bar.txt'))
        ->save();

    expect($zip->getFileContent('foo.txt'))->toBe('foo')
        ->and($zip->getFileContent('bar.txt'))->toBe('bar');

    $zip->close();
});

test('add and get with array works correctly', function () {
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

test('add and get with custom filename array works correctly', function () {
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

test('add and get with sub folder works correctly', function () {
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

test('get file content throws exception for missing file', function () {
    $zip = zip()
        ->make($this->targetPath)
        ->add(getTestSupportPath('bar.txt'))
        ->save();

    expect(fn () => $zip->getFileContent('baz'))
        ->toThrow(Exception::class, 'The file "baz" cannot be found');

    $zip->close();
});

test('remove works correctly', function () {
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

test('extract whitelist works correctly', function () {
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

test('extract to throws exception when directory creation fails', function () {
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

// test('navigation folder and home works correctly', function () {
//     $this->archive->folder('foo/bar');
//     expect($this->archive->getCurrentFolderPath())->toBe('foo/bar');

//     $this->file->shouldReceive('isFile')->with('foo')->andReturn(true);
//     $this->archive->add('foo');
//     expect($this->archive->getFileContent('foo/bar/foo'))->toBe('foo/bar/foo');

//     $this->file->shouldReceive('isFile')->with('bar')->andReturn(true);
//     $this->archive->home()->add('bar');
//     expect($this->archive->getFileContent('bar'))->toBe('bar');

//     $this->file->shouldReceive('isFile')->with('baz/bar/bing')->andReturn(true);
//     $this->archive->folder('test')->add('baz/bar/bing');
//     expect($this->archive->getFileContent('test/bing'))->toBe('test/bing');
// });

// test('list files works correctly', function () {
//     // Empty file test
//     $this->file->shouldReceive('isFile')->with('foo.file')->andReturn(true);
//     $this->file->shouldReceive('isFile')->with('bar.file')->andReturn(true);
//     expect($this->archive->listFiles())->toBeEmpty();

//     // Non-empty file test
//     $this->archive->add('foo.file');
//     $this->archive->add('bar.file');
//     expect($this->archive->listFiles())->toBe(['foo.file', 'bar.file']);

//     // Empty subdir test
//     $this->file->shouldReceive('isFile')->with('/path/to/subDirEmpty')->andReturn(false);
//     $this->file->shouldReceive('files')->with('/path/to/subDirEmpty')->andReturn([]);
//     $this->file->shouldReceive('directories')->with('/path/to/subDirEmpty')->andReturn([]);
//     $this->archive->folder('subDirEmpty')->add('/path/to/subDirEmpty');
//     expect($this->archive->listFiles())->toBe(['foo.file', 'bar.file']);

//     // Non-empty subdir test
//     $this->file->shouldReceive('isFile')->with('/path/to/subDir')->andReturn(false);
//     $this->file->shouldReceive('isFile')->with('sub.file')->andReturn(true);
//     $this->file->shouldReceive('files')->with('/path/to/subDir')->andReturn(['sub.file']);
//     $this->file->shouldReceive('directories')->with('/path/to/subDir')->andReturn([]);
//     $this->archive->folder('subDir')->add('/path/to/subDir');
//     expect($this->archive->listFiles())->toBe(['foo.file', 'bar.file', 'subDir/sub.file']);
// });

// test('list files with regex filter works correctly', function () {
//     // Add root level files
//     $this->file->shouldReceive('isFile')->with('foo.file')->andReturn(true);
//     $this->file->shouldReceive('isFile')->with('bar.log')->andReturn(true);
//     $this->archive->add('foo.file')->add('bar.log');

//     // Add subdir files
//     $this->file->shouldReceive('isFile')->with('/path/to/subDir')->andReturn(false);
//     $this->file->shouldReceive('isFile')->with('sub.file')->andReturn(true);
//     $this->file->shouldReceive('isFile')->with('anotherSub.log')->andReturn(true);
//     $this->file->shouldReceive('files')->with('/path/to/subDir')->andReturn(['sub.file', 'anotherSub.log']);
//     $this->file->shouldReceive('directories')->with('/path/to/subDir')->andReturn([]);
//     $this->archive->folder('subDir')->add('/path/to/subDir');

//     expect($this->archive->listFiles('/\.file$/i'))->toBe(['foo.file', 'subDir/sub.file']);
// });

// test('list files throws exception with invalid regex filter', function () {
//     $this->file->shouldReceive('isFile')->with('foo.file')->andReturn(true);
//     $this->archive->add('foo.file');

//     $invalidPattern = 'asdasd';
//     expect(fn () => $this->archive->listFiles($invalidPattern))
//         ->toThrow(RuntimeException::class, 'regular expression match on \'foo.file\' failed with error. Please check if pattern is valid regular expression.');
// });
