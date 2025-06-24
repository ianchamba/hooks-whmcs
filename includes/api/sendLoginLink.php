<?php
// Inclua o init.php do WHMCS para carregar o ambiente
require_once __DIR__ . '/../../init.php';

// Verifica se o arquivo que contém a função de envio do WhatsApp existe
$whatsappFilePath = __DIR__ . '/../../modules/addons/WhatsAppNotify/addons/AutoLoginNotify.php';
if (file_exists($whatsappFilePath)) {
    require_once $whatsappFilePath; // Inclui o arquivo apenas se ele existir
}

use WHMCS\Database\Capsule;

// Verifica se a requisição possui o parâmetro 'action=sendLoginLink' e se é uma requisição POST
if (isset($_GET['action']) && $_GET['action'] === 'sendLoginLink' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Captura o JSON da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    $email = isset($input['email']) ? $input['email'] : null;

    // Log para debugging
    error_log("Requisição recebida no sendLoginLink.php com o e-mail: " . print_r($email, true));

    if ($email) {
        // Realiza a busca do cliente no WHMCS
        $client = localAPI('GetClientsDetails', ['email' => $email]);

        if ($client['result'] === 'success') {
            // Cria o token de login único
            $createToken = localAPI('CreateSsoToken', [
                'client_id' => $client['id']
            ]);

            if ($createToken['result'] === 'success') {
                // Gera o link de login
                $link = $createToken['redirect_url'];

                // Envia o e-mail usando o template padrão do WHMCS
                $emailParams = [
                    'messagename' => 'Magic Login', // Nome do template de e-mail no WHMCS
                    'id' => $client['id'], // ID do cliente
                    'customvars' => base64_encode(serialize([
                        'login_link' => $link // Passa o link como uma variável personalizada
                    ]))
                ];

                $sendEmail = localAPI('SendEmail', $emailParams);

                if ($sendEmail['result'] === 'success') {
                    // Verifica se a função sendWhatsappAutoLogin existe antes de chamá-la
                    if (function_exists('sendWhatsappAutoLogin')) {
                        $whatsappResult = sendWhatsappAutoLogin($client['phonenumberformatted'], $client['firstname'], $link);

                        if ($whatsappResult['success']) {
                            echo json_encode(['success' => true, 'message' => 'E-mail e WhatsApp enviados com sucesso.']);
                        } else {
                            echo json_encode(['success' => true, 'message' => 'E-mail enviado com sucesso, mas houve um erro ao enviar o WhatsApp.']);
                            error_log("Erro ao enviar WhatsApp: " . $whatsappResult['error']);
                        }
                    } else {
                        echo json_encode(['success' => true, 'message' => 'E-mail enviado com sucesso, envio de WhatsApp não configurado.']);
                        error_log("Função sendWhatsappAutoLogin não existe. WhatsApp não enviado.");
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro ao enviar e-mail usando template WHMCS.']);
                    error_log("Erro ao enviar e-mail usando template WHMCS: " . print_r($sendEmail, true));
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao criar o token.']);
                error_log("Erro ao criar o token");
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Cliente não encontrado.']);
            error_log("Cliente não encontrado para o e-mail $email");
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'E-mail não informado.']);
        error_log("E-mail não informado");
    }
    exit;
} else {
    // Retorna um erro 405 para métodos não permitidos
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}