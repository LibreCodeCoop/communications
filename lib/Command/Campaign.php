<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Command;

use InvalidArgumentException;
use OCA\Communications\Service\CampaignRepository;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Auditable occ interface. Archive is non-destructive; update preserves
 * unspecified fields, unlike sending an incomplete REST document.
 */
class Campaign extends Command {
	public function __construct(
		private CampaignRepository $campaigns,
		private IUserManager $users,
	) {
		parent::__construct();
	}
	protected function configure():void {
		$this->setName('communications:campaign')
			->setDescription('List, create, update, archive or permanently delete publications')
			->addArgument('action', InputArgument::REQUIRED, 'list | show | create | update | archive | delete')
			->addArgument('id', InputArgument::OPTIONAL, 'Campaign ID for show, update or archive')
			->addOption('json', null, InputOption::VALUE_REQUIRED, 'Full or partial campaign configuration as JSON')
			->addOption('file', null, InputOption::VALUE_REQUIRED, 'Read JSON payload from file (recommended)')
			->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Existing Nextcloud UID for new announcements')
			->addOption('confirm-name', null, InputOption::VALUE_REQUIRED, 'Exact internal name for irreversible delete');
	}
	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$action = strtolower((string)$input->getArgument('action'));
			$rawId = $input->getArgument('id');
			$id = $rawId !== null && ctype_digit((string)$rawId) ? (int)$rawId : 0;
			$this->emit($output, $this->runAction($action, $id, $input));
			return self::SUCCESS;
		} catch (\Exception $error) {
			$output->writeln('<error>' . $error->getMessage() . '</error>');
			return self::FAILURE;
		}
	}

	private function runAction(string $action, int $id, InputInterface $input): array {
		if ($action === 'list') {
			return $this->campaigns->all();
		}
		if (in_array($action, ['show', 'update', 'archive', 'delete'], true) && $id <= 0) {
			throw new InvalidArgumentException('Specify a positive campaign ID.');
		}
		return match ($action) {
			'show' => $this->show($id),
			'archive' => $this->archive($id),
			'delete' => $this->delete($id, $input),
			'create', 'update' => $this->save($action, $id, $input),
			default => throw new InvalidArgumentException('Use list, show, create, update, archive or delete.'),
		};
	}

	private function show(int $id): array {
		$campaign = $this->campaigns->find($id);
		if ($campaign === null) {
			throw new InvalidArgumentException('Campaign not found.');
		}
		return $campaign;
	}

	private function archive(int $id): array {
		if ($this->campaigns->setStatus($id, 'archived') === null) {
			throw new InvalidArgumentException('Campaign not found.');
		}
		return ['id' => $id, 'status' => 'archived'];
	}

	private function delete(int $id, InputInterface $input): array {
		$name = (string)($input->getOption('confirm-name') ?? '');
		if ($name === '') {
			throw new InvalidArgumentException('Provide --confirm-name with the exact internal name.');
		}
		if (!$this->campaigns->purgeArchived($id, $name)) {
			throw new InvalidArgumentException('Campaign not found.');
		}
		return ['id' => $id, 'deleted' => true];
	}

	private function save(string $action, int $id, InputInterface $input): array {
		$payload = $this->payload($input);
		if ($action === 'create') {
			$actor = (string)($input->getOption('actor') ?? '');
			if ($actor === '' || $this->users->get($actor) === null) {
				throw new InvalidArgumentException('Provide --actor with an existing Nextcloud user ID.');
			}
			$campaign = $this->campaigns->create($payload, $actor);
		} else {
			$prior = $this->campaigns->find($id);
			if ($prior === null) {
				throw new InvalidArgumentException('Campaign not found.');
			}
			$campaign = $this->campaigns->update($id, array_replace($prior, $payload));
		}
		return ['id' => $campaign['id'], 'status' => $campaign['status']];
	}

	private function payload(InputInterface $input): array {
		$json = $input->getOption('json');
		$file = $input->getOption('file');
		if ($json !== null && $file !== null) {
			throw new InvalidArgumentException('Use either --json or --file.');
		}
		if ($file !== null) {
			if (!is_file($file) || !is_readable($file) || filesize($file) > 1048576) {
				throw new InvalidArgumentException('JSON file not found, readable or below 1 MiB.');
			}
			$json = file_get_contents($file);
		}
		if (!is_string($json) || $json === '') {
			throw new InvalidArgumentException('Provide --file or --json.');
		}
		$payload = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
		if (!is_array($payload) || array_is_list($payload)) {
			throw new InvalidArgumentException('Campaign payload must be a JSON object.');
		}
		return $payload;
	}

	private function emit(OutputInterface $output, array $payload):void {
		$output->writeln(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
	}
}
