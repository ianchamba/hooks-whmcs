<?php
if (!defined("WHMCS")) {
    die("Este arquivo não pode ser acessado diretamente");
}

use WHMCS\Database\Capsule;

add_hook('AdminHomeWidgets', 1, function () {
    // Buscar top 3 afiliados com maior saldo total (balance + withdrawn)
    $topAffiliates = Capsule::table('tblaffiliates')
        ->join('tblclients', 'tblaffiliates.clientid', '=', 'tblclients.id')
        ->select([
            'tblclients.firstname',
            'tblclients.lastname', 
            'tblclients.email',
            'tblclients.id as client_id',
            'tblaffiliates.id as affiliate_id',
            'tblaffiliates.balance',
            'tblaffiliates.withdrawn',
            'tblaffiliates.visitors',
            Capsule::raw('(tblaffiliates.balance + tblaffiliates.withdrawn) as total_earned')
        ])
        ->where(function($query) {
            $query->where('tblaffiliates.balance', '>', 0)
                  ->orWhere('tblaffiliates.withdrawn', '>', 0);
        })
        ->orderBy('total_earned', 'desc')
        ->limit(3)
        ->get();

    // Contar total de referrals para cada afiliado
    foreach ($topAffiliates as $affiliate) {
        $referrals = Capsule::table('tblaffiliatesaccounts')
            ->where('affiliateid', $affiliate->affiliate_id)
            ->count();
        $affiliate->total_referrals = $referrals;
    }

    // Se não houver afiliados, mostrar mensagem
    if ($topAffiliates->isEmpty()) {
        $html = <<<HTML
<div class="panel-body">
    <div class="widget-content-padded">
        <div class="text-center">
            <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
            <p class="text-muted">Nenhum afiliado com ganhos encontrado</p>
        </div>
    </div>
</div>
HTML;
    } else {
        // Construir HTML com ranking
        $rankingHtml = '';
        $position = 1;
        
        foreach ($topAffiliates as $affiliate) {
            $fullName = trim($affiliate->firstname . ' ' . $affiliate->lastname);
            $email = $affiliate->email;
            $totalEarned = number_format($affiliate->total_earned, 2, ',', '.');
            $currentBalance = number_format($affiliate->balance, 2, ',', '.');
            $withdrawn = number_format($affiliate->withdrawn, 2, ',', '.');
            $visitors = number_format($affiliate->visitors);
            $totalReferrals = $affiliate->total_referrals;
            $clientId = $affiliate->client_id;
            $affiliateId = $affiliate->affiliate_id;
            
            // Definir ícone e cor baseado na posição
            $icons = [
                1 => '<i class="fas fa-trophy text-warning"></i>', // Ouro
                2 => '<i class="fas fa-medal text-secondary"></i>', // Prata
                3 => '<i class="fas fa-award text-bronze"></i>' // Bronze
            ];
            
            $positionIcon = $icons[$position] ?? '<i class="fas fa-star"></i>';
            
            $rankingHtml .= <<<HTML
            <div class="row affiliate-rank mb-3">
                <div class="col-sm-1 text-center">
                    $positionIcon
                </div>
                <div class="col-sm-6">
                    <strong><a href="clientssummary.php?userid=$clientId" title="Ver cliente">$fullName</a></strong><br>
                    <small class="text-muted">$email</small>
                </div>
                <div class="col-sm-5 text-right">
                    <span class="text-success"><strong>R$ $totalEarned</strong></span><br>
                    <small class="text-muted">
                        Saldo: R$ $currentBalance | Sacado: R$ $withdrawn<br>
                        $visitors visitas | $totalReferrals conversões
                    </small>
                </div>
            </div>
HTML;
            $position++;
        }

        $html = <<<HTML
<div class="panel-body">
    <div class="widget-content-padded">
        <style>
            .text-bronze { color: #cd7f32 !important; }
            .affiliate-rank:hover { background-color: #f8f9fa; border-radius: 4px; }
            .affiliate-rank { padding: 8px; transition: background-color 0.2s; }
        </style>
        $rankingHtml
        <hr>
        <div class="text-center">
            <a href="affiliates.php" class="btn btn-sm btn-primary">
                <i class="fas fa-chart-line"></i> Ver Todos os Afiliados
            </a>
        </div>
    </div>
</div>
HTML;
    }

    return [
        'title' => 'Top 3 Afiliados',
        'content' => $html,
        'icon' => 'fa-users',
        'width' => 'half',
        'cache' => false
    ];
});

// Widget extra: Estatísticas gerais de afiliados
add_hook('AdminHomeWidgets', 1, function () {
    // Estatísticas gerais
    $totalAffiliates = Capsule::table('tblaffiliates')->count();
    $totalCommissionsPaid = Capsule::table('tblaffiliates')->sum('withdrawn');
    $totalPendingBalance = Capsule::table('tblaffiliates')->sum('balance');
    $totalVisitors = Capsule::table('tblaffiliates')->sum('visitors');
    $totalReferrals = Capsule::table('tblaffiliatesaccounts')->count();
    
    // Formatação
    $totalCommissionsPaidFormatted = number_format($totalCommissionsPaid, 2, ',', '.');
    $totalPendingBalanceFormatted = number_format($totalPendingBalance, 2, ',', '.');
    $totalVisitorsFormatted = number_format($totalVisitors);
    $totalReferralsFormatted = number_format($totalReferrals);
    
    // Taxa de conversão
    $conversionRate = $totalVisitors > 0 ? round(($totalReferrals / $totalVisitors) * 100, 2) : 0;
    
    $html = <<<HTML
<div class="panel-body">
    <div class="widget-content-padded">
        <div class="row text-center">
            <div class="col-sm-6 mb-3">
                <div class="small-stat">
                    <div class="stat-icon">
                        <i class="fas fa-users text-primary"></i>
                    </div>
                    <div class="stat-number">$totalAffiliates</div>
                    <div class="stat-label">Afiliados Ativos</div>
                </div>
            </div>
            <div class="col-sm-6 mb-3">
                <div class="small-stat">
                    <div class="stat-icon">
                        <i class="fas fa-eye text-info"></i>
                    </div>
                    <div class="stat-number">$totalVisitorsFormatted</div>
                    <div class="stat-label">Total de Visitas</div>
                </div>
            </div>
            <div class="col-sm-6 mb-3">
                <div class="small-stat">
                    <div class="stat-icon">
                        <i class="fas fa-handshake text-success"></i>
                    </div>
                    <div class="stat-number">$totalReferralsFormatted</div>
                    <div class="stat-label">Conversões</div>
                </div>
            </div>
            <div class="col-sm-6 mb-3">
                <div class="small-stat">
                    <div class="stat-icon">
                        <i class="fas fa-percentage text-warning"></i>
                    </div>
                    <div class="stat-number">$conversionRate%</div>
                    <div class="stat-label">Taxa Conversão</div>
                </div>
            </div>
        </div>
        <hr>
        <div class="row text-center">
            <div class="col-sm-6">
                <span class="text-success"><strong>R$ $totalCommissionsPaidFormatted</strong></span><br>
                <small class="text-muted">Total Pago</small>
            </div>
            <div class="col-sm-6">
                <span class="text-warning"><strong>R$ $totalPendingBalanceFormatted</strong></span><br>
                <small class="text-muted">Saldo Pendente</small>
            </div>
        </div>
    </div>
</div>
HTML;

    return [
        'title' => 'Estatísticas de Afiliados',
        'content' => $html,
        'icon' => 'fa-chart-pie',
        'width' => 'half',
        'cache' => false
    ];
});