<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Controller;

use InvalidArgumentException;
use OCA\Communications\Service\BoardService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class BoardController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $session,
		private BoardService $board,
		private \OCP\IL10N $l10n,
	) {
		parent::__construct('communications', $request);
	}

	private function uid():?string {
		return $this->session->getUser()?->getUID();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'GET', url: '/api/board')]
	public function index(int $page = 1, string $search = '', string $filter = 'all'):JSONResponse {
		$uid = $this->uid();
		return $uid === null ? new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401)
			: new JSONResponse($this->board->list($uid, $page, $search, $filter === 'unread'?'unread':'all'));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'GET', url: '/api/board/{id}')]
	public function show(int $id):JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		$item = $this->board->view($uid, $id);
		return $item ? new JSONResponse(['announcement' => $item])
			: new JSONResponse(['error' => $this->l10n->t('Announcement not found.')], 404);
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'POST', url: '/api/board/{id}/read')]
	public function markRead(int $id):JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		return $this->board->markRead($uid, $id)
			? new JSONResponse(['read' => true])
			: new JSONResponse(['error' => $this->l10n->t('Publication not found.')], 404);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'GET', url: '/api/board/{id}/comments')]
	public function comments(int $id, int $before = 0):JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		$items = $this->board->comments($uid, $id, max(0, $before));
		return $items ? new JSONResponse($items)
			: new JSONResponse(['error' => $this->l10n->t('Announcement not found.')], 404);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'GET', url: '/api/board/{id}/mentions')]
	public function mentions(int $id, string $search = ''):JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		$results = $this->board->mentionCandidates($uid, $id, $search);
		return $results === null
	? new JSONResponse(['error' => $this->l10n->t('Publication unavailable.')], 404)
	: new JSONResponse(['users' => $results]);
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'POST', url: '/api/board/{id}/comments')]
	public function createComment(int $id, string $message = ''):JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		try {
			return new JSONResponse(['comment' => $this->board->createComment($uid, $id, $message)], 201);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 422);
		}
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/board/{id}/comments/{commentId}')]
	public function deleteComment(int $id, int $commentId):JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		return $this->board->deleteComment($uid, $id, $commentId)
			? new JSONResponse(['deleted' => true])
			: new JSONResponse(['error' => $this->l10n->t('Comment not found or unavailable.')], 404);
	}
}
