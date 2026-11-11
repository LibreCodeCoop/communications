<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later
 * Invoke only Comunicações' generic listener, NOT the global dispatcher:
 * no LibreSign callbacks or unrelated webhooks are fired by a fake signature.
 */
require '/var/www/html/lib/base.php';

$server=\OC::$server;
$uid='poc_second';
$user=$server->get(\OCP\IUserManager::class)->get($uid);
if ($user===null || !$user->isEnabled()) {
 throw new \RuntimeException('Missing isolated lab subject');
}
$session=$server->get(\OCP\IUserSession::class);
$session->setUser($user);
$events=$server->get(\OCA\Communications\Service\EventStore::class);
$trigger=\OCA\Communications\Service\NativeEventCatalog::triggerKey(
 \OCA\Libresign\Events\SigningRequestCompletedEvent::class
);
$before=array_column($events->forUser($uid,time()-60,250),'id');
$file=new \OCA\Libresign\Db\File();
$file->setUuid('mvp-native-'.bin2hex(random_bytes(12)));
$file->setNodeType('file');
$file->setStatus(\OCA\Libresign\Enum\FileStatus::SIGNED->value);
$event=\OCA\Libresign\Events\SigningRequestCompletedEvent::fromTransition(
 $file,\OCA\Libresign\Enum\FileStatus::PARTIAL_SIGNED->value
);
if ($event===null) {throw new \RuntimeException('No aggregate completion event');}
$listener=$server->get(\OCA\Communications\AppInfo\NativeEventListener::class);
$listener->handle($event);
$after=array_values(array_filter($events->forUser($uid,time()-60,250),
 static fn(array $row):bool=>!in_array($row['id'],$before,true)));
if(count($after)!==1 || $after[0]['trigger_key']!==$trigger ||
   $after[0]['context_app']!=='libresign' || $after[0]['uid']!==$uid) {
 throw new \RuntimeException('Native event not recorded or wrong subject');
}
echo json_encode(['eventId'=>$after[0]['id'],
 'trigger'=>$trigger,'user'=>$after[0]['uid'],
 'context'=>$after[0]['context_app'],
 'webhookContract'=>$event->getWebhookSerializable(),
],JSON_THROW_ON_ERROR);
