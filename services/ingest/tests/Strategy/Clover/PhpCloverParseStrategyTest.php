<?php

namespace App\Tests\Strategy\Clover;

use App\Exception\ParseException;
use App\Strategy\Clover\PhpCloverParseStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhpCloverParseStrategyTest extends TestCase
{
    #[DataProvider('cloverXmlDataProvider')]
    public function testSupports(string $contents, bool $expectedSupport): void
    {
        $parser = new PhpCloverParseStrategy();
        $this->assertEquals($expectedSupport, $parser->supports($contents));
    }

    #[DataProvider('cloverXmlDataProvider')]
    public function testParse(string $contents, bool $expectedSupport, array $expectedCoverage): void
    {
        $parser = new PhpCloverParseStrategy();
        if (!$expectedSupport) {
            $this->expectException(ParseException::class);
        }

        $projectCoverage = $parser->parse($contents);

        if ($expectedSupport) {
            $this->assertEquals(
                $expectedCoverage,
                json_decode(json_encode($projectCoverage), true)
            );
        }
    }

    public static function cloverXmlDataProvider(): array
    {
        return [
            'Invalid XML file' => [
                'not-valid-xml',
                false,
                []
            ],
            'Schema violating XML' => [
                <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <root generated="1681657109">
                  <someElement timestamp="1681657109">
                    <otherElement name="mock-file">
                      <line num="13" type="method" name="__construct" visibility="public" complexity="1" crap="1" count="4"/>
                    </otherElement>
                  </someElement>
                </root>
                XML,
                false,
                []
            ],
            'Valid schema XML' => [
                <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <coverage generated="1681657109">
                  <project timestamp="1681657109">
                    <file name="mock-file">
                      <class name="mock-class" namespace="global">
                        <metrics complexity="3" methods="3" coveredmethods="3" conditionals="0" coveredconditionals="0" statements="12" coveredstatements="12" elements="15" coveredelements="15"/>
                      </class>
                      <line num="13" type="method" name="__construct" visibility="public" complexity="1" crap="1" count="4"/>
                      <line num="17" type="stmt" count="4"/>
                      <line num="19" type="method" name="sendRequest" visibility="public" complexity="1" crap="1" count="4"/>
                      <metrics loc="38" ncloc="38" classes="1" methods="3" coveredmethods="3" conditionals="0" coveredconditionals="0" statements="12" coveredstatements="12" elements="15" coveredelements="15"/>
                    </file>
                    <file name="untestable-file">
                      <metrics loc="11" ncloc="11" classes="0" methods="0" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="0" coveredstatements="0" elements="0" coveredelements="0"/>
                    </file>
                    <file name="untestable-class-file">
                      <class name="untestable-class" namespace="global">
                        <metrics complexity="0" methods="0" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="0" coveredstatements="0" elements="0" coveredelements="0"/>
                      </class>
                      <metrics loc="41" ncloc="32" classes="0" methods="0" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="0" coveredstatements="0" elements="0" coveredelements="0"/>
                    </file>
                    <metrics files="50" loc="2716" ncloc="2226" classes="37" methods="107" coveredmethods="94" conditionals="0" coveredconditionals="0" statements="733" coveredstatements="683" elements="840" coveredelements="777"/>
                  </project>
                </coverage>
                XML,
                true,
                [
                    'generatedAt' => [
                        'date' => '2023-04-16 14:58:29.000000',
                        'timezone_type' => 3,
                        'timezone' => 'UTC'
                    ],
                    'files' => [
                        [
                            'fileName' => 'mock-file',
                            'lines' => [
                                [
                                    'type' => 'METHOD',
                                    'name' => '__construct',
                                    'lineNumber' => 13,
                                    'count' => 4,
                                    'complexity' => 1,
                                    'crap' => 1
                                ],
                                [
                                    'type' => 'STATEMENT',
                                    'lineNumber' => 17,
                                    'count' => 4,
                                    'complexity' => 0,
                                    'crap' => 0
                                ],
                                [
                                    'type' => 'METHOD',
                                    'name' => 'sendRequest',
                                    'lineNumber' => 19,
                                    'count' => 4,
                                    'complexity' => 1,
                                    'crap' => 1
                                ]
                            ]
                        ],
                        [
                            'fileName' => 'mock-file',
                            'lines' => []
                        ],
                        [
                            'fileName' => 'untestable-file',
                            'lines' => []
                        ],
                        [
                            'fileName' => 'untestable-file',
                            'lines' => []
                        ],
                        [
                            'fileName' => 'untestable-class-file',
                            'lines' => []
                        ],
                        [
                            'fileName' => 'untestable-class-file',
                            'lines' => []
                        ]
                    ]
                ]
            ]
        ];
    }
}
