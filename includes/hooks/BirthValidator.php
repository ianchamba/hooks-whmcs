<?php
/*
    @HookName: WHMCS - Máscara de Data de Nascimento (Alternativo)
    @description: Adiciona uma máscara de data ao custom field de Data de Nascimento no formato dd/mm/yyyy usando JavaScript nativo.
*/

add_hook('ClientAreaHeadOutput', 1, function($vars) {
    $customfield_dob_id = 42; // Substitua pelo ID do custom field de data de nascimento

    return <<<HTML
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var dateField = document.getElementById('customfield{$customfield_dob_id}');
            if (dateField) {
                dateField.addEventListener('input', function(e) {
                    var value = e.target.value.replace(/[^0-9]/g, '').slice(0, 8);
                    if (value.length >= 5) {
                        e.target.value = value.slice(0, 2) + '/' + value.slice(2, 4) + '/' + value.slice(4);
                    } else if (value.length >= 3) {
                        e.target.value = value.slice(0, 2) + '/' + value.slice(2);
                    } else {
                        e.target.value = value;
                    }
                });
                dateField.setAttribute('placeholder', 'dd/mm/yyyy');
            }
        });
    </script>
HTML;
});
