<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Bridges\NetteTracy;

use Nextras\Dbal\DriverException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\ILogger;
use Nextras\Dbal\Platforms\IPlatform;
use Nextras\Dbal\Result\Result;
use Tracy\Debugger;
use Tracy\IBarPanel;


class ConnectionPanel implements IBarPanel, ILogger
{
	/** @var int */
	private $maxQueries = 100;

	/** @var int */
	private $count = 0;

	/** @var float */
	private $totalTime;

	/**
	 * @var array
	 * @phpstan-var array<array{IConnection, string, float, ?int}>
	 */
	private $queries = [];

	/** @var IConnection */
	private $connection;

	/** @var bool */
	private $doExplain;


	public static function install(IConnection $connection, bool $doExplain = true): void
	{
		$doExplain = $doExplain && $connection->getPlatform()->isSupported(IPlatform::SUPPORT_QUERY_EXPLAIN);
		Debugger::getBar()->addPanel(new ConnectionPanel($connection, $doExplain));
	}


	public function __construct(IConnection $connection, bool $doExplain)
	{
		$connection->addLogger($this);
		$this->connection = $connection;
		$this->doExplain = $doExplain;
	}


	public function onConnect(): void
	{
	}


	public function onDisconnect(): void
	{
	}


	public function onQuery(string $sqlQuery, float $timeTaken, ?Result $result, ?DriverException $exception): void
	{
		$this->count++;
		if ($this->count > $this->maxQueries) {
			return;
		}

		$this->totalTime += $timeTaken;
		$this->queries[] = [
			$this->connection,
			$sqlQuery,
			$timeTaken,
			$result ? $result->count() : null,
		];
	}


	public function getTab(): ?string
	{
		$count = $this->count;
		$totalTime = $this->totalTime;

		ob_start();
		require __DIR__ . '/ConnectionPanel.tab.phtml';
		return (string) ob_get_clean();
	}


	public function getPanel(): ?string
	{
		$count = $this->count;
		$queries = $this->queries;
		$queries = array_map(function ($row) {
			try {
				$row[4] = null;
				if ($this->doExplain) {
					$row[4] = $this->connection->getDriver()->query('EXPLAIN ' . $row['1'])->fetchAll();
				}
			} catch (\Throwable $e) {
				$row[4] = null;
				$row[3] = null; // rows count is also irrelevant
			}

			$row[1] = self::highlight($row[1]);
			return $row;
		}, $queries);
		$whitespaceExplain = $this->connection->getPlatform()->isSupported(IPlatform::SUPPORT_WHITESPACE_EXPLAIN);

		ob_start();
		require __DIR__ . '/ConnectionPanel.panel.phtml';
		return (string) ob_get_clean();
	}


	public static function highlight(string $sql): string
	{
		static $keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|SHOW|DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE|START\s+TRANSACTION|COMMIT|ROLLBACK|(?:RELEASE\s+|ROLLBACK\s+TO\s+)?SAVEPOINT';
		static $keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|[RI]?LIKE|REGEXP|TRUE|FALSE';

		$sql = " $sql ";
		$sql = htmlspecialchars($sql, ENT_IGNORE, 'UTF-8');
		$sql = preg_replace_callback("#(/\\*.+?\\*/)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])#is", function ($matches) {
			if (!empty($matches[1])) { // comment
				return '<em style="color:gray">' . $matches[1] . '</em>';
			} elseif (!empty($matches[2])) { // most important keywords
				return '<strong style="color:#2D44AD">' . $matches[2] . '</strong>';
			} elseif (!empty($matches[3])) { // other keywords
				return '<strong>' . $matches[3] . '</strong>';
			}
		}, $sql);
		assert($sql !== null);
		return trim($sql);
	}
}
