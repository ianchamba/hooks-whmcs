<?php

use WHMCS\Database\Capsule;

// Hook para aceitar pedidos automaticamente após pagamento da fatura
add_hook('InvoicePaid', 1, function ($vars) {
    $invoiceID = $vars['invoiceid'];

    // Obtem todos os pedidos associados à fatura
    $orders = Capsule::table('tblorders')
        ->where('invoiceid', $invoiceID)
        ->pluck('id');

    foreach ($orders as $orderID) {
        // Aceita o pedido automaticamente
        MakeAcceptOrder($orderID);
    }
});

/**
 * Função para aceitar pedidos automaticamente
 */
function MakeAcceptOrder($orderID = "")
{
    if (empty($orderID)) {
        logActivity("Dados insuficientes para aceitar pedido: OrderID=$orderID");
        return;
    }

    $command = 'AcceptOrder';
    $postData = [
        'orderid' => $orderID,
        'autosetup' => '1',
        'sendemail' => '1',
    ];

    $admin = Capsule::table('tbladmins')->where('roleid', '=', 1)->first();

    if (!$admin) {
        logActivity("Nenhum administrador encontrado para executar o comando AcceptOrder.");
        return;
    }

    $adminUsername = $admin->username;

    $results = localAPI($command, $postData, $adminUsername);

    if ($results['result'] !== 'success') {
        logActivity("Erro ao aceitar pedido automaticamente: " . json_encode($results));
    } else {
        logActivity("Pedido aceito automaticamente: OrderID=$orderID");
    }
}