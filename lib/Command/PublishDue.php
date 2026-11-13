<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Command;

use OCA\Communications\Service\BoardPublicationService;
use OCA\Communications\Service\CampaignRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Process scheduled publications and optionally drain their outbox in labs. */
final class PublishDue extends Command {
	public function __construct(
		private BoardPublicationService $publisher,
		private CampaignRepository $campaigns,
	) {
		parent::__construct();
	}
	protected function configure():void {
		$this->setName('communications:publish-due')
			->setDescription('Publish due board announcements and queue channel deliveries')
			->addOption('deliver', null, InputOption::VALUE_NONE,
				'Process up to 2000 accounts synchronously (for QA or repair)');
	}
	protected function execute(InputInterface $input, OutputInterface $output):int {
		try {
			$result = $this->publisher->publishDue(500);
			$output->writeln('Scheduled publications: ' . json_encode($result, JSON_THROW_ON_ERROR));
			if (!$input->getOption('deliver')) {
				return self::SUCCESS;
			}
			$processed = 0;
			foreach ($this->campaigns->all() as $c) {
				$now = time();
				// The bulk worker is only for immediate/scheduled publications. Event
				// campaigns use EventChannelDeliveryJob per trusted user and occurrence.
				if (!in_array($c['trigger_key'], ['nextcloud.board.published','nextcloud.time.window'], true)
					|| (!$c['board_visible'] && !$c['notify_users'] && !$c['notify_activity']
					 && !$c['notify_email'] && $c['gateway_channels'] === [])
					|| $c['status'] !== 'active' || $c['published_at'] <= 0
					|| ($c['starts_at'] !== null && $c['starts_at'] > $now)
					|| ($c['ends_at'] !== null && $c['ends_at'] <= $now)) {
					continue;
				}
				$offset = 0;
				do {
					$r = $this->publisher->broadcast((int)$c['id'], (int)$c['published_at'], $offset);
					$processed += $r['processed'];
					$offset += 100;
				} while ($r['queued'] && $offset < 2000);
			}
			$output->writeln('Accounts processed: ' . $processed);
			return self::SUCCESS;
		} catch (\Throwable $error) {
			$output->writeln('<error>' . $error->getMessage() . '</error>');
			return self::FAILURE;
		}
	}
}
