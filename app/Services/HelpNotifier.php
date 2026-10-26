<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\HelpRequest;

/**
 * Emails des demandes d'aide (migration 028).
 *
 * L'alerte au bureau ne contient ni le sujet ni le message : une demande
 * d'aide peut toucher à la santé ou à l'argent, et une boîte email partagée
 * se transfère facilement. Le bureau lit le détail dans le back office.
 */
final class HelpNotifier
{
    public static function notifyBureau(array $request): void
    {
        $recipient = ContactSettings::recipientEmail();

        if ($recipient === '') {
            return;
        }

        Mail::send(
            $recipient,
            (string) Config::get('mail.from_name', 'FIUCC'),
            t('mail.help_new.subject', ['reference' => HelpRequest::reference($request)]),
            t('mail.help_new.heading'),
            t('mail.help_new.body', [
                'reference' => e(HelpRequest::reference($request)),
                'category'  => e(t('help.category.' . $request['category'])),
                'url'       => e(rtrim((string) Config::get('app.url', ''), '/') . '/admin/help/' . (int) $request['id']),
            ])
        );
    }

    /**
     * Réponse du bureau au membre : texte libre saisi par un admin, échappé
     * avant d'entrer dans le corps HTML (CLAUDE.md, règle 5).
     */
    public static function notifyMember(array $request, string $response): void
    {
        Mail::send(
            (string) $request['email'],
            (string) $request['full_name'],
            t('mail.help_reply.subject', ['reference' => HelpRequest::reference($request)]),
            t('mail.help_reply.heading'),
            t('mail.help_reply.body', [
                'reference' => e(HelpRequest::reference($request)),
                'status'    => e(t('help.status.' . $request['status'])),
                'response'  => nl2br(e($response)),
                'url'       => e(rtrim((string) Config::get('app.url', ''), '/') . '/help'),
            ])
        );
    }
}
