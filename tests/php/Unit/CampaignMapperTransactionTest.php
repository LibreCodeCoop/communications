<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Db\CampaignMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class CampaignMapperTransactionTest extends TestCase {
	public function testUnchangedOrderCommits(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->expects(self::once())->method('beginTransaction');
		$db->expects(self::once())->method('commit');
		$db->expects(self::never())->method('rollBack');
		$db->expects(self::never())->method('getQueryBuilder');

		(new CampaignMapper($db))->persistOrder([['id' => 7, 'manual_order' => 10]]);
	}

	public function testOrderFailureRollsBackAndPropagates(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->expects(self::once())->method('beginTransaction');
		$db->expects(self::never())->method('commit');
		$db->expects(self::once())->method('rollBack');
		$db->method('getQueryBuilder')->willThrowException(new \RuntimeException('Database error'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Database error');
		(new CampaignMapper($db))->persistOrder([['id' => 7, 'manual_order' => 0]]);
	}

	public function testArchivedPurgeFailureRollsBackAndPropagates(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->expects(self::once())->method('beginTransaction');
		$db->expects(self::never())->method('commit');
		$db->expects(self::once())->method('rollBack');
		$db->method('getQueryBuilder')->willThrowException(new \RuntimeException('Database error'));

		$this->expectException(\RuntimeException::class);
		(new CampaignMapper($db))->purgeArchived(42);
	}
}
