<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Dbal library.
 * @license    MIT
 * @link       https://github.com/nextras/dbal
 */

namespace Nextras\Dbal\Platforms;

use Nextras\Dbal\Platforms\Data\Column;
use Nextras\Dbal\Platforms\Data\ForeignKey;
use Nextras\Dbal\Platforms\Data\Table;


interface IPlatform
{
	const SUPPORT_MULTI_COLUMN_IN = 1;
	const SUPPORT_QUERY_EXPLAIN = 2;


	/**
	 * Returns platform name.
	 */
	public function getName(): string;


	/**
	 * Returns list of tables names indexed by FQN table name.
	 * If no schema is provided, uses current schema name (search path).
	 * @return Table[]
	 * @phpstan-return array<string, Table>
	 */
	public function getTables(?string $schema = null): array;


	/**
	 * Returns list of table columns metadata, indexed by column name.
	 * @return Column[]
	 * @phpstan-return array<string, Column>
	 */
	public function getColumns(string $table): array;


	/**
	 * Returns list of table foreign keys, indexed by column name.
	 * @return ForeignKey[]
	 * @phpstan-return array<string, ForeignKey>
	 */
	public function getForeignKeys(string $table): array;


	/**
	 * Returns primary sequence name for the table.
	 * If not supported nor present, returns a null.
	 */
	public function getPrimarySequenceName(string $table): ?string;


	public function isSupported(int $feature): bool;
}
