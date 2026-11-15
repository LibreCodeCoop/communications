<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Command;

use OCA\Communications\Service\BoardPublicationService;
use OCA\Communications\Service\PublicationDrainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PublishDue extends Command {
	public function __construct(
		private BoardPublicationService $publisher,
		private PublicationDrainer $drainer,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('communications:publish-due')
			->setDescription('Publish due board announcements and queue channel deliveries')
			->addOption('deliver', null, InputOption::VALUE_NONE,
				'Process up to 2000 accounts synchronously (for QA or repair)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$result = $this->publisher->publishDue(500);
			$output->writeln('Scheduled publications: ' . json_encode($result, JSON_THROW_ON_ERROR));
			if ($input->getOption('deliver')) {
				$output->writeln('Accounts processed: ' . $this->drainer->drain());
			}
			return self::SUCCESS;
		} catch (\JsonException|\OCP\DB\Exception|\RuntimeException $error) {
			$output->writeln('<error>' . $error->getMessage() . '</error>');
			return self::FAILURE;
		}
	}
}
