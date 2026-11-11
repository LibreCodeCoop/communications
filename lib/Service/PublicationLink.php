<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Service;

use OCP\IURLGenerator;

/** Accessible direct detail link, not a request to list content in Inbox. */
final class PublicationLink {
 public static function absolute(IURLGenerator $urls,int $id):string {
  $route=$urls->linkToRoute('communications.page.board',['id'=>$id]);
  if ($route==='') {
   $route=rtrim((string)\OC::$WEBROOT,'/').'/index.php/apps/communications/board?id='.$id;
  } else {
   // Some routes ignore non-path parameters; a deep link always carries id.
   if (!str_contains($route,'id=')) {
    $route.=(str_contains($route,'?')?'&':'?').'id='.$id;
   }
  }
  return $urls->getAbsoluteURL($route);
 }
}
