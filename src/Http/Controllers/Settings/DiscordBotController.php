<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Discord\DiscordBot;
use App\Discord\DiscordBotException;
use App\Discord\DiscordNotifier;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveDiscordBotRequest;
use App\Notifications\NotificationManager;
use App\Utils\AuditLogger;
use DomainException;
use EmergencyForge\Http\Exceptions\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Einstellungen › System › Discord-Bot: Token, Name und Profilbild des
 * eigenen Bots, welche Benachrichtigungen er per Direktnachricht zustellt,
 * und eine Testnachricht an sich selbst. Name und Bild gehen direkt an
 * Discord; gespeichert wird hier, was Discord zurückmeldet. Das Token
 * liegt verschlüsselt in der Datenbank (App\Security\SecretBox) und
 * steht nie im Formular und nie im Audit-Log.
 */
final class DiscordBotController extends Controller
{
    private const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

    private const AVATAR_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /** GET /settings/system/discord */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $settings = DiscordBot::settings();
        $activeUsers = Capsule::table('intra_users')->where('is_active', 1)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $this->renderView('settings/system/discord', [
            'bot' => [
                'enabled'   => $settings['enabled'],
                'connected' => $settings['token'] !== '',
                'tokenLost' => $settings['token_lost'],
                'id'        => $settings['id'],
                'name'      => $settings['name'],
                'avatarUrl' => DiscordBot::avatarUrl($settings['id'], $settings['avatar']),
                'inviteUrl' => DiscordBot::snowflake($settings['id']) ? DiscordBot::inviteUrl($settings['id']) : null,
                'dmTypes'   => $settings['dm_types'],
            ],
            'types' => $this->typeLabels(),
            'reach' => [
                'users'     => count($activeUsers),
                'reachable' => count(DiscordNotifier::recipients($activeUsers)),
                'optedOut'  => Capsule::table('intra_users')->where('is_active', 1)->where('discord_dm', 0)->count(),
            ],
            'ownDiscordId' => DiscordNotifier::recipients([(int) ($_SESSION['userid'] ?? 0)], false) !== [],
        ]);
    }

    /** POST /settings/system/discord */
    public function save(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        try {
            $data = SaveDiscordBotRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('settings/system/discord');
        }

        $userId  = (int) ($_SESSION['userid'] ?? 0);
        $current = DiscordBot::settings();

        if ($data['action'] === 'disconnect') {
            DiscordBot::store(['enabled' => false, 'token' => '', 'id' => '', 'name' => '', 'avatar' => ''], $userId);
            (new AuditLogger())->log($userId, 'Discord-Bot getrennt', null, 'System');
            Flash::success('Das Token ist gelöscht, der Bot schickt keine Nachrichten mehr.');
            $this->redirect('settings/system/discord');
        }

        $values  = [];
        $changed = [];
        try {
            $token = $data['token'];
            if ($token !== '') {
                $values  = ['token' => $token] + DiscordBot::me($token);
                $changed[] = 'Token';
            }
            $activeToken = $values['token'] ?? $current['token'];

            $profile = [];
            $name = $data['name'];
            if ($name !== '' && $name !== ($values['name'] ?? $current['name'])) {
                if (mb_strlen($name) < 2 || mb_strlen($name) > 32) {
                    throw new DomainException('Der Name braucht 2 bis 32 Zeichen.');
                }
                $profile['username'] = $name;
                $changed[] = 'Name';
            }
            $avatar = $this->uploadedAvatar();
            if ($avatar !== null) {
                $profile['avatar'] = $avatar;
                $changed[] = 'Profilbild';
            }
            if ($profile !== []) {
                if ($activeToken === '') {
                    throw new DomainException('Name und Profilbild lassen sich erst mit einem Token ändern.');
                }
                $values = array_merge($values, DiscordBot::updateProfile($activeToken, $profile));
            }

            $enabled = $data['enabled'];
            if ($enabled && $activeToken === '') {
                throw new DomainException('Ohne Token kann der Bot nicht eingeschaltet werden.');
            }
            if ($enabled !== $current['enabled']) {
                $changed[] = $enabled ? 'eingeschaltet' : 'ausgeschaltet';
            }

            $dmTypes = array_values(array_intersect(array_keys($this->typeLabels()), $data['dm_types']));
            if ($dmTypes != $current['dm_types']) {
                $changed[] = 'Direktnachrichten';
            }
        } catch (DiscordBotException | DomainException $e) {
            Flash::error($e->getMessage());
            $this->redirect('settings/system/discord');
        }

        if ($changed === []) {
            Flash::info('Keine Änderungen.');
            $this->redirect('settings/system/discord');
        }

        try {
            DiscordBot::store($values + ['enabled' => $enabled, 'dm_types' => $dmTypes], $userId);
        } catch (\RuntimeException $e) {
            Flash::error('Das Token ließ sich nicht verschlüsselt speichern: ' . $e->getMessage());
            $this->redirect('settings/system/discord');
        }
        (new AuditLogger())->log($userId, 'Discord-Bot geändert', implode(', ', $changed), 'System');
        Flash::success('Der Discord-Bot ist gespeichert.');
        $this->redirect('settings/system/discord');
    }

    /** POST /settings/system/discord/test: Testnachricht an das eigene Konto. */
    public function test(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $userId    = (int) ($_SESSION['userid'] ?? 0);
        $discordId = DiscordNotifier::recipients([$userId], false)[$userId] ?? null;
        if ($discordId === null) {
            Flash::error('Dein Konto hat keine Discord-ID, auch nicht über den verknüpften Mitarbeiter.');
            $this->redirect('settings/system/discord');
        }

        try {
            DiscordBot::sendDirectMessage($discordId, ['embeds' => [DiscordNotifier::embed(
                'Testnachricht',
                'Der Discord-Bot ist richtig eingerichtet. So sehen Benachrichtigungen per Direktnachricht aus.',
                BASE_PATH . 'settings/system/discord',
                'System',
            )]]);
        } catch (DiscordBotException $e) {
            Flash::error($e->getMessage());
            $this->redirect('settings/system/discord');
        }

        Flash::success('Die Testnachricht ist raus. Schau in deine Discord-Direktnachrichten.');
        $this->redirect('settings/system/discord');
    }

    /** @return array<string, string> Typ => Beschriftung */
    private function typeLabels(): array
    {
        $labels = [];
        foreach ((new NotificationManager())->types() as $key => $type) {
            $labels[$key] = $type->label();
        }
        asort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
    }

    /** Das hochgeladene Profilbild als data:-URI, wie Discord es will. */
    private function uploadedAvatar(): ?string
    {
        $file  = $_FILES['avatar'] ?? null;
        $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_file($tmp)) {
            throw new DomainException('Das Profilbild ist nicht angekommen. Bitte noch einmal hochladen.');
        }
        if ((int) filesize($tmp) > self::AVATAR_MAX_BYTES) {
            throw new DomainException('Das Profilbild ist zu groß (höchstens 2 MB).');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, self::AVATAR_TYPES, true)) {
            throw new DomainException('Als Profilbild gehen PNG, JPG, GIF und WebP.');
        }

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($tmp));
    }

    private function ensureAdmin(): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }
    }
}
