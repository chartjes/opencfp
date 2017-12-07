<?php

declare(strict_types=1);

/**
 * Copyright (c) 2013-2017 OpenCFP
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/opencfp/opencfp
 */

namespace OpenCFP\Test\Unit\Console\Command;

use Localheinz\Test\Util\Helper;
use OpenCFP\Console\Command\ClearCacheCommand;
use OpenCFP\PathInterface;
use org\bovigo\vfs;
use PHPUnit\Framework;
use Symfony\Component\Console;

/**
 * @covers \OpenCFP\Console\Command\ClearCacheCommand
 */
final class ClearCacheCommandTest extends Framework\TestCase
{
    use Helper;

    /**
     * @var string
     */
    private $root;

    protected function setUp()
    {
        $this->root = vfs\vfsStream::setup('cache');
    }

    /**
     * @test
     */
    public function isFinal()
    {
        $this->assertClassIsFinal(ClearCacheCommand::class);
    }

    /**
     * @test
     */
    public function extendsCommand()
    {
        $this->assertClassExtends(Console\Command\Command::class, ClearCacheCommand::class);
    }

    /**
     * @test
     */
    public function hasNameAndDescription()
    {
        $command = new ClearCacheCommand($this->createPathMock());

        $this->assertSame('cache:clear', $command->getName());
        $this->assertSame('Clears the caches', $command->getDescription());
    }

    /**
     * @test
     */
    public function executeRemovesFilesWithinCacheDirectories()
    {
        $accessors = [
            'cachePurifierPath',
            'cacheTwigPath',
        ];

        $directories = \array_combine(
            $accessors,
            \array_map(function () {
                return $this->createDirectoryWithFilesAndDirectories($this->root->url());
            }, $accessors)
        );

        $path = $this->createPathMock();

        foreach ($directories as $accessor => $directory) {
            $path
                ->expects($this->once())
                ->method($accessor)
                ->willReturn($directory);
        }

        $command = new ClearCacheCommand($path);

        $commandTester = new Console\Tester\CommandTester($command);

        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());

        $this->assertContains('Clearing caches', $commandTester->getDisplay());
        $this->assertContains('Cleared caches', $commandTester->getDisplay());

        foreach ($directories as $directory) {
            $this->assertDirectoryExists($directory);

            $filesAndDirectories = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $directory,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            $this->assertCount(0, $filesAndDirectories);
        }
    }

    /**
     * @return PathInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createPathMock(): PathInterface
    {
        return $this->createMock(PathInterface::class);
    }

    private function createDirectoryWithFilesAndDirectories(string $rootDirectory, int $currentDepth = 0, $maxDepth = 3)
    {
        $directory = $this->createDirectory($rootDirectory);

        for ($i = 0; $i < 5; ++$i) {
            $this->createFile($directory);
        }

        if ($currentDepth < $maxDepth) {
            return $this->createDirectoryWithFilesAndDirectories($directory, ++$currentDepth);
        }

        return $directory;
    }

    private function createDirectory(string $rootDirectory): string
    {
        $directory = \sprintf(
            '%s/%s',
            $rootDirectory,
            $this->faker()->word
        );

        \mkdir($directory, 0777, true);

        return $directory;
    }

    private function createFile(string $parentDirectory)
    {
        $fileName = $this->createFileName();

        $filePath = \sprintf(
            '%s/%s',
            $parentDirectory,
            $fileName
        );

        \file_put_contents(
            $filePath,
            $this->faker()->text
        );
    }

    private function createFileName(): string
    {
        $faker = $this->faker();

        $withExtension = $faker->boolean;

        if ($withExtension) {
            return \sprintf(
                '%s.%s',
                $faker->unique()->word,
                $faker->fileExtension
            );
        }

        return $faker->unique()->word;
    }
}
