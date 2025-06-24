<?php
/*
    @HookName: WHMCS - Validador de CPF/CNPJ
    @https://github.com/chags/WHMCS-CpfCnpjValidator
    @author Chags  https://github.com/chags
    @description: hoork de validação de CPF e CNPJ no formulario de cadastro do WHMCS 
    @valida se tem no bando para não repetir e se esta no padrão de geração de CPF/CNPJ
    @licence GNU GENERAL PUBLIC LICENSE Version 3, 29 June 2007
*/

if (!defined('WHMCS')) {
    die('You cannot access this file directly.');
}

use WHMCS\Database\Capsule;

if (!function_exists("validaCPF")) {
    function validaCPF($cpf = null) {
        if (empty($cpf)) {
            return false;
        }

        $cpf = preg_replace("/[^0-9]/", "", $cpf);

        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($i = 0, $sum = 0; $i < 9; $i++) {
            $sum += intval($cpf[$i]) * (10 - $i);
        }

        $digit1 = ($sum * 10) % 11;
        $digit1 = ($digit1 == 10) ? 0 : $digit1;

        if ($cpf[9] != $digit1) {
            return false;
        }

        for ($i = 0, $sum = 0; $i < 10; $i++) {
            $sum += intval($cpf[$i]) * (11 - $i);
        }

        $digit2 = ($sum * 10) % 11;
        $digit2 = ($digit2 == 10) ? 0 : $digit2;

        return ($cpf[10] == $digit2);
    }
}

if (!function_exists("validaCNPJ")) {
    function validaCNPJ($cnpj = null) {
        if (empty($cnpj)) {
            return false;
        }

        $cnpj = preg_replace("/[^0-9]/", "", $cnpj);

        if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        for ($i = 0, $sum = 0, $mult = 5; $i < 12; $i++) {
            $sum += intval($cnpj[$i]) * $mult;
            $mult = ($mult == 2) ? 9 : ($mult - 1);
        }

        $digit1 = ($sum % 11 < 2) ? 0 : (11 - $sum % 11);

        if ($cnpj[12] != $digit1) {
            return false;
        }

        for ($i = 0, $sum = 0, $mult = 6; $i < 13; $i++) {
            $sum += intval($cnpj[$i]) * $mult;
            $mult = ($mult == 2) ? 9 : ($mult - 1);
        }

        $digit2 = ($sum % 11 < 2) ? 0 : (11 - $sum % 11);

        return ($cnpj[13] == $digit2);
    }
}

add_hook('ClientAreaHeadOutput', 1, function($vars) {
    $customfield_cpf_cnpj_id = 41; // Substitua pelo ID do custom field de CPF/CNPJ

    return <<<HTML
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var cpfCnpjField = document.getElementById('customfield{$customfield_cpf_cnpj_id}');
            if (cpfCnpjField) {
                cpfCnpjField.addEventListener('input', function(e) {
                    var value = e.target.value.replace(/[^0-9]/g, '');
                    
                    if (value.length <= 11) { // Máscara CPF: 000.000.000-00
                        value = value.slice(0, 11); // Limita a 11 dígitos
                        value = value.replace(/(\\d{3})(\\d)/, '$1.$2');
                        value = value.replace(/(\\d{3})(\\d)/, '$1.$2');
                        value = value.replace(/(\\d{3})(\\d{1,2})$/, '$1-$2');
                    } else { // Máscara CNPJ: 00.000.000/0000-00
                        value = value.slice(0, 14); // Limita a 14 dígitos
                        value = value.replace(/(\\d{2})(\\d)/, '$1.$2');
                        value = value.replace(/(\\d{3})(\\d)/, '$1.$2');
                        value = value.replace(/(\\d{3})(\\d)/, '$1/$2');
                        value = value.replace(/(\\d{4})(\\d{1,2})$/, '$1-$2');
                    }
                    
                    e.target.value = value;
                });
                cpfCnpjField.setAttribute('placeholder', 'CPF ou CNPJ');
            }
        });
    </script>
HTML;
});
