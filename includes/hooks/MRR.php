<?php
if (!defined("WHMCS")) {
    die("Este arquivo não pode ser acessado diretamente");
}

use WHMCS\Database\Capsule;

add_hook('AdminHomeWidgets', 1, function () {
    // Array de conversão de ciclos inline
    $billingCycles = [
        'Monthly' => 1,
        'Quarterly' => 3,
        'Semiannually' => 6,
        'Annually' => 12,
        'Biennially' => 24,
        'Triennially' => 36,
        'One Time' => 0,
        'Free Account' => 0
    ];

    // Buscar serviços ativos de hosting
    $activeServices = Capsule::table('tblhosting')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->join('tblproductgroups', 'tblproducts.gid', '=', 'tblproductgroups.id')
        ->select([
            'tblhosting.id',
            'tblhosting.amount',
            'tblhosting.billingcycle',
            'tblhosting.nextduedate',
            'tblhosting.qty',
            'tblproducts.name as product_name',
            'tblproductgroups.name as group_name'
        ])
        ->where('tblhosting.domainstatus', 'Active')
        ->where('tblhosting.amount', '>', 0)
        ->whereNotIn('tblhosting.billingcycle', ['One Time', 'Free Account'])
        ->get();

    // Buscar domínios ativos
    $activeDomains = Capsule::table('tbldomains')
        ->select([
            'id',
            'recurringamount',
            'registrationperiod',
            'nextduedate'
        ])
        ->where('status', 'Active')
        ->where('recurringamount', '>', 0)
        ->where('donotrenew', 0)
        ->get();

    // Calcular MRR dos serviços
    $totalMRR = 0;
    $servicesMRR = [];
    $nextMonthRevenue = 0;
    $next3MonthsRevenue = 0;
    $next6MonthsRevenue = 0;
    $next12MonthsRevenue = 0;

    foreach ($activeServices as $service) {
        $months = $billingCycles[$service->billingcycle] ?? 1;
        if ($months > 0) {
            $monthlyAmount = ($service->amount * $service->qty) / $months;
            $totalMRR += $monthlyAmount;
            
            // Agrupar por grupo de produto
            $groupName = $service->group_name;
            if (!isset($servicesMRR[$groupName])) {
                $servicesMRR[$groupName] = 0;
            }
            $servicesMRR[$groupName] += $monthlyAmount;
            
            // Calcular receita futura baseada na próxima data de vencimento
            $nextDue = new DateTime($service->nextduedate);
            $now = new DateTime();
            $oneMonth = new DateTime('+1 month');
            $threeMonths = new DateTime('+3 months');
            $sixMonths = new DateTime('+6 months');
            $twelveMonths = new DateTime('+12 months');
            
            if ($nextDue <= $oneMonth) {
                $nextMonthRevenue += $service->amount * $service->qty;
            }
            if ($nextDue <= $threeMonths) {
                $next3MonthsRevenue += $service->amount * $service->qty;
            }
            if ($nextDue <= $sixMonths) {
                $next6MonthsRevenue += $service->amount * $service->qty;
            }
            if ($nextDue <= $twelveMonths) {
                $next12MonthsRevenue += $service->amount * $service->qty;
            }
        }
    }

    // Calcular MRR dos domínios (considerando renovação anual)
    $domainsMRR = 0;
    foreach ($activeDomains as $domain) {
        $monthlyDomainAmount = $domain->recurringamount / 12; // Domínios geralmente são anuais
        $domainsMRR += $monthlyDomainAmount;
        
        // Adicionar aos grupos
        if (!isset($servicesMRR['Domínios'])) {
            $servicesMRR['Domínios'] = 0;
        }
        $servicesMRR['Domínios'] += $monthlyDomainAmount;
    }

    $totalMRR += $domainsMRR;

    // Calcular ARR
    $totalARR = $totalMRR * 12;

    // Calcular receita do mês atual (faturas pagas este mês)
    $currentMonth = date('Y-m-01');
    $currentMonthRevenue = Capsule::table('tblinvoices')
        ->where('status', 'Paid')
        ->where('datepaid', '>=', $currentMonth)
        ->sum('total');

    // Calcular receita do mês anterior para comparação
    $lastMonth = date('Y-m-01', strtotime('-1 month'));
    $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
    $lastMonthRevenue = Capsule::table('tblinvoices')
        ->where('status', 'Paid')
        ->where('datepaid', '>=', $lastMonth)
        ->where('datepaid', '<=', $lastMonthEnd)
        ->sum('total');

    // Calcular crescimento
    $growthPercentage = 0;
    if ($lastMonthRevenue > 0) {
        $growthPercentage = (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;
    }

    // Formatação
    $totalMRRFormatted = number_format($totalMRR, 2, ',', '.');
    $totalARRFormatted = number_format($totalARR, 2, ',', '.');
    $currentMonthRevenueFormatted = number_format($currentMonthRevenue, 2, ',', '.');
    $growthFormatted = number_format(abs($growthPercentage), 1);
    $growthIcon = $growthPercentage >= 0 ? 'fa-arrow-up text-success' : 'fa-arrow-down text-danger';
    $growthColor = $growthPercentage >= 0 ? 'text-success' : 'text-danger';

    // Próximas renovações
    $next30DaysRevenue = number_format($nextMonthRevenue, 2, ',', '.');
    $next90DaysRevenue = number_format($next3MonthsRevenue, 2, ',', '.');

    // Breakdown por categoria (top 3)
    arsort($servicesMRR);
    $topCategories = array_slice($servicesMRR, 0, 3, true);
    $categoriesHtml = '';
    foreach ($topCategories as $category => $mrr) {
        $categoryMRRFormatted = number_format($mrr, 2, ',', '.');
        $percentage = $totalMRR > 0 ? round(($mrr / $totalMRR) * 100, 1) : 0;
        $categoriesHtml .= <<<HTML
            <div class="row mb-2">
                <div class="col-sm-6">
                    <span class="text-muted">$category</span>
                </div>
                <div class="col-sm-6 text-right">
                    <strong>R$ $categoryMRRFormatted</strong>
                    <small class="text-muted">($percentage%)</small>
                </div>
            </div>
HTML;
    }

    $html = <<<HTML
<div class="panel-body">
    <div class="widget-content-padded">
        <style>
            .revenue-metric { 
                text-align: center; 
                padding: 15px; 
                border-radius: 8px; 
                margin-bottom: 15px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            .revenue-number { 
                font-size: 28px; 
                font-weight: bold; 
                margin-bottom: 5px; 
            }
            .revenue-label { 
                font-size: 12px; 
                opacity: 0.9; 
            }
            .mini-metric {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 6px;
                text-align: center;
                margin-bottom: 10px;
            }
            .growth-indicator {
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
        </style>
        
        <!-- MRR Principal -->
        <div class="revenue-metric">
            <div class="revenue-number">R$ $totalMRRFormatted</div>
            <div class="revenue-label">Receita Recorrente Mensal (MRR)</div>
        </div>
        
        <!-- Métricas secundárias -->
        <div class="row">
            <div class="col-sm-6">
                <div class="mini-metric">
                    <strong>R$ $totalARRFormatted</strong><br>
                    <small class="text-muted">ARR Projetado</small>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="mini-metric">
                    <strong>R$ $currentMonthRevenueFormatted</strong><br>
                    <small class="text-muted">Receita Este Mês</small>
                </div>
            </div>
        </div>
        
        <!-- Crescimento -->
        <div class="text-center mb-3">
            <span class="growth-indicator $growthColor">
                <i class="fas $growthIcon"></i>
                <strong>$growthFormatted%</strong>
            </span>
            <small class="text-muted">vs mês anterior</small>
        </div>
        
        <hr>
        
        <!-- Próximas renovações -->
        <div class="mb-3">
            <h6 class="text-muted mb-2">Próximas Renovações</h6>
            <div class="row">
                <div class="col-sm-6">
                    <small class="text-muted">Próximos 30 dias:</small><br>
                    <strong class="text-info">R$ $next30DaysRevenue</strong>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted">Próximos 90 dias:</small><br>
                    <strong class="text-warning">R$ $next90DaysRevenue</strong>
                </div>
            </div>
        </div>
        
        <hr>
        
        <!-- Breakdown por categoria -->
        <div class="mb-3">
            <h6 class="text-muted mb-2">Breakdown por Categoria</h6>
            $categoriesHtml
        </div>
        
        <div class="text-center">
            <a href="reports.php" class="btn btn-sm btn-primary">
                <i class="fas fa-chart-line"></i> Ver Relatórios Detalhados
            </a>
        </div>
    </div>
</div>
HTML;

    return [
        'title' => 'Previsão de Receita Recorrente',
        'content' => $html,
        'icon' => 'fa-chart-line',
        'width' => 'half',
        'cache' => false
    ];
});

// Widget complementar: Tendências de MRR  
add_hook('AdminHomeWidgets', 1, function () {
    // Calcular MRR dos últimos 6 meses para tendência
    $mrrTrend = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $monthStart = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime("-$i months"));
        $monthName = date('M/y', strtotime("-$i months"));
        
        // Receita paga no mês
        $monthRevenue = Capsule::table('tblinvoices')
            ->where('status', 'Paid')
            ->where('datepaid', '>=', $monthStart)
            ->where('datepaid', '<=', $monthEnd . ' 23:59:59')
            ->sum('total');
            
        $mrrTrend[$monthName] = $monthRevenue;
    }
    
    // Calcular tendência de crescimento
    $trendValues = array_values($mrrTrend);
    $isGrowing = end($trendValues) > $trendValues[0];
    $avgGrowth = 0;
    
    if (count($trendValues) > 1) {
        $growthRates = [];
        for ($i = 1; $i < count($trendValues); $i++) {
            if ($trendValues[$i-1] > 0) {
                $growthRates[] = (($trendValues[$i] - $trendValues[$i-1]) / $trendValues[$i-1]) * 100;
            }
        }
        $avgGrowth = count($growthRates) > 0 ? array_sum($growthRates) / count($growthRates) : 0;
    }
    
    // Criar gráfico simples com barras CSS
    $maxValue = max($trendValues);
    $chartHtml = '';
    
    foreach ($mrrTrend as $month => $revenue) {
        $height = $maxValue > 0 ? ($revenue / $maxValue) * 60 : 0;
        $revenueFormatted = number_format($revenue, 0, ',', '.');
        
        $chartHtml .= <<<HTML
            <div class="chart-bar" style="display: inline-block; width: 15%; margin: 0 1%; text-align: center; vertical-align: bottom;">
                <div style="height: {$height}px; background: linear-gradient(to top, #4facfe, #00f2fe); margin-bottom: 5px; border-radius: 2px; min-height: 5px;"></div>
                <small class="text-muted" style="font-size: 10px;">$month</small><br>
                <small style="font-size: 9px;">R$ $revenueFormatted</small>
            </div>
HTML;
    }
    
    $avgGrowthFormatted = number_format(abs($avgGrowth), 1);
    $trendIcon = $isGrowing ? 'fa-trending-up text-success' : 'fa-trending-down text-danger';
    $trendColor = $isGrowing ? 'text-success' : 'text-danger';
    $trendText = $isGrowing ? 'Crescimento' : 'Declínio';
    
    $html = <<<HTML
<div class="panel-body">
    <div class="widget-content-padded">
        <style>
            .trend-chart {
                height: 100px;
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                margin: 20px 0;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 8px;
            }
        </style>
        
        <div class="text-center mb-3">
            <h6 class="text-muted">Tendência dos Últimos 6 Meses</h6>
            <span class="$trendColor">
                <i class="fas $trendIcon"></i>
                <strong>$trendText</strong>
            </span>
            <br>
            <small class="text-muted">Crescimento médio: $avgGrowthFormatted% ao mês</small>
        </div>
        
        <div class="trend-chart">
            $chartHtml
        </div>
        
        <div class="text-center">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i> 
                Baseado na receita faturada mensalmente
            </small>
        </div>
    </div>
</div>
HTML;

    return [
        'title' => 'Tendência de Receita',
        'content' => $html,
        'icon' => 'fa-chart-area',
        'width' => 'half',
        'cache' => false
    ];
});