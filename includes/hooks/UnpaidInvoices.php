<?php
if (!defined("WHMCS")) {
    die("Este arquivo não pode ser acessado diretamente");
}
use WHMCS\Database\Capsule;
add_hook('AdminHomeWidgets', 1, function () {
    // Contar faturas em aberto
    $count = Capsule::table('tblinvoices')->where('status', 'Unpaid')->count();
    // Somar totais agrupados por ID da moeda do cliente
    $totalsByCurrency = Capsule::table('tblinvoices')
        ->join('tblclients', 'tblinvoices.userid', '=', 'tblclients.id')
        ->select('tblclients.currency', Capsule::raw('SUM(tblinvoices.total) as total'))
        ->where('tblinvoices.status', 'Unpaid')
        ->groupBy('tblclients.currency')
        ->get();
    // Obter todas as moedas do banco de dados
    $currenciesDB = Capsule::table('tblcurrencies')->get();
    $currencies = [];
    foreach ($currenciesDB as $currency) {
        $currencyArr = (array)$currency;
        $currencies[$currencyArr['id']] = $currencyArr;
    }
    // Formatar totais apenas com símbolo da moeda + valor, sem código
    $totalsFormatted = [];
    foreach ($totalsByCurrency as $row) {
        $curId = $row->currency;
        $amount = $row->total;
        if (isset($currencies[$curId])) {
            $c = $currencies[$curId];
            // Determinar símbolo (prefixo ou sufixo)
            $symbol = trim($c['prefix']) ?: trim($c['suffix']) ?: $c['code'];
            $totalsFormatted[] = $symbol . number_format($amount, 2, ',', '.');
        } else {
            // fallback apenas para o valor
            $totalsFormatted[] = number_format($amount, 2, ',', '.');
        }
    }
    $totalsString = implode(' e ', $totalsFormatted);
    // Construir conteúdo HTML
    $html = <<<HTML
<div class="panel-body">
            <div class="widget-content-padded icon-stats">
    <div class="row">
        <div class="col-sm-4">
            <div class="item">
                <div class="icon-holder text-center">
                    <a href="invoices.php?status=Unpaid"><i class="fal fa-file-invoice text-danger"></i></a>
                </div>
                <div class="data">
                    <div class="number">
                        <a href="invoices.php?status=Unpaid"><span class="text-danger">$count</span></a>
                    </div>				
                    <div class="note">
                        Em Aberto 
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-8 text-right">
            <i class="fas fa-money-check color-orange"></i> <span class="color-orange">Total em aberto: $totalsString</span><br> <a href="invoices.php?status=Unpaid"><i class="fas fa-arrow-right"></i> Ver faturas em aberto</a>
        </div>
    </div>
</div></div>
HTML;
    return [
        'title' => 'Faturas em Aberto',
        'content' => $html,
        'icon' => 'fa-file-invoice-dollar',
        'width' => 'half',
        'cache' => false
    ];
});