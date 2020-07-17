<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\Tests\Unit\Resource;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\ResourceCompressor;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Testcase for the ResourceCompressor class
 */
class ResourceCompressorTest extends BaseTestCase
{
    /**
     * Restore Environment after the test
     * @var bool
     */
    protected $backupEnvironment = true;

    /**
     * @var ResourceCompressor|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface
     */
    protected $subject;

    /**
     * Set up the test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->getAccessibleMock(ResourceCompressor::class, ['compressCssFile', 'compressJsFile', 'createMergedCssFile', 'createMergedJsFile', 'getFilenameFromMainDir', 'checkBaseDirectory']);
    }

    /**
     * @return array
     */
    public function cssFixStatementsDataProvider(): array
    {
        return [
            'nothing to do - no charset/import/namespace' => [
                'body { background: #ffffff; }',
                'body { background: #ffffff; }'
            ],
            'import in front' => [
                '@import url(http://www.example.com/css); body { background: #ffffff; }',
                'LF/* moved by compressor */LF@import url(http://www.example.com/css);LF/* moved by compressor */LFbody { background: #ffffff; }'
            ],
            'import in back, without quotes' => [
                'body { background: #ffffff; } @import url(http://www.example.com/css);',
                'LF/* moved by compressor */LF@import url(http://www.example.com/css);LF/* moved by compressor */LFbody { background: #ffffff; }'
            ],
            'import in back, with double-quotes' => [
                'body { background: #ffffff; } @import url("http://www.example.com/css");',
                'LF/* moved by compressor */LF@import url("http://www.example.com/css");LF/* moved by compressor */LFbody { background: #ffffff; }'
            ],
            'import in back, with single-quotes' => [
                'body { background: #ffffff; } @import url(\'http://www.example.com/css\');',
                'LF/* moved by compressor */LF@import url(\'http://www.example.com/css\');LF/* moved by compressor */LFbody { background: #ffffff; }'
            ],
            'import in middle and back, without quotes' => [
                'body { background: #ffffff; } @import url(http://www.example.com/A); div { background: #000; } @import url(http://www.example.com/B);',
                'LF/* moved by compressor */LF@import url(http://www.example.com/A);@import url(http://www.example.com/B);LF/* moved by compressor */LFbody { background: #ffffff; }  div { background: #000; }'
            ],
            'charset declaration is unique' => [
                'body { background: #ffffff; } @charset "UTF-8"; div { background: #000; }; @charset "UTF-8";',
                '@charset "UTF-8";LF/* moved by compressor */LFbody { background: #ffffff; }  div { background: #000; };'
            ],
            'order of charset, namespace and import is correct' => [
                'body { background: #ffffff; } @charset "UTF-8"; div { background: #000; }; @import "file2.css"; @namespace url(http://www.w3.org/1999/xhtml);',
                '@charset "UTF-8";LF/* moved by compressor */LF@namespace url(http://www.w3.org/1999/xhtml);LF/* moved by compressor */LF@import "file2.css";LF/* moved by compressor */LFbody { background: #ffffff; }  div { background: #000; };'
            ],
        ];
    }

    /**
     * @test
     * @dataProvider cssFixStatementsDataProvider
     * @param string $input
     * @param string $expected
     */
    public function cssFixStatementsMovesStatementsToTopIfNeeded($input, $expected): void
    {
        $result = $this->subject->_call('cssFixStatements', $input);
        $resultWithReadableLinefeed = str_replace(LF, 'LF', $result);
        $this->assertEquals($expected, $resultWithReadableLinefeed);
    }

    /**
     * @test
     */
    public function compressedCssFileIsFlaggedToNotCompressAgain(): void
    {
        $fileName = 'fooFile.css';
        $compressedFileName = $fileName . '.gzip';
        $testFileFixture = [
            $fileName => [
                'file' => $fileName,
                'compress' => true,
            ]
        ];
        $this->subject->expects($this->once())
            ->method('compressCssFile')
            ->with($fileName)
            ->will($this->returnValue($compressedFileName));

        $result = $this->subject->compressCssFiles($testFileFixture);

        $this->assertArrayHasKey($compressedFileName, $result);
        $this->assertArrayHasKey('compress', $result[$compressedFileName]);
        $this->assertFalse($result[$compressedFileName]['compress']);
    }

    /**
     * @test
     */
    public function compressedJsFileIsFlaggedToNotCompressAgain(): void
    {
        $fileName = 'fooFile.js';
        $compressedFileName = $fileName . '.gzip';
        $testFileFixture = [
            $fileName => [
                'file' => $fileName,
                'compress' => true,
            ]
        ];
        $this->subject->expects($this->once())
            ->method('compressJsFile')
            ->with($fileName)
            ->will($this->returnValue($compressedFileName));

        $result = $this->subject->compressJsFiles($testFileFixture);

        $this->assertArrayHasKey($compressedFileName, $result);
        $this->assertArrayHasKey('compress', $result[$compressedFileName]);
        $this->assertFalse($result[$compressedFileName]['compress']);
    }

    /**
     * @test
     */
    public function concatenatedCssFileIsFlaggedToNotConcatenateAgain(): void
    {
        $fileName = 'fooFile.css';
        $concatenatedFileName = 'merged_' . $fileName;
        $testFileFixture = [
            $fileName => [
                'file' => $fileName,
                'excludeFromConcatenation' => false,
                'media' => 'all',
            ]
        ];
        $this->subject->expects($this->once())
            ->method('createMergedCssFile')
            ->will($this->returnValue($concatenatedFileName));

        $result = $this->subject->concatenateCssFiles($testFileFixture);

        $this->assertArrayHasKey($concatenatedFileName, $result);
        $this->assertArrayHasKey('excludeFromConcatenation', $result[$concatenatedFileName]);
        $this->assertTrue($result[$concatenatedFileName]['excludeFromConcatenation']);
    }

    /**
     * @test
     */
    public function concatenatedCssFilesAreSeparatedByMediaType(): void
    {
        $allFileName = 'allFile.css';
        $screenFileName1 = 'screenFile.css';
        $screenFileName2 = 'screenFile2.css';
        $testFileFixture = [
            $allFileName => [
                'file' => $allFileName,
                'excludeFromConcatenation' => false,
                'media' => 'all',
            ],
            // use two screen files to check if they are merged into one, even with a different media type
            $screenFileName1 => [
                'file' => $screenFileName1,
                'excludeFromConcatenation' => false,
                'media' => 'screen',
            ],
            $screenFileName2 => [
                'file' => $screenFileName2,
                'excludeFromConcatenation' => false,
                'media' => 'screen',
            ],
        ];
        $this->subject->expects($this->exactly(2))
            ->method('createMergedCssFile')
            ->will($this->onConsecutiveCalls(
                $this->returnValue('merged_' . $allFileName),
                $this->returnValue('merged_' . $screenFileName1)
            ));

        $result = $this->subject->concatenateCssFiles($testFileFixture);

        $this->assertEquals([
            'merged_' . $allFileName,
            'merged_' . $screenFileName1
        ], array_keys($result));
        $this->assertEquals('all', $result['merged_' . $allFileName]['media']);
        $this->assertEquals('screen', $result['merged_' . $screenFileName1]['media']);
    }

    /**
     * @test
     */
    public function concatenatedCssFilesObeyForceOnTopOption(): void
    {
        $screen1FileName = 'screen1File.css';
        $screen2FileName = 'screen2File.css';
        $screen3FileName = 'screen3File.css';
        $testFileFixture = [
            $screen1FileName => [
                'file' => $screen1FileName,
                'excludeFromConcatenation' => false,
                'media' => 'screen',
            ],
            $screen2FileName => [
                'file' => $screen2FileName,
                'excludeFromConcatenation' => false,
                'media' => 'screen',
            ],
            $screen3FileName => [
                'file' => $screen3FileName,
                'excludeFromConcatenation' => false,
                'forceOnTop' => true,
                'media' => 'screen',
            ],
        ];
        // Replace mocked method getFilenameFromMainDir by passthrough callback
        $this->subject->expects($this->any())->method('getFilenameFromMainDir')->willReturnArgument(0);
        $this->subject->expects($this->once())
            ->method('createMergedCssFile')
            ->with($this->equalTo([$screen3FileName, $screen1FileName, $screen2FileName]));

        $this->subject->concatenateCssFiles($testFileFixture);
    }

    /**
     * @test
     */
    public function concatenatedCssFilesObeyExcludeFromConcatenation(): void
    {
        $screen1FileName = 'screen1File.css';
        $screen2FileName = 'screen2File.css';
        $screen3FileName = 'screen3File.css';
        $testFileFixture = [
            $screen1FileName => [
                'file' => $screen1FileName,
                'excludeFromConcatenation' => false,
                'media' => 'screen',
            ],
            $screen2FileName => [
                'file' => $screen2FileName,
                'excludeFromConcatenation' => true,
                'media' => 'screen',
            ],
            $screen3FileName => [
                'file' => $screen3FileName,
                'excludeFromConcatenation' => false,
                'media' => 'screen',
            ],
        ];
        $this->subject->expects($this->any())->method('getFilenameFromMainDir')->willReturnArgument(0);
        $this->subject->expects($this->once())
            ->method('createMergedCssFile')
            ->with($this->equalTo([$screen1FileName, $screen3FileName]))
            ->will($this->returnValue('merged_screen'));

        $result = $this->subject->concatenateCssFiles($testFileFixture);
        $this->assertEquals([
            $screen2FileName,
            'merged_screen'
        ], array_keys($result));
        $this->assertEquals('screen', $result[$screen2FileName]['media']);
        $this->assertEquals('screen', $result['merged_screen']['media']);
    }

    /**
     * @test
     */
    public function concatenateJsFileIsFlaggedToNotConcatenateAgain(): void
    {
        $fileName = 'fooFile.js';
        $concatenatedFileName = 'merged_' . $fileName;
        $testFileFixture = [
            $fileName => [
                'file' => $fileName,
                'excludeFromConcatenation' => false,
                'section' => 'top',
            ]
        ];
        $this->subject->expects($this->once())
            ->method('createMergedJsFile')
            ->will($this->returnValue($concatenatedFileName));

        $result = $this->subject->concatenateJsFiles($testFileFixture);

        $this->assertArrayHasKey($concatenatedFileName, $result);
        $this->assertArrayHasKey('excludeFromConcatenation', $result[$concatenatedFileName]);
        $this->assertTrue($result[$concatenatedFileName]['excludeFromConcatenation']);
    }

    /**
     * @return array
     */
    public function concatenateJsFileAsyncDataProvider(): array
    {
        return [
            'all files have no async' => [
                [
                    [
                        'file' => 'file1.js',
                        'excludeFromConcatenation' => false,
                        'section' => 'top',
                    ],
                    [
                        'file' => 'file2.js',
                        'excludeFromConcatenation' => false,
                        'section' => 'top',
                    ],
                ],
                false
            ],
            'all files have async false' => [
                [
                    [
                        'file' => 'file1.js',
                        'excludeFromConcatenation' => false,
                        'section' => 'top',
                        'async' => false,
                    ],
                    [
                        'file' => 'file2.js',
                        'excludeFromConcatenation' => false,
                        'section' => 'top',
                        'async' => false,
                    ],
                ],
                false
            ],
            'all files have async true' => [
                [
                    [
                        'file' => 'file1.js',
                        'excludeFromConcatenation' => false,
                        'section' => 'top',
                        'async' => true,
                    ],
                    [
                        'file' => 'file2.js',
                        'excludeFromConcatenation' => false,
                        'section' => 'top',
                        'async' => true,
                    ],
                ],
                true
            ],
            'one file async true and one file async false' => [
                [
                    [
                        'file' => 'file1.js',
                        'excludeFromConcatenation' => false,
                        'section' => 'top',
                        'async' => true,
                    ],
                    [
                        'file' => 'file2.js',
                        'excludeFromConcatenation' => false,
                        'section' => 'top',
                        'async' => false,
                    ],
                ],
                false
            ],
            'one file async true and one file async false but is excluded form concatenation' => [
                [
                    [
                        'file' => 'file1.js',
                        'excludeFromConcatenation' => false,
                        'section' => 'top',
                        'async' => true,
                    ],
                    [
                        'file' => 'file2.js',
                        'excludeFromConcatenation' => true,
                        'section' => 'top',
                        'async' => false,
                    ],
                ],
                true
            ],
            'one file async false and one file async true but is excluded form concatenation' => [
                [
                    [
                        'file' => 'file1.js',
                        'excludeFromConcatenation' => false,
                        'section' => 'top',
                        'async' => false,
                    ],
                    [
                        'file' => 'file2.js',
                        'excludeFromConcatenation' => true,
                        'section' => 'top',
                        'async' => true,
                    ],
                ],
                false
            ],
        ];
    }

    /**
     * @test
     * @dataProvider concatenateJsFileAsyncDataProvider
     * @param string $input
     * @param bool $expected
     */
    public function concatenateJsFileAddsAsyncPropertyIfAllFilesAreAsync(array $input, bool $expected): void
    {
        $concatenatedFileName = 'merged_foo.js';
        $this->subject->expects($this->once())
            ->method('createMergedJsFile')
            ->will($this->returnValue($concatenatedFileName));

        $result = $this->subject->concatenateJsFiles($input);

        $this->assertSame($expected, $result[$concatenatedFileName]['async']);
    }

    /**
     * @return array
     */
    public function calcStatementsDataProvider(): array
    {
        return [
            'simple calc' => [
                'calc(100% - 3px)',
                'calc(100% - 3px)',
            ],
            'complex calc with parentheses at the beginning' => [
                'calc((100%/20) - 2*3px)',
                'calc((100%/20) - 2*3px)',
            ],
            'complex calc with parentheses at the end' => [
                'calc(100%/20 - 2*3px - (200px + 3%))',
                'calc(100%/20 - 2*3px - (200px + 3%))',
            ],
            'complex calc with many parentheses' => [
                'calc((100%/20) - (2 * (3px - (200px + 3%))))',
                'calc((100%/20) - (2 * (3px - (200px + 3%))))',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider calcStatementsDataProvider
     * @param string $input
     * @param string $expected
     */
    public function calcFunctionMustRetainWhitespaces($input, $expected): void
    {
        $result = $this->subject->_call('compressCssString', $input);
        $this->assertSame($expected, trim($result));
    }

    /**
     * @return array
     */
    public function compressCssFileContentDataProvider(): array
    {
        $path = __DIR__ . '/ResourceCompressorTest/Fixtures/';
        return [
            // File. Tests:
            // - Stripped comments and white-space.
            // - Retain white-space in selectors. (http://drupal.org/node/472820)
            // - Retain pseudo-selectors. (http://drupal.org/node/460448)
            0 => [
                $path . 'css_input_without_import.css',
                $path . 'css_input_without_import.css.optimized.css'
            ],
            // File. Tests:
            // - Retain comment hacks.
            2 => [
                $path . 'comment_hacks.css',
                $path . 'comment_hacks.css.optimized.css'
            ], /*
            // File. Tests:
            // - Any @charset declaration at the beginning of a file should be
            //   removed without breaking subsequent CSS.*/
            6 => [
                $path . 'charset_sameline.css',
                $path . 'charset.css.optimized.css'
            ],
            7 => [
                $path . 'charset_newline.css',
                $path . 'charset.css.optimized.css'
            ],
        ];
    }

    /**
     * Tests optimizing a CSS asset group.
     *
     * @test
     * @dataProvider compressCssFileContentDataProvider
     * @param string $cssFile
     * @param string $expected
     */
    public function compressCssFileContent($cssFile, $expected): void
    {
        $cssContent = file_get_contents($cssFile);
        $compressedCss = $this->subject->_call('compressCssString', $cssContent);
        // we have to fix relative paths, if we aren't working on a file in our target directory
        $relativeFilename = str_replace(Environment::getPublicPath() . '/', '', $cssFile);
        if (strpos($relativeFilename, $this->subject->_get('targetDirectory')) === false) {
            $compressedCss = $this->subject->_call('cssFixRelativeUrlPaths', $compressedCss, PathUtility::dirname($relativeFilename) . '/');
        }
        $this->assertEquals(file_get_contents($expected), $compressedCss, 'Group of file CSS assets optimized correctly.');
    }

    /**
     * @return array
     */
    public function getVariousFilenamesFromMainDirInBackendContextDataProvider(): array
    {
        return [
            // Get filename using EXT:
            [
                'EXT:core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css',
                'sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css'
            ],
            // Get filename using relative path
            [
                'typo3/sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css',
                'sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css'
            ],
            [
                'sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css',
                'sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css'
            ],
            [
                'typo3temp/assets/compressed/.htaccess',
                '../typo3temp/assets/compressed/.htaccess'
            ],
            // Get filename using absolute path
            [
                Environment::getPublicPath() . '/typo3/sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css',
                'sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css'
            ],
            // Get filename using docroot relative path
            [
                '/typo3/sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css',
                'sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css'
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getVariousFilenamesFromMainDirInBackendContextDataProvider
     * @param string $filename input that will be fired on the extension
     * @param string $expected
     */
    public function getVariousFilenamesFromMainDirInBackendContext(string $filename, string $expected)
    {
        $rootPath = \dirname($_SERVER['SCRIPT_NAME']);
        $this->subject = $this->getAccessibleMock(ResourceCompressor::class, ['dummy']);
        $this->subject->setRootPath($rootPath . '/');

        $relativeToRootPath = $this->subject->_call('getFilenameFromMainDir', $filename);
        $this->assertSame($expected, $relativeToRootPath, 'Path to the file relative to the path converted correctly.');
    }

    /**
     * @return array
     */
    public function getVariousFilenamesFromMainDirInFrontendContextDataProvider(): array
    {
        return [
            // Get filename using EXT:
            [
                'EXT:core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css',
                'typo3/sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css'
            ],
            // Get filename using relative path
            [
                'typo3/sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css',
                'typo3/sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css'
            ],
            [
                'typo3temp/assets/compressed/.htaccess',
                'typo3temp/assets/compressed/.htaccess'
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getVariousFilenamesFromMainDirInFrontendContextDataProvider
     * @param string $filename input that will be fired on the extension
     * @param string $expected
     */
    public function getVariousFilenamesFromMainDirInFrontendContext(string $filename, string $expected)
    {
        Environment::initialize(
            Environment::getContext(),
            true,
            false,
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            PATH_site . 'index.php',
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );
        $_SERVER['ORIG_SCRIPT_NAME'] = '/index.php';
        $this->subject = $this->getAccessibleMock(ResourceCompressor::class, ['dummy']);
        $this->subject->setRootPath(PATH_site);

        $relativeToRootPath = $this->subject->_call('getFilenameFromMainDir', $filename);
        $this->assertSame($expected, $relativeToRootPath, 'Path to the file relative to the path converted correctly.');
    }

    public function getFilenamesFromMainDirInBackendContextInSubfolderDataProvider(): array
    {
        $subfolderFake = basename(Environment::getPublicPath());
        return [
            // Get filename using absolute path
            [
                Environment::getPublicPath() . '/typo3/sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css',
                'sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css'
            ],
            // Get filename using docroot relative path
            [
                '/' . $subfolderFake . '/typo3/sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css',
                'sysext/core/Tests/Unit/Resource/ResourceCompressorTest/Fixtures/charset.css'
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getFilenamesFromMainDirInBackendContextInSubfolderDataProvider
     * @param string $filename
     * @param string $expected
     */
    public function getFilenamesFromMainDirInBackendContextWithSubFolder(string $filename, string $expected): void
    {
        // getCurrentScript() called by PathUtility::getRelativePathTo() is usually something
        // like '.../bin/phpunit' in testing context, but we want .../typo3/index.php as entry
        // script point here to fake the backend call.
        $bePath = Environment::getBackendPath();
        $subfolderFake = basename(Environment::getPublicPath());
        $_SERVER['ORIG_SCRIPT_NAME'] = '/' . $subfolderFake . '/typo3/index.php';
        Environment::initialize(
            Environment::getContext(),
            true,
            false,
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            $bePath . '/index.php',
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );
        $subject = $this->getAccessibleMock(ResourceCompressor::class, ['dummy']);
        $subject->setRootPath($bePath . '/');
        $relativeToRootPath = $subject->_call('getFilenameFromMainDir', $filename);
        self::assertSame($expected, $relativeToRootPath);
    }

    /**
     * @test
     */
    public function deferJavascriptIsNotConcatenated(): void
    {
        $fileName = 'fooFile.js';
        $concatenatedFileName = 'merged_' . $fileName;
        $testFileFixture = [
            $fileName => [
                'file' => $fileName,
                'defer' => true,
                'section' => 'top',
            ]
        ];

        $result = $this->subject->concatenateJsFiles($testFileFixture);

        self::assertArrayNotHasKey($concatenatedFileName, $result);
        self::assertTrue($result[$fileName]['defer']);
    }
}
