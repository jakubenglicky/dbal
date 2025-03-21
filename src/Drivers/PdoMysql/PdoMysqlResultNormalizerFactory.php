<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\PdoMysql;


use Closure;
use DateInterval;
use DateTimeZone;
use Nextras\Dbal\Exception\InvalidArgumentException;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function date_default_timezone_get;
use function preg_match;


/**
 * @internal
 */
class PdoMysqlResultNormalizerFactory
{
	use StrictObjectTrait;


	private Closure $intNormalizer;
	private Closure $floatNormalizer;
	private Closure $timeNormalizer;
	private Closure $dateTimeNormalizer;
	private Closure $localDateTimeNormalizer;


	public function __construct(PdoMysqlDriver $driver)
	{
		$applicationTimeZone = new DateTimeZone(date_default_timezone_get());

		$this->intNormalizer = static function ($value): ?int {
			if ($value === null) return null;
			return (int) $value;
		};

		$this->floatNormalizer = static function ($value): ?float {
			if ($value === null) return null;
			return (float) $value;
		};

		$this->timeNormalizer = static function ($value): ?DateInterval {
			if ($value === null) return null;
			$matched = preg_match('#^(-?)(\d+):(\d+):(\d+)#', $value, $m);
			if ($matched !== 1) {
				throw new InvalidArgumentException("Unsupported value format for TIME column: $value. Unable to parse to DateInterval");
			}
			$value = new DateInterval("PT{$m[2]}H{$m[3]}M{$m[4]}S");
			$value->invert = $m[1] === '-' ? 1 : 0;
			return $value;
		};

		$this->dateTimeNormalizer = static function ($value) use ($driver, $applicationTimeZone): ?DateTimeImmutable {
			if ($value === null) return null;
			$value = $value . ' ' . $driver->getConnectionTimeZone()->getName();
			$dateTime = new DateTimeImmutable($value);
			return $dateTime->setTimezone($applicationTimeZone);
		};

		$this->localDateTimeNormalizer = static function ($value) use ($applicationTimeZone): ?DateTimeImmutable {
			if ($value === null) return null;
			$dateTime = new DateTimeImmutable($value);
			return $dateTime->setTimezone($applicationTimeZone);
		};
	}


	/**
	 * @param array<string, mixed> $types
	 * @return array<string, callable (mixed): mixed>
	 */
	public function resolve(array $types): array
	{
		static $ints = [
			'BIT' => true,
			'INT24' => true,
			'INTERVAL' => true,
			'TINY' => true,
			'SHORT' => true,
			'LONG' => true,
			'LONGLONG' => true,
			'YEAR' => true,
		];

		static $floats = [
			'DOUBLE' => true,
			'FLOAT' => true,
		];

		$normalizers = [];
		foreach ($types as $column => $type) {
			if ($type === 'VAR_STRING' || $type === 'STRING') {
				continue; // optimization
			} elseif (isset($ints[$type])) {
				$normalizers[$column] = $this->intNormalizer;
			} elseif (isset($floats[$type])) {
				$normalizers[$column] = $this->floatNormalizer;
			} elseif ($type === 'DATETIME') {
				$normalizers[$column] = $this->localDateTimeNormalizer;
			} elseif ($type === 'TIMESTAMP') {
				$normalizers[$column] = $this->dateTimeNormalizer;
			} elseif ($type === 'TIME') {
				$normalizers[$column] = $this->timeNormalizer;
			} elseif ($type === 'DATE') {
				$normalizers[$column] = $this->localDateTimeNormalizer;
			}
		}
		return $normalizers;
	}
}
