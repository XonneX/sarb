<?php

declare(strict_types=1);

namespace DaveLiddament\StaticAnalysisResultsBaseliner\Tests\Unit\Framework\Command;

use DaveLiddament\StaticAnalysisResultsBaseliner\Domain\Common\FileName;
use DaveLiddament\StaticAnalysisResultsBaseliner\Domain\Common\ProjectRoot;
use DaveLiddament\StaticAnalysisResultsBaseliner\Domain\Common\SarbException;
use DaveLiddament\StaticAnalysisResultsBaseliner\Domain\HistoryAnalyser\HistoryFactory;
use DaveLiddament\StaticAnalysisResultsBaseliner\Domain\ResultsParser\ResultsParser;
use DaveLiddament\StaticAnalysisResultsBaseliner\Domain\ResultsParser\UnifiedDiffParser\Parser;
use DaveLiddament\StaticAnalysisResultsBaseliner\Framework\Command\CreateBaseLineCommand;
use DaveLiddament\StaticAnalysisResultsBaseliner\Framework\Command\RemoveBaseLineFromResultsCommand;
use DaveLiddament\StaticAnalysisResultsBaseliner\Framework\Container\HistoryFactoryRegistry;
use DaveLiddament\StaticAnalysisResultsBaseliner\Framework\Container\OutputFormatterRegistry;
use DaveLiddament\StaticAnalysisResultsBaseliner\Framework\Container\ResultsParsersRegistry;
use DaveLiddament\StaticAnalysisResultsBaseliner\Plugins\GitDiffHistoryAnalyser\GitDiffHistoryFactory;
use DaveLiddament\StaticAnalysisResultsBaseliner\Tests\TestDoubles\HistoryFactoryStub;
use DaveLiddament\StaticAnalysisResultsBaseliner\Tests\TestDoubles\ResultsParserStub2;
use DaveLiddament\StaticAnalysisResultsBaseliner\Tests\TestDoubles\ResultsParserStub2Identifier;
use DaveLiddament\StaticAnalysisResultsBaseliner\Tests\Unit\Plugins\GitDiffHistoryAnalyser\internal\StubGitWrapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RemoveBaseLineCommandTest extends TestCase
{
    private const INPUT_STRING_1 = <<<EOF
This is
a multiline
string
EOF;
    public const BASELINE_FILENAME = 'baseline1.sarb';
    public const BASELINE_FILE_ARGUMENT = 'baseline-file';
    public const INPUT_FORMAT_OPTION = '--output-format';
    public const PROJECT_ROOT = '--project-root';

    /**
     * @var RemoveBaseLineFromResultsCommand
     */
    private $command;

    /**
     * @var ProjectRoot
     */
    private $projectRoot;

    /**
     * @var OutputFormatterRegistry
     */
    private $outputFormatterRegistry;

    protected function setUp(): void
    {
        $this->defaultResultsParser = new Sa();
        $this->resultsParser2 = new ResultsParserStub2();

        $this->resultsParserRegistry = new ResultsParsersRegistry([
            $this->defaultResultsParser,
            $this->resultsParser2,
        ]);

        $this->defaultHistoryFactory = new GitDiffHistoryFactory(new StubGitWrapper('123', ''), new Parser());
        $this->historyFactoryStub = new HistoryFactoryStub();

        $this->historyFactoryRegistry = new HistoryFactoryRegistry([
            $this->defaultHistoryFactory,
            $this->historyFactoryStub,
        ]);

        $this->projectRoot = new ProjectRoot('/tmp', '/tmp/foo/bar');
    }

    public function testHappyPath(): void
    {
        $commandTester = $this->createCommandTester(
            $this->defaultHistoryFactory,
            $this->defaultResultsParser,
            self::BASELINE_FILENAME,
            null,
            null
        );

        $commandTester->execute([
            self::BASELINE_FILE_ARGUMENT => self::BASELINE_FILENAME,
        ]);

        $this->assertReturnCode(0, $commandTester);
        $this->assertResponseContains('Baseline created', $commandTester);
    }

    public function testPickNonDefaultResultsParserWithGuessTypesSet(): void
    {
        $commandTester = $this->createCommandTester(
            $this->defaultHistoryFactory,
            $this->resultsParser2,
            self::BASELINE_FILENAME,
            null,
            null
        );

        $commandTester->execute([
            self::INPUT_FORMAT_OPTION => ResultsParserStub2Identifier::CODE,
            self::BASELINE_FILE_ARGUMENT => self::BASELINE_FILENAME,
        ]);

        $this->assertReturnCode(0, $commandTester);
        $this->assertResponseContains(
            '[stub_2] guesses the classification of violations. This means results might not be 100% accurate.',
            $commandTester
        );
    }

    public function testPickNonDefaultHistoryAnalyser(): void
    {
        $commandTester = $this->createCommandTester(
            $this->historyFactoryStub,
            $this->defaultResultsParser,
            self::BASELINE_FILENAME,
            null,
            null
        );

        $commandTester->execute([
            self::BASELINE_FILE_ARGUMENT => self::BASELINE_FILENAME,
            self::HISTORY_ANALYSER => HistoryFactoryStub::CODE,
        ]);

        $this->assertReturnCode(0, $commandTester);
    }

    public function testInvalidResultsParser(): void
    {
        $commandTester = $this->createCommandTester(
            $this->defaultHistoryFactory,
            $this->defaultResultsParser,
            self::BASELINE_FILENAME,
            null,
            null
        );

        $commandTester->execute([
            self::INPUT_FORMAT_OPTION => 'rubbish',
            self::BASELINE_FILE_ARGUMENT => self::BASELINE_FILENAME,
        ]);

        $this->assertReturnCode(2, $commandTester);
        $this->assertResponseContains(
            'Invalid value [rubbish] for option [input-format]. Pick one of: sarb-json|stub_2',
            $commandTester
        );
    }

    public function testInvalidHistoryAnalyser(): void
    {
        $commandTester = $this->createCommandTester(
            $this->defaultHistoryFactory,
            $this->defaultResultsParser,
            self::BASELINE_FILENAME,
            null,
            null
        );

        $commandTester->execute([
            self::HISTORY_ANALYSER => 'rubbish',
            self::BASELINE_FILE_ARGUMENT => self::BASELINE_FILENAME,
        ]);

        $this->assertReturnCode(2, $commandTester);
        $this->assertResponseContains(
            'Invalid value [rubbish] for option [history-analyser]. Pick one of: git|history-factory-stub',
            $commandTester
        );
    }

    public function testSpecifyProjectRoot(): void
    {
        $commandTester = $this->createCommandTester(
            $this->historyFactoryStub,
            $this->defaultResultsParser,
            self::BASELINE_FILENAME,
            $this->projectRoot,
            null
        );

        $commandTester->execute([
            self::HISTORY_ANALYSER => HistoryFactoryStub::CODE,
            self::BASELINE_FILE_ARGUMENT => self::BASELINE_FILENAME,
            self::PROJECT_ROOT => '/tmp',
        ]);

        $this->assertReturnCode(0, $commandTester);
    }

    public function testSimulateSarbException(): void
    {
        $commandTester = $this->createCommandTester(
            $this->historyFactoryStub,
            $this->defaultResultsParser,
            self::BASELINE_FILENAME,
            null,
            new SarbException()
        );

        $commandTester->execute([
            self::HISTORY_ANALYSER => HistoryFactoryStub::CODE,
            self::BASELINE_FILE_ARGUMENT => self::BASELINE_FILENAME,
            self::PROJECT_ROOT => '/tmp',
        ]);

        $this->assertReturnCode(4, $commandTester);
    }

    public function testSimulateThrowable(): void
    {
        $commandTester = $this->createCommandTester(
            $this->historyFactoryStub,
            $this->defaultResultsParser,
            self::BASELINE_FILENAME,
            null,
            new \Exception()
        );

        $commandTester->execute([
            self::HISTORY_ANALYSER => HistoryFactoryStub::CODE,
            self::BASELINE_FILE_ARGUMENT => self::BASELINE_FILENAME,
            self::PROJECT_ROOT => '/tmp',
        ]);

        $this->assertReturnCode(5, $commandTester);
    }

    private function createCommandTester(
        HistoryFactory $expectedHistoryFactory,
        ResultsParser $expectedResultsParser,
        string $baselineFileName,
        ?ProjectRoot $projectRoot,
        ?\Throwable $exception
    ): CommandTester {
        $mockBaseLineCreator = new MockBaseLineCreator(
            $expectedHistoryFactory,
            $expectedResultsParser,
            new FileName($baselineFileName),
            $projectRoot,
            self::INPUT_STRING_1.PHP_EOL, // CommandTest adds line end
            $exception
        );

        $command = new CreateBaseLineCommand(
            $this->resultsParserRegistry,
            $this->historyFactoryRegistry,
            $mockBaseLineCreator
        );

        $commandTester = new CommandTester($command);
        $commandTester->setInputs([self::INPUT_STRING_1]);

        return $commandTester;
    }

    private function assertReturnCode(int $expectedReturnCode, CommandTester $commandTester): void
    {
        $this->assertSame($expectedReturnCode, $commandTester->getStatusCode(), $commandTester->getDisplay());
    }

    private function assertResponseContains(string $expectedMessage, CommandTester $commandTester): void
    {
        $output = $commandTester->getDisplay();
        $position = strpos($output, $expectedMessage);
        $this->assertNotFalse($position, "Can't find message [$expectedMessage] in [$output]");
    }
}
