<?php
use WHMCS\Database\Capsule;

add_hook('TicketOpen', 1, function($vars) {
    $ticketId = $vars['ticketid'];
    $subject = $vars['subject'];

    // Verifica se o assunto do ticket contém "Affiliate Withdrawal Request"
    if (strpos($subject, 'Affiliate Withdrawal Request') !== false) {
        $newSubject = "Solicitação de Retirada para Afiliado";
        
        // Atualiza o título do ticket no banco de dados
        Capsule::table('tbltickets')
            ->where('id', $ticketId)
            ->update(['title' => $newSubject]);
    }
});

add_hook('EmailPreSend', 1, function($vars) {
    // Verifica se o nome da mensagem contém "Affiliate Withdrawal Request"
    if (strpos($vars['messagename'], 'Affiliate Withdrawal Request') !== false) {
        return [
            'subject' => 'Solicitação de Retirada para Afiliado'
        ];
    }
});