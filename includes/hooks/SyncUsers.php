<?php

use WHMCS\Database\Capsule;

add_hook('ClientEdit', 1, function($vars) {
    $clientId = $vars['userid'];
    $newEmail = $vars['email'];
    $newFirstName = $vars['firstname'];
    $newLastName = $vars['lastname'];

    try {
        $userLink = Capsule::table('tblusers_clients')
            ->where('client_id', $clientId)
            ->where('owner', 1)
            ->first();

        if (!$userLink) return;

        $userId = $userLink->auth_user_id;

        Capsule::table('tblusers')
            ->where('id', $userId)
            ->update([
                'email' => $newEmail,
                'first_name' => $newFirstName,
                'last_name' => $newLastName,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    } catch (Exception $e) {
        logActivity('Erro ao sincronizar dados do usuário principal: ' . $e->getMessage());
    }
});