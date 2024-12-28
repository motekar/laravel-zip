<?php

use Illuminate\Filesystem\Filesystem;
use Motekar\LaravelZip\Repositories\ArrayArchive;
use Motekar\LaravelZip\ZipBuilder;

beforeEach(function () {
    $this->file = Mockery::mock(new Filesystem);
    $this->archive = new ZipBuilder($this->file);
    $this->archive->make('foo', new ArrayArchive('foo', true));
});

afterEach(function () {
    Mockery::close();
});

test('make creates archive correctly', function () {
    expect($this->archive->getArchiveType())->toBe(ArrayArchive::class)
        ->and($this->archive->getFilePath())->toBe('foo');
});

test('make throws exception when directory creation fails', function () {
    $path = getcwd().time();

    $this->file->shouldReceive('makeDirectory')
        ->with($path, 0755, true)
        ->andReturn(false);

    $zip = new ZipBuilder($this->file);

    expect(fn () => $zip->make($path.DIRECTORY_SEPARATOR.'createMe.zip'))
        ->toThrow(RuntimeException::class, 'Failed to create folder');
});

test('add and get files work correctly', function () {
    $this->file->shouldReceive('isFile')->with('foo.bar')
        ->times(1)->andReturn(true);
    $this->file->shouldReceive('isFile')->with('foo')
        ->times(1)->andReturn(true);

    $this->archive->add('foo.bar');
    $this->archive->add('foo');

    expect($this->archive->getFileContent('foo'))->toBe('foo')
        ->and($this->archive->getFileContent('foo.bar'))->toBe('foo.bar');
});

test('add and get with array works correctly', function () {
    $this->file->shouldReceive('isFile')->with('foo.bar')
        ->times(1)->andReturn(true);
    $this->file->shouldReceive('isFile')->with('foo')
        ->times(1)->andReturn(true);

    $this->archive->add([
        'foo.bar',
        'foo',
    ]);

    expect($this->archive->getFileContent('foo'))->toBe('foo')
        ->and($this->archive->getFileContent('foo.bar'))->toBe('foo.bar');
});

test('add and get with custom filename array works correctly', function () {
    $this->file->shouldReceive('isFile')->with('foo.bar')
        ->times(1)->andReturn(true);
    $this->file->shouldReceive('isFile')->with('foo')
        ->times(1)->andReturn(true);

    $this->archive->add([
        'custom.bar' => 'foo.bar',
        'custom' => 'foo',
    ]);

    expect($this->archive->getFileContent('custom'))->toBe('custom')
        ->and($this->archive->getFileContent('custom.bar'))->toBe('custom.bar');
});

test('add and get with sub folder works correctly', function () {
    $this->file->shouldReceive('isFile')->with('/path/to/fooDir')
        ->once()->andReturn(false);

    $this->file->shouldReceive('files')->with('/path/to/fooDir')
        ->once()->andReturn(['fileInFooDir.bar', 'fileInFooDir.foo']);

    $this->file->shouldReceive('directories')->with('/path/to/fooDir')
        ->once()->andReturn(['fooSubdir']);

    $this->file->shouldReceive('files')->with('/path/to/fooDir/fooSubdir')
        ->once()->andReturn(['fileInFooDir.bar']);
    $this->file->shouldReceive('directories')->with('/path/to/fooDir/fooSubdir')
        ->once()->andReturn([]);

    $this->archive->folder('fooDir')
        ->add('/path/to/fooDir');

    expect($this->archive->getFileContent('fooDir/fileInFooDir.bar'))->toBe('fooDir/fileInFooDir.bar')
        ->and($this->archive->getFileContent('fooDir/fileInFooDir.foo'))->toBe('fooDir/fileInFooDir.foo')
        ->and($this->archive->getFileContent('fooDir/fooSubdir/fileInFooDir.bar'))->toBe('fooDir/fooSubdir/fileInFooDir.bar');
});

test('get file content throws exception for missing file', function () {
    expect(fn () => $this->archive->getFileContent('baz'))
        ->toThrow(Exception::class, 'The file "baz" cannot be found');
});

test('remove works correctly', function () {
    $this->file->shouldReceive('isFile')->with('foo')
        ->andReturn(true);

    $this->archive->add('foo');

    expect($this->archive->contains('foo'))->toBeTrue();

    $this->archive->remove('foo');

    expect($this->archive->contains('foo'))->toBeFalse();

    // Test removing multiple files
    $this->file->shouldReceive('isFile')->with('foo')
        ->andReturn(true);
    $this->file->shouldReceive('isFile')->with('fooBar')
        ->andReturn(true);

    $this->archive->add(['foo', 'fooBar']);

    expect($this->archive->contains('foo'))->toBeTrue()
        ->and($this->archive->contains('fooBar'))->toBeTrue();

    $this->archive->remove(['foo', 'fooBar']);

    expect($this->archive->contains('foo'))->toBeFalse()
        ->and($this->archive->contains('fooBar'))->toBeFalse();
});

test('extract whitelist works correctly', function () {
    $this->file->shouldReceive('isFile')->with('foo')->andReturn(true);
    $this->file->shouldReceive('isFile')->with('foo.log')->andReturn(true);

    $this->archive->add('foo')->add('foo.log');

    $this->file->shouldReceive('put')
        ->with(realpath('').DIRECTORY_SEPARATOR.'foo', 'foo');
    $this->file->shouldReceive('put')
        ->with(realpath('').DIRECTORY_SEPARATOR.'foo.log', 'foo.log');

    $this->archive->extractTo(getcwd(), ['foo'], ZipBuilder::WHITELIST);

    expect($this->file->isFile('foo'))->toBe(true)
        ->and($this->file->isFile('foo.log'))->toBe(true);
});

test('extract to throws exception when directory creation fails', function () {
    $path = getcwd().time();

    $this->file->shouldReceive('isFile')->with('foo.log')->andReturn(true);
    $this->file->shouldReceive('makeDirectory')
        ->with($path, 0755, true)
        ->andReturn(false);

    $this->archive->add('foo.log');

    $this->file->shouldNotReceive('put')
        ->with(realpath('').DIRECTORY_SEPARATOR.'foo.log', 'foo.log');

    expect(fn () => $this->archive->extractTo($path, ['foo'], ZipBuilder::WHITELIST))
        ->toThrow(RuntimeException::class, 'Failed to create folder');
});

test('navigation folder and home works correctly', function () {
    $this->archive->folder('foo/bar');
    expect($this->archive->getCurrentFolderPath())->toBe('foo/bar');

    $this->file->shouldReceive('isFile')->with('foo')->andReturn(true);
    $this->archive->add('foo');
    expect($this->archive->getFileContent('foo/bar/foo'))->toBe('foo/bar/foo');

    $this->file->shouldReceive('isFile')->with('bar')->andReturn(true);
    $this->archive->home()->add('bar');
    expect($this->archive->getFileContent('bar'))->toBe('bar');

    $this->file->shouldReceive('isFile')->with('baz/bar/bing')->andReturn(true);
    $this->archive->folder('test')->add('baz/bar/bing');
    expect($this->archive->getFileContent('test/bing'))->toBe('test/bing');
});

test('list files works correctly', function () {
    // Empty file test
    $this->file->shouldReceive('isFile')->with('foo.file')->andReturn(true);
    $this->file->shouldReceive('isFile')->with('bar.file')->andReturn(true);
    expect($this->archive->listFiles())->toBeEmpty();

    // Non-empty file test
    $this->archive->add('foo.file');
    $this->archive->add('bar.file');
    expect($this->archive->listFiles())->toBe(['foo.file', 'bar.file']);

    // Empty subdir test
    $this->file->shouldReceive('isFile')->with('/path/to/subDirEmpty')->andReturn(false);
    $this->file->shouldReceive('files')->with('/path/to/subDirEmpty')->andReturn([]);
    $this->file->shouldReceive('directories')->with('/path/to/subDirEmpty')->andReturn([]);
    $this->archive->folder('subDirEmpty')->add('/path/to/subDirEmpty');
    expect($this->archive->listFiles())->toBe(['foo.file', 'bar.file']);

    // Non-empty subdir test
    $this->file->shouldReceive('isFile')->with('/path/to/subDir')->andReturn(false);
    $this->file->shouldReceive('isFile')->with('sub.file')->andReturn(true);
    $this->file->shouldReceive('files')->with('/path/to/subDir')->andReturn(['sub.file']);
    $this->file->shouldReceive('directories')->with('/path/to/subDir')->andReturn([]);
    $this->archive->folder('subDir')->add('/path/to/subDir');
    expect($this->archive->listFiles())->toBe(['foo.file', 'bar.file', 'subDir/sub.file']);
});

test('list files with regex filter works correctly', function () {
    // Add root level files
    $this->file->shouldReceive('isFile')->with('foo.file')->andReturn(true);
    $this->file->shouldReceive('isFile')->with('bar.log')->andReturn(true);
    $this->archive->add('foo.file')->add('bar.log');

    // Add subdir files
    $this->file->shouldReceive('isFile')->with('/path/to/subDir')->andReturn(false);
    $this->file->shouldReceive('isFile')->with('sub.file')->andReturn(true);
    $this->file->shouldReceive('isFile')->with('anotherSub.log')->andReturn(true);
    $this->file->shouldReceive('files')->with('/path/to/subDir')->andReturn(['sub.file', 'anotherSub.log']);
    $this->file->shouldReceive('directories')->with('/path/to/subDir')->andReturn([]);
    $this->archive->folder('subDir')->add('/path/to/subDir');

    expect($this->archive->listFiles('/\.file$/i'))->toBe(['foo.file', 'subDir/sub.file']);
});

test('list files throws exception with invalid regex filter', function () {
    $this->file->shouldReceive('isFile')->with('foo.file')->andReturn(true);
    $this->archive->add('foo.file');

    $invalidPattern = 'asdasd';
    expect(fn () => $this->archive->listFiles($invalidPattern))
        ->toThrow(RuntimeException::class, 'regular expression match on \'foo.file\' failed with error. Please check if pattern is valid regular expression.');
});
