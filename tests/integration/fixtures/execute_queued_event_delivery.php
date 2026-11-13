<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Executes ONLY the queued delivery for one synthetic event in a disposable
 * Nextcloud test instance. Unlike cron.php it does not drain unrelated jobs.
 * Never expose this test-only CLI entry point over HTTP or run on production.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || count($argv) !== 2 || !ctype_digit($argv[1]) || (int)$argv[1] <= 0) {
	fwrite(STDERR, "Usage: php execute_queued_event_delivery.php <positive-event-id>\n");
	exit(2);
}

$eventId = (int)$argv[1];
require '/var/www/html/lib/base.php';

$events = \OC::$server->get(\OCA\Communications\Service\EventStore::class);
$event = $events->byId($eventId);
if ($event === null || $event['context_app'] !== 'libresign' || $event['uid'] !== 'poc_second') {
	throw new \RuntimeException('Refusing to execute an event outside the synthetic LibreSign fixture');
}

$jobs = \OC::$server->get(\OCP\BackgroundJob\IJobList::class);
$matches = [];
foreach ($jobs->getJobsIterator(\OCA\Communications\BackgroundJob\EventChannelDeliveryJob::class, 100, 0) as $job) {
	if (($job->getArgument()['eventId'] ?? null) === $eventId) {
		$matches[] = $job;
	}
}
if (count($matches) !== 1) {
	throw new \RuntimeException('Expected exactly one queued delivery for the synthetic event; found ' . count($matches));
}

$job = $matches[0];
$jobId = $job->getId();
$job->start($jobs);
if ($jobs->getById($jobId) !== null) {
	throw new \RuntimeException('Scoped event delivery job remained in the queue');
}

echo json_encode(['eventId' => $eventId, 'jobId' => $jobId, 'executed' => true], JSON_THROW_ON_ERROR);
