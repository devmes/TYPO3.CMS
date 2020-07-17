<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\Tests\Unit\Configuration\TypoScript\ConditionMatching;

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

use Prophecy\Argument;
use TYPO3\CMS\Backend\Configuration\TypoScript\ConditionMatching\ConditionMatcher;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\TypoScript\ConditionMatching\AbstractConditionMatcher;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test cases
 */
class AbstractConditionMatcherTest extends UnitTestCase
{
    /**
     * @var ApplicationContext
     */
    protected $backupApplicationContext;

    /**
     * @var AbstractConditionMatcher|\PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface
     */
    protected $conditionMatcher;

    /**
     * @var \ReflectionMethod
     */
    protected $evaluateConditionCommonMethod;

    /**
     * @var \ReflectionMethod
     */
    protected $evaluateExpressionMethod;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        require_once 'Fixtures/ConditionMatcherUserFuncs.php';

        $this->resetSingletonInstances = true;
        $GLOBALS['TYPO3_REQUEST'] = new ServerRequest();
        $cacheFrontendProphecy = $this->prophesize(FrontendInterface::class);
        $cacheFrontendProphecy->has(Argument::any())->willReturn(false);
        $cacheFrontendProphecy->set(Argument::any(), Argument::any())->willReturn(null);
        $cacheManagerProphecy = $this->prophesize(CacheManager::class);
        $cacheManagerProphecy->getCache('cache_core')->willReturn($cacheFrontendProphecy->reveal());
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerProphecy->reveal());

        $packageManagerProphecy = $this->prophesize(PackageManager::class);
        $corePackageProphecy = $this->prophesize(PackageInterface::class);
        $corePackageProphecy->getPackagePath()->willReturn(__DIR__ . '/../../../../../../../sysext/core/');
        $packageManagerProphecy->getActivePackages()->willReturn([
            $corePackageProphecy->reveal()
        ]);
        GeneralUtility::setSingletonInstance(PackageManager::class, $packageManagerProphecy->reveal());

        $this->initConditionMatcher();
        $this->backupApplicationContext = GeneralUtility::getApplicationContext();
    }

    protected function initConditionMatcher()
    {
        // test the abstract methods via the backend condition matcher
        $this->conditionMatcher = $this->getAccessibleMock(ConditionMatcher::class, ['determineRootline']);
        $this->evaluateConditionCommonMethod = new \ReflectionMethod(AbstractConditionMatcher::class, 'evaluateConditionCommon');
        $this->evaluateConditionCommonMethod->setAccessible(true);
        $this->evaluateExpressionMethod = new \ReflectionMethod(AbstractConditionMatcher::class, 'evaluateExpression');
        $this->evaluateExpressionMethod->setAccessible(true);
        $loggerProphecy = $this->prophesize(Logger::class);
        $this->conditionMatcher->setLogger($loggerProphecy->reveal());
    }

    /**
     * Tear down
     */
    protected function tearDown(): void
    {
        Fixtures\GeneralUtilityFixture::setApplicationContext($this->backupApplicationContext);
        parent::tearDown();
    }

    /**
     * @return array
     */
    public function datesConditionDataProvider(): array
    {
        return [
            '[dayofmonth = 17]' => ['dayofmonth', 17, true],
            '[dayofweek = 3]' => ['dayofweek', 3, true],
            '[dayofyear = 16]' => ['dayofyear', 16, true],
            '[hour = 11]' => ['hour', 11, true],
            '[minute = 4]' => ['minute', 4, true],
            '[month = 1]' => ['month', 1, true],
            '[year = 1945]' => ['year', 1945, true],
        ];
    }

    /**
     * @test
     * @dataProvider datesConditionDataProvider
     * @param string $expressionMethod
     * @param int $expressionValue
     * @param bool $expected
     */
    public function checkConditionMatcherForDates(string $expressionMethod, int $expressionValue, bool $expected): void
    {
        $GLOBALS['SIM_EXEC_TIME'] = mktime(11, 4, 0, 1, 17, 1945);
        $this->assertSame($expected, $this->evaluateConditionCommonMethod->invokeArgs(
            $this->conditionMatcher,
            [$expressionMethod, $expressionValue]
        ));
    }

    /**
     * @return array
     */
    public function datesFunctionDataProvider(): array
    {
        return [
            '[dayofmonth = 17]' => ['j', 17, true],
            '[dayofweek = 3]' => ['w', 3, true],
            '[dayofyear = 16]' => ['z', 16, true],
            '[hour = 11]' => ['G', 11, true],
            '[minute = 4]' => ['i', 4, true],
            '[month = 1]' => ['n', 1, true],
            '[year = 1945]' => ['Y', 1945, true],
        ];
    }

    /**
     * @test
     * @dataProvider datesFunctionDataProvider
     * @param string $format
     * @param int $expressionValue
     * @param bool $expected
     */
    public function checkConditionMatcherForDateFunction(string $format, int $expressionValue, bool $expected): void
    {
        $GLOBALS['SIM_EXEC_TIME'] = gmmktime(11, 4, 0, 1, 17, 1945);
        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('@' . $GLOBALS['SIM_EXEC_TIME'])));
        $this->assertSame(
            $expected,
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['date("' . $format . '") == ' . $expressionValue])
        );
    }

    /**
     * @test
     */
    public function checkConditionMatcherForFeatureFunction(): void
    {
        $featureName = 'test.testFeature';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features'][$featureName] = true;
        $this->assertTrue(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['feature("' . $featureName . '")'])
        );
        $this->assertTrue(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['feature("' . $featureName . '") == true'])
        );
        $this->assertTrue(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['feature("' . $featureName . '") === true'])
        );
        $this->assertFalse(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['feature("' . $featureName . '") == false'])
        );
        $this->assertFalse(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['feature("' . $featureName . '") === false'])
        );

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features'][$featureName] = false;
        $this->assertFalse(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['feature("' . $featureName . '")'])
        );
        $this->assertFalse(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['feature("' . $featureName . '") == true'])
        );
        $this->assertFalse(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['feature("' . $featureName . '") === true'])
        );
        $this->assertTrue(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['feature("' . $featureName . '") == false'])
        );
        $this->assertTrue(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['feature("' . $featureName . '") === false'])
        );
    }

    /**
     * @return array
     */
    public function hostnameDataProvider(): array
    {
        return [
            '[hostname = localhost]' => ['hostname', 'localhost', true],
            '[hostname = localhost, foo.local]' => ['hostname', 'localhost, foo.local', true],
            '[hostname = bar.local, foo.local]' => ['hostname', 'bar.local, foo.local', false],
        ];
    }

    /**
     * @test
     * @dataProvider hostnameDataProvider
     * @param string $expressionMethod
     * @param string $expressionValue
     * @param bool $expected
     */
    public function checkConditionMatcherForHostname(string $expressionMethod, string $expressionValue, bool $expected): void
    {
        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertSame($expected, $this->evaluateConditionCommonMethod->invokeArgs(
            $this->conditionMatcher,
            [$expressionMethod, $expressionValue]
        ));
    }

    /**
     * Data provider with matching applicationContext conditions.
     *
     * @return array
     */
    public function matchingApplicationContextConditionsDataProvider(): array
    {
        return [
            ['Production*'],
            ['Production/Staging/*'],
            ['Production/Staging/Server2'],
            ['/^Production.*$/'],
            ['/^Production\\/.+\\/Server\\d+$/'],
        ];
    }

    /**
     * @test
     * @dataProvider matchingApplicationContextConditionsDataProvider
     */
    public function evaluateConditionCommonReturnsTrueForMatchingContexts($matchingContextCondition): void
    {
        /** @var ApplicationContext $applicationContext */
        $applicationContext = new ApplicationContext('Production/Staging/Server2');
        Fixtures\GeneralUtilityFixture::setApplicationContext($applicationContext);
        $this->initConditionMatcher();

        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs($this->conditionMatcher, ['applicationContext', $matchingContextCondition])
        );
        // Test expression language
        $this->assertTrue(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['like(applicationContext, "' . preg_quote($matchingContextCondition, '/') . '")'])
        );
    }

    /**
     * Data provider with not matching applicationContext conditions.
     *
     * @return array
     */
    public function notMatchingApplicationContextConditionsDataProvider(): array
    {
        return [
            ['Production'],
            ['Testing*'],
            ['Development/Profiling, Testing/Unit'],
            ['Testing/Staging/Server2'],
            ['/^Testing.*$/'],
            ['/^Production\\/.+\\/Host\\d+$/'],
        ];
    }

    /**
     * @test
     * @dataProvider notMatchingApplicationContextConditionsDataProvider
     */
    public function evaluateConditionCommonReturnsNullForNotMatchingApplicationContexts($notMatchingApplicationContextCondition): void
    {
        /** @var ApplicationContext $applicationContext */
        $applicationContext = new ApplicationContext('Production/Staging/Server2');
        Fixtures\GeneralUtilityFixture::setApplicationContext($applicationContext);
        $this->initConditionMatcher();

        $this->assertFalse(
            $this->evaluateConditionCommonMethod->invokeArgs($this->conditionMatcher, ['applicationContext', $notMatchingApplicationContextCondition])
        );
        // Test expression language
        $this->assertFalse(
            $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['like(applicationContext, "' . preg_quote($notMatchingApplicationContextCondition, '/') . '")'])
        );
    }

    /**
     * Data provider for evaluateConditionCommonEvaluatesIpAddressesCorrectly
     *
     * @return array
     */
    public function evaluateConditionCommonDevIpMaskDataProvider(): array
    {
        return [
            // [0] $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask']
            // [1] Actual IP
            // [2] Expected condition result
            'IP matches' => [
                '127.0.0.1',
                '127.0.0.1',
                true,
            ],
            'ipv4 wildcard subnet' => [
                '127.0.0.1/24',
                '127.0.0.2',
                true,
            ],
            'ipv6 wildcard subnet' => [
                '0:0::1/128',
                '::1',
                true,
            ],
            'List of addresses matches' => [
                '1.2.3.4, 5.6.7.8',
                '5.6.7.8',
                true,
            ],
            'IP does not match' => [
                '127.0.0.1',
                '127.0.0.2',
                false,
            ],
            'ipv4 subnet does not match' => [
                '127.0.0.1/8',
                '126.0.0.1',
                false,
            ],
            'ipv6 subnet does not match' => [
                '::1/127',
                '::2',
                false
            ],
            'List of addresses does not match' => [
                '127.0.0.1, ::1',
                '::2',
                false,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider evaluateConditionCommonDevIpMaskDataProvider
     */
    public function evaluateConditionCommonEvaluatesIpAddressesCorrectly($devIpMask, $actualIp, $expectedResult): void
    {
        // Do not trigger proxy stuff of GeneralUtility::getIndPEnv
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP']);

        GeneralUtility::setIndpEnv('REMOTE_ADDR', $actualIp);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = $devIpMask;
        $this->initConditionMatcher();
        $result = $this->evaluateExpressionMethod->invokeArgs($this->conditionMatcher, ['ip("devIP")']);
        $this->assertSame($expectedResult, $result);
    }

    /**
     * @test
     */
    public function testUserFuncIsCalled(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunction']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithSingleArgument(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithSingleArgument(x)']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithIntegerZeroArgument(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithSingleArgument(0)']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithWhitespaceArgument(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithNoArgument( )']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleArguments(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArguments(1,2,3)']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsNullBoolString(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArguments(0,true,"foo")']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsNullStringBool(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArguments(0,"foo",true)']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsStringBoolNull(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArguments("foo",true,0)']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsStringNullBool(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArguments("foo",0,true)']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsBoolNullString(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArguments(true,0,"foo")']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsBoolStringNull(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArguments(true,"foo",0)']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsNullBoolStringSingleQuotes(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', "user_testFunctionWithThreeArguments(0,true,'foo')"]
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsNullStringBoolSingleQuotes(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', "user_testFunctionWithThreeArguments(0,'foo',true)"]
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsStringBoolNullSingleQuotes(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', "user_testFunctionWithThreeArguments('foo',true,0)"]
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsStringNullBoolSingleQuotes(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', "user_testFunctionWithThreeArguments('foo',0,true)"]
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsBoolNullStringSingleQuotes(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', "user_testFunctionWithThreeArguments(true,0,'foo')"]
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleDifferentArgumentsBoolStringNullSingleQuotes(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', "user_testFunctionWithThreeArguments(true,'foo',0)"]
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleSingleQuotedArguments(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', "user_testFunctionWithThreeArguments('foo','bar', 'baz')"]
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleSoubleQuotedArguments(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArguments("foo","bar","baz")']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncReturnsFalse(): void
    {
        $this->assertFalse(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionFalse']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleArgumentsAndQuotes(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArguments(1,2,"3,4,5,6")']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleArgumentsAndQuotesAndSpaces(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArguments ( 1 , 2, "3, 4, 5, 6" )']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleArgumentsAndQuotesAndSpacesStripped(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArgumentsSpaces ( 1 , 2, "3, 4, 5, 6" )']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithSpacesInQuotes(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithSpaces(" 3, 4, 5, 6 ")']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithMultipleArgumentsAndQuotesAndSpacesStrippedAndEscapes(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithThreeArgumentsSpaces ( 1 , 2, "3, \"4, 5\", 6" )']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithQuoteMissing(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testFunctionWithQuoteMissing ("value \")']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithQuotesInside(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'user_testQuotes("1 \" 2")']
            )
        );
    }

    /**
     * @test
     */
    public function testUserFuncWithClassMethodCall(): void
    {
        $this->assertTrue(
            $this->evaluateConditionCommonMethod->invokeArgs(
                $this->conditionMatcher,
                ['userFunc', 'ConditionMatcherUserFunctions::isTrue(1)']
            )
        );
    }

    public function expressionDataProvider(): array
    {
        return [
            // Default variants
            '[]' => ['[]', '[]'],
            '[foo]' => ['[foo]', '[foo]'],
            '[foo] && [bar]' => ['[foo] && [bar]', '[foo]&&[bar]'],
            '[foo] AND [bar]' => ['[foo] AND [bar]', '[foo]&&[bar]'],
            '[foo] and [bar]' => ['[foo] and [bar]', '[foo]&&[bar]'],
            '[foo] [bar]' => ['[foo] [bar]', '[foo]||[bar]'],
            '[foo] || [bar]' => ['[foo] || [bar]', '[foo]||[bar]'],
            '[foo] OR [bar]' => ['[foo] OR [bar]', '[foo]||[bar]'],
            '[foo] or [bar]' => ['[foo] or [bar]', '[foo]||[bar]'],
            '[foo] && [bar]&&[baz]' => ['[foo] && [bar]&&[baz]', '[foo]&&[bar]&&[baz]'],
            '[foo] AND [bar]AND[baz]' => ['[foo] AND [bar]AND[baz]', '[foo]&&[bar]&&[baz]'],
            '[foo] and [bar]and[baz]' => ['[foo] and [bar]and[baz]', '[foo]&&[bar]&&[baz]'],
            '[foo] || [bar]||[baz]' => ['[foo] || [bar]||[baz]', '[foo]||[bar]||[baz]'],
            '[foo] OR [bar]OR[baz]' => ['[foo] OR [bar]OR[baz]', '[foo]||[bar]||[baz]'],
            '[foo] or [bar]or[baz]' => ['[foo] or [bar]or[baz]', '[foo]||[bar]||[baz]'],
            '[foo] && [bar]||[baz]' => ['[foo] && [bar]||[baz]', '[foo]&&[bar]||[baz]'],
            '[foo] AND [bar]OR[baz]' => ['[foo] AND [bar]OR[baz]', '[foo]&&[bar]||[baz]'],
            '[foo] and [bar]or[baz]' => ['[foo] and [bar]or[baz]', '[foo]&&[bar]||[baz]'],
            '[foo] || [bar]OR[baz]' => ['[foo] || [bar]OR[baz]', '[foo]||[bar]||[baz]'],
            '[foo] || [bar]or[baz]' => ['[foo] || [bar]or[baz]', '[foo]||[bar]||[baz]'],
            '[foo] OR [bar]AND[baz]' => ['[foo] OR [bar]AND[baz]', '[foo]||[bar]&&[baz]'],
            '[foo] or [bar]and[baz]' => ['[foo] or [bar]and[baz]', '[foo]||[bar]&&[baz]'],

            // Special variants
            '[foo && bar && baz]' => ['[foo && bar && baz]', '[foo && bar && baz]'],
            '[foo and bar and baz]' => ['[foo and bar and baz]', '[foo and bar and baz]'],
            '[foo AND bar AND baz]' => ['[foo AND bar AND baz]', '[foo AND bar AND baz]'],
            '[foo || bar || baz]' => ['[foo || bar || baz]', '[foo || bar || baz]'],
            '[foo or bar or baz]' => ['[foo or bar or baz]', '[foo or bar or baz]'],
            '[foo OR bar OR baz]' => ['[foo OR bar OR baz]', '[foo OR bar OR baz]'],
            '[request.getParsedBody()[\'type\'] > 0]' => ['[request.getParsedBody()[\'type\'] > 0]', '[request.getParsedBody()[\'type\'] > 0]'],
            '[request.getParsedBody()[\'type\'] > 0 || request.getQueryParams()[\'type\'] > 0]' => ['[request.getParsedBody()[\'type\'] > 0 || request.getQueryParams()[\'type\'] > 0]', '[request.getParsedBody()[\'type\'] > 0 || request.getQueryParams()[\'type\'] > 0]'],
            '[request.getParsedBody()[\'type\'] > 0 or request.getQueryParams()[\'type\'] == 1]' => ['[request.getParsedBody()[\'type\'] > 0 or request.getQueryParams()[\'type\'] == 1]', '[request.getParsedBody()[\'type\'] > 0 or request.getQueryParams()[\'type\'] == 1]'],
            '[ (request.getParsedBody()[\'type\'] > 0) || (request.getQueryParams()[\'type\'] > 0) ]' => ['[ (request.getParsedBody()[\'type\'] > 0) || (request.getQueryParams()[\'type\'] > 0) ]', '[ (request.getParsedBody()[\'type\'] > 0) || (request.getQueryParams()[\'type\'] > 0) ]'],
            '[request.getParsedBody()[\'tx_news_pi1\'][\'news\'] > 0 || request.getQueryParams()[\'tx_news_pi1\'][\'news\'] > 0]' => ['[request.getParsedBody()[\'tx_news_pi1\'][\'news\'] > 0 || request.getQueryParams()[\'tx_news_pi1\'][\'news\'] > 0]', '[request.getParsedBody()[\'tx_news_pi1\'][\'news\'] > 0 || request.getQueryParams()[\'tx_news_pi1\'][\'news\'] > 0]'],
            '[request.getQueryParams()[\'tx_news_pi1\'][\'news\'] > 0]' => ['[request.getQueryParams()[\'tx_news_pi1\'][\'news\'] > 0]', '[request.getQueryParams()[\'tx_news_pi1\'][\'news\'] > 0]'],
        ];
    }

    /**
     * @test
     * @dataProvider expressionDataProvider
     * @param string $expression
     * @param string $expectedResult
     */
    public function normalizeExpressionWorksAsExpected(string $expression, string $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->conditionMatcher->_call('normalizeExpression', $expression));
    }
}
