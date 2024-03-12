<?php declare(strict_types = 1);

namespace Nextras\Dbal\Drivers\Exception;


use Exception;


class UnknownMysqlTimezone extends QueryException
{
	public function __construct(
		string $message,
		int $errorCode = 0,
		string $errorSqlState = '',
		Exception $previousException = null,
		?string $sqlQuery = null,
	)
	{
		parent::__construct(
			$message . "\nSee how to solve the issue: https://nextras.org/dbal/docs/main/timezones-mysql-support",
			$errorCode,
			$errorSqlState,
			$previousException,
			$sqlQuery,
		);
	}
}
