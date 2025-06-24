<?php
/**
 * Hostbraza WHMCS Turnstile Hook
 *
 * LICENSE: Apache 2.0 + Commons Clause
 * @category   whmcs
 * @package    whmcs-turnstile
 * @author     Hostbraza
 * @license    https://hostbraza.com.br
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly!');
}

// Função para adicionar notificação de erro
function addTurnstileErrorNotification($message) {
    $_SESSION['turnstile_error'] = $message;
    $_SESSION['turnstile_error_display'] = $message;
}

if (!empty($_POST)) {
    $pageFile = basename($_SERVER['SCRIPT_NAME'], '.php');
    
    // NOVA VERIFICAÇÃO: Pula captcha para rota /login/cart
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $isLoginCartRoute = strpos($requestUri, 'rp=/login/cart') !== false;
    
    if ((($pageFile == 'index' && isset($_POST['username']) && isset($_POST['password']) && in_array('login', hostbrazaTurnstileLocations)) ||
            ($pageFile == 'register' && in_array('register', hostbrazaTurnstileLocations)) ||
            ($pageFile == 'contact' && in_array('contact', hostbrazaTurnstileLocations)) ||
            ($pageFile == 'pwreset' && in_array('reset', hostbrazaTurnstileLocations)) ||
            ($pageFile == 'submitticket' && in_array('ticket', hostbrazaTurnstileLocations)) ||
            ($pageFile == 'cart' && $_GET['a'] == 'checkout' && in_array('checkout', hostbrazaTurnstileLocations))) 
            && hostbrazaTurnstileEnabled 
            && !$_POST['promocode'] 
            && !$isLoginCartRoute) { // ADICIONADA EXCEÇÃO PARA /login/cart
        
        if (!isset($_POST['cf-turnstile-response'])) {
            unset($_SESSION['uid']);
            addTurnstileErrorNotification('Por favor, complete o captcha antes de continuar.');
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'secret' => hostbrazaTurnstileSecret,
                'response' => $_POST['cf-turnstile-response'],
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_URL => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ]);
        $result = curl_exec($curl);
        curl_close($curl);
        
        if ($json = json_decode($result)) {
            if (!$json->success) {
                unset($_SESSION['uid']);
                addTurnstileErrorNotification(hostbrazaTurnstileError);
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit;
            }
        }
    }
}

// Hook para exibir notificação de erro
add_hook('ClientAreaPage', 1, function($vars) {
    if (isset($_SESSION['turnstile_error'])) {
        $errorMessage = $_SESSION['turnstile_error'];
        unset($_SESSION['turnstile_error']);
        
        // Adiciona o alert na variável de template
        global $smarty;
        $currentAlerts = $smarty->getTemplateVars('errormessage');
        
        // Se já existe uma mensagem de erro, adiciona à existente
        if ($currentAlerts) {
            $smarty->assign('errormessage', $currentAlerts . '<br>' . $errorMessage);
        } else {
            $smarty->assign('errormessage', $errorMessage);
        }
    }
});

// Hook alternativo para injetar o alert diretamente no HTML
add_hook('ClientAreaHeaderOutput', 1, function($vars) {
    if (isset($_SESSION['turnstile_error_display'])) {
        $errorMessage = $_SESSION['turnstile_error_display'];
        unset($_SESSION['turnstile_error_display']);
        
        return '
<script>
document.addEventListener("DOMContentLoaded", function() {
    var alertDiv = document.createElement("div");
    alertDiv.className = "alert alert-danger alert-dismissible turnstile-popup-alert";
    alertDiv.innerHTML = \'<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><i class="fas fa-exclamation-circle"></i> ' . addslashes($errorMessage) . '\';
    
    // Adiciona ao body
    document.body.appendChild(alertDiv);
    
    // Auto-remove após 5 segundos
    setTimeout(function() {
        alertDiv.style.animation = "slideDown 0.3s ease-out";
        setTimeout(function() {
            alertDiv.remove();
        }, 300);
    }, 5000);
    
    // Remove ao clicar no X
    alertDiv.querySelector(".close").addEventListener("click", function() {
        alertDiv.style.animation = "slideDown 0.3s ease-out";
        setTimeout(function() {
            alertDiv.remove();
        }, 300);
    });
});
</script>';
    }
    return '';
});

add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    if (!hostbrazaTurnstileEnabled) {
        return '';
    }

    $pageFile = basename($_SERVER['SCRIPT_NAME'], '.php');
    $isCheckoutPage = $pageFile == 'cart' && $_GET['a'] == 'checkout';
    
    // NOVA VERIFICAÇÃO: Não adiciona captcha para rota /login/cart
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $isLoginCartRoute = strpos($requestUri, 'rp=/login/cart') !== false;

    if (
        (in_array('login', hostbrazaTurnstileLocations) && $vars['pagetitle'] == $vars['LANG']['login'] && !$isLoginCartRoute) ||
        (in_array('register', hostbrazaTurnstileLocations) && $pageFile == 'register') ||
        (in_array('contact', hostbrazaTurnstileLocations) && $pageFile == 'contact') ||
        (in_array('reset', hostbrazaTurnstileLocations) && ($pageFile == 'pwreset' || $vars['pagetitle'] == $vars['LANG']['pwreset'])) ||
        (in_array('ticket', hostbrazaTurnstileLocations) && $pageFile == 'submitticket') ||
        (in_array('checkout', hostbrazaTurnstileLocations) && $isCheckoutPage)
    ) {
        return '
<script>
document.addEventListener("DOMContentLoaded", function () {
    const isCheckoutPage = window.location.search.includes("a=checkout");
    let elements;

    if (isCheckoutPage) {
        // Na página de checkout, apenas desativa o botão #checkout
        const checkoutBtn = document.querySelector("#checkout");
        elements = checkoutBtn ? [checkoutBtn] : [];
    } else {
        // Nas outras páginas, mantém o comportamento original
        const selectors = [
            "input[type=submit].btn-primary",
            "button.btn-primary[type=submit]",
            "button[type=submit].btn.btn-primary",
            "#sendLoginLink"
        ];
        elements = Array.from(document.querySelectorAll(selectors.join(",")));
    }

    elements.forEach(el => {
        if (el.tagName.toLowerCase() === "a") {
            el.classList.add("disabled");
            el.style.pointerEvents = "none";
            el.style.opacity = "0.5";
        } else {
            el.disabled = true;
        }
    });

    window.javascriptCallback = function(token) {
        elements.forEach(el => {
            if (el.tagName.toLowerCase() === "a") {
                el.classList.remove("disabled");
                el.style.pointerEvents = "auto";
                el.style.opacity = "1";
            } else {
                el.disabled = false;
            }
        });
    };
    
    // Validação adicional antes do submit
    const forms = document.querySelectorAll("form");
    forms.forEach(form => {
        form.addEventListener("submit", function(e) {
        
        const submitBtn = e.submitter || form.querySelector("button[type=\'submit\']");
        
        // Ignora validação para botões que não são de checkout
        if (submitBtn && !submitBtn.classList.contains(\'btn-checkout\') && submitBtn.id !== \'checkout\') {
            return true;
        }
            const turnstileResponse = form.querySelector("[name=\'cf-turnstile-response\']");
            if (!turnstileResponse || !turnstileResponse.value) {
                e.preventDefault();
                
                const submitBtn = e.submitter || form.querySelector("button[type=\'submit\']");
            if (submitBtn) {
                const btnText = submitBtn.querySelector(".btn-text");
                if (btnText) {
                    btnText.classList.remove("invisible");
                }
                // Remove também o spinner/loader se existir
                const btnLoader = submitBtn.querySelector(".btn-loader, .spinner-border");
                if (btnLoader) {
                    btnLoader.style.display = "none";
                }
            }
                
                // Remove popup anterior se existir
                var existingAlert = document.querySelector(".turnstile-popup-alert");
                if (existingAlert) {
                    existingAlert.remove();
                }
                
                // Cria alert popup do Bootstrap
                var alertDiv = document.createElement("div");
                alertDiv.className = "alert alert-warning alert-dismissible turnstile-popup-alert";
                alertDiv.style.cssText = "position: fixed; bottom: 20px; left: 20px; z-index: 9999; max-width: 400px; animation: slideUp 0.3s ease-out;";
                alertDiv.innerHTML = \'<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><i class="fas fa-exclamation-triangle"></i> Por favor, complete o captcha antes de continuar.\';
                
                // Adiciona CSS de animação se não existir
                if (!document.querySelector("#turnstileAnimationStyles")) {
                    var style = document.createElement("style");
                    style.id = "turnstileAnimationStyles";
                    style.innerHTML = `
                        @keyframes slideUp {
                            from { transform: translateY(100%); opacity: 0; }
                            to { transform: translateY(0); opacity: 1; }
                        }
                        @keyframes slideDown {
                            from { transform: translateY(0); opacity: 1; }
                            to { transform: translateY(100%); opacity: 0; }
                        }
                    `;
                    document.head.appendChild(style);
                }
                
                // Adiciona ao body
                document.body.appendChild(alertDiv);
                
                // Auto-remove após 3 segundos
                setTimeout(function() {
                    alertDiv.style.animation = "slideDown 0.3s ease-out";
                    setTimeout(function() {
                        alertDiv.remove();
                    }, 300);
                }, 3000);
                
                // Remove ao clicar no X
                alertDiv.querySelector(".close").addEventListener("click", function() {
                    alertDiv.style.animation = "slideDown 0.3s ease-out";
                    setTimeout(function() {
                        alertDiv.remove();
                    }, 300);
                });
                
                return false;
            }
        });
    });
});
</script>

<script>
var turnstileDiv = document.createElement("div");
turnstileDiv.innerHTML = \'<div class="cf-turnstile" data-sitekey="'.hostbrazaTurnstileSite.'" data-callback="javascriptCallback" data-theme="'.hostbrazaTurnstileTheme.'"></div>'.(hostbrazaTurnstileCredits ? '<a href="https://hostbraza.com.br" target="_blank"><small class="text-muted text-uppercase">Captcha integration by Hostbraza</small></a>' : '<!-- Captcha integration by Hostbraza -->').'\';

// POSIÇÃO CORRIGIDA: Sempre antes do botão, nunca após o resumo do pedido
var targetSelector = "input[type=submit], button[type=submit]";
if (window.location.search.includes("a=checkout") && document.querySelector("#btnCompleteOrder")) {
    targetSelector = "#btnCompleteOrder";
}

if (document.querySelector(targetSelector)) {
    var form = document.querySelector(targetSelector).parentNode;
    form.insertBefore(turnstileDiv, document.querySelector(targetSelector));
}
</script>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
';
    }

    return '';
});