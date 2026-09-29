<?php

declare(strict_types=1);

/**
 * Mail — Web-Routen.
 *
 * Alles hinter AuthMiddleware + `mail.use`. Das Postfach kommt immer aus
 * der Sitzung (Mailbox::current()), keine Route nimmt ein fremdes an.
 * Schreibende Routen prüft die globale CsrfMiddleware.
 *
 * @var \EmergencyForge\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use Plugin\Mail\Controllers\MailAdminController;
use Plugin\Mail\Controllers\MailController;
use Plugin\Mail\Controllers\MailListController;

$mailAuth = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'mail.use'])];
$folders  = '{folder:inbox|sent|drafts|archive|trash}';

// Seiten (kein Schreiben auf GET: Entwürfe entstehen per POST, „gelesen“
// setzt mail.js per POST).
$router->get('/mail',                                  [MailController::class, 'index'],           $mailAuth);
$router->get('/mail/' . $folders,                      [MailController::class, 'folder'],          $mailAuth);
$router->get('/mail/' . $folders . '/{id:\d+}',        [MailController::class, 'messagePage'],     $mailAuth);
$router->get('/mail/' . $folders . '/{id:\d+}/preview', [MailController::class, 'messagePreview'], $mailAuth);
$router->get('/mail/compose',                          [MailController::class, 'composeNew'],      $mailAuth);
$router->get('/mail/compose/reply/{id:\d+}',           [MailController::class, 'composeReply'],    $mailAuth);
$router->get('/mail/compose/reply-all/{id:\d+}',       [MailController::class, 'composeReplyAll'], $mailAuth);
$router->get('/mail/compose/forward/{id:\d+}',         [MailController::class, 'composeForward'],  $mailAuth);
$router->get('/mail/compose/draft/{id:\d+}',           [MailController::class, 'composeDraft'],    $mailAuth);
$router->get('/mail/signature',                        [MailController::class, 'signatureForm'],   $mailAuth);
$router->post('/mail/signature',                       [MailController::class, 'saveSignature'],   $mailAuth);

// JSON

$router->post('/mail/drafts',                    [MailController::class, 'createDraft'],        $mailAuth);
$router->post('/mail/drafts/{id:\d+}',           [MailController::class, 'updateDraft'],        $mailAuth);
$router->post('/mail/drafts/{id:\d+}/send',      [MailController::class, 'sendDraft'],          $mailAuth);
$router->post('/mail/drafts/{id:\d+}/attachments', [MailController::class, 'uploadAttachment'], $mailAuth);
$router->post('/mail/messages/{id:\d+}/move',    [MailController::class, 'move'],               $mailAuth);
$router->post('/mail/messages/{id:\d+}/read',    [MailController::class, 'markRead'],           $mailAuth);
$router->post('/mail/messages/{id:\d+}/delete',  [MailController::class, 'delete'],             $mailAuth);
$router->get( '/mail/attachments/{id:\d+}',      [MailController::class, 'downloadAttachment'], $mailAuth);
$router->post('/mail/attachments/{id:\d+}/delete', [MailController::class, 'deleteAttachment'], $mailAuth);
$router->get( '/mail/addressbook',               [MailController::class, 'addressbook'],        $mailAuth);

// Verteiler (mail.lists.manage)
$listAuth = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'mail.lists.manage'])];
$router->get( '/mail/lists',                   [MailListController::class, 'index'],   $listAuth);
$router->get( '/mail/lists/create',            [MailListController::class, 'create'],  $listAuth);
$router->post('/mail/lists',                   [MailListController::class, 'store'],   $listAuth);
$router->get( '/mail/lists/{id:\d+}/edit',     [MailListController::class, 'edit'],    $listAuth);
$router->post('/mail/lists/{id:\d+}',          [MailListController::class, 'update'],  $listAuth);
$router->post('/mail/lists/{id:\d+}/delete',   [MailListController::class, 'destroy'], $listAuth);

// Postfachverwaltung und Einstellungen (mail.admin) — ohne Einsicht in Mails.
$adminAuth = [new AuthMiddleware(), new PermissionMiddleware(['admin', 'mail.admin'])];
$router->get( '/settings/mail',                            [MailAdminController::class, 'settings'],      $adminAuth);
$router->post('/settings/mail',                            [MailAdminController::class, 'saveSettings'],  $adminAuth);
$router->get( '/settings/mail/mailboxes',                  [MailAdminController::class, 'mailboxes'],     $adminAuth);
$router->get( '/settings/mail/mailboxes/{id:\d+}/edit',    [MailAdminController::class, 'editMailbox'],   $adminAuth);
$router->post('/settings/mail/mailboxes/{id:\d+}',         [MailAdminController::class, 'updateMailbox'], $adminAuth);
$router->post('/settings/mail/mailboxes/{id:\d+}/lock',    [MailAdminController::class, 'lockMailbox'],   $adminAuth);
$router->post('/settings/mail/mailboxes/{id:\d+}/unlock',  [MailAdminController::class, 'unlockMailbox'], $adminAuth);
$router->post('/settings/mail/mailboxes/{id:\d+}/account', [MailAdminController::class, 'assignAccount'], $adminAuth);
