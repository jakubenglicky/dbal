<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Pgsql;


use Closure;
use DateInterval;
use DateTimeZone;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Dbal\Utils\StrictObjectTrait;
use function bindec;
use function date_default_timezone_get;
use function in_array;
use function pg_unescape_bytea;
use function strtolower;


/**
 * @internal
 */
class PgsqlResultNormalizerFactory
{
	use StrictObjectTrait;


	private Closure $intNormalizer;
	private Closure $floatNormalizer;
	private Closure $boolNormalizer;
	private Closure $dateTimeNormalizer;
	private Closure $intervalNormalizer;
	private Closure $varBitNormalizer;
	private Closure $byteaNormalizer;


	public function __construct()
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

		$this->boolNormalizer = static function ($value): ?bool {
			static $trues = ['true', 't', 'yes', 'y', 'on', '1'];
			if ($value === null) return null;
			return in_array(strtolower($value), $trues, true);
		};

		$this->dateTimeNormalizer = static function ($value) use ($applicationTimeZone): ?DateTimeImmutable {
			if ($value === null) return null;
			$dateTime = new DateTimeImmutable($value);
			return $dateTime->setTimezone($applicationTimeZone);
		};

		$this->intervalNormalizer = static function ($value): ?DateInterval {
			if ($value === null) return null;
			$interval = DateInterval::createFromDateString($value);
			return $interval !== false ? $interval : null;
		};

		$this->varBitNormalizer = static function ($value) {
			if ($value === null) return null;
			return bindec($value);
		};

		$this->byteaNormalizer = static function ($value): ?string {
			if ($value === null) return null;
			return pg_unescape_bytea($value);
		};
	}


	/**
	 * @param array<string, mixed> $types
	 * @return array<string, callable (mixed): mixed>
	 */
	public function resolve(array $types): array
	{
		static $ints = [
			'int8' => true,
			'int4' => true,
			'int2' => true,
		];

		static $floats = [
			'numeric' => true,
			'float4' => true,
			'float8' => true,
		];

		static $dateTimes = [
			'time' => true,
			'date' => true,
			'timestamp' => true,
			'timetz' => true,
			'timestamptz' => true,
		];

		$normalizers = [];
		foreach ($types as $column => $type) {
			if ($type === 'varchar') {
				continue; // optimization
			} elseif (isset($ints[$type])) {
				$normalizers[$column] = $this->intNormalizer;
			} elseif (isset($floats[$type])) {
				$normalizers[$column] = $this->floatNormalizer;
			} elseif ($type === 'bool') {
				$normalizers[$column] = $this->boolNormalizer;
			} elseif (isset($dateTimes[$type])) {
				$normalizers[$column] = $this->dateTimeNormalizer;
			} elseif ($type === 'interval') {
				$normalizers[$column] = $this->intervalNormalizer;
			} elseif ($type === 'bit' || $type === 'varbit') {
				$normalizers[$column] = $this->varBitNormalizer;
			} elseif ($type === 'bytea') {
				$normalizers[$column] = $this->byteaNormalizer;
			}
		}
		return $normalizers;
	}
}
