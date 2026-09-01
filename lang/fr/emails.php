<?php

/*
|--------------------------------------------------------------------------
| Email Copy
|--------------------------------------------------------------------------
|
| Le texte des e-mails que reçoivent les personnes. Les notifications
| internes restent en anglais, car elles sont destinées à l'équipe. Tout ce
| que reçoit un visiteur est traduit, puisqu'il a choisi la langue sur le site.
|
*/

return [

    'lead_received' => [
        'subject' => 'Nous avons bien reçu votre message',
        'preheader' => 'Merci de nous avoir écrit. Voici une copie de ce que vous nous avez envoyé.',
        'badge' => 'Message reçu',
        'heading' => 'Merci de nous avoir écrit',
        'intro' => 'Nous avons bien votre message et un membre de l\'équipe vous recontactera rapidement. Voici une copie de ce que vous nous avez envoyé.',
        'name' => 'Nom',
        'email' => 'E-mail',
        'phone' => 'Téléphone',
        'message' => 'Message',
        'no_message' => 'Aucun message.',
        'closing' => 'Si vous souhaitez ajouter quelque chose, répondez simplement à cet e-mail.',
        'cta' => 'Voir nos logements',
    ],

];
