<?php
use WHMCS\Database\Capsule;

// Hook para rodar durante o cron diário
add_hook('DailyCronJob', 1, function () {
    // URL base do site
    $baseUrl = 'URL_BASE_DO_SEU_SITE';
    
    // Páginas fixas
    $urls = [
        ['loc' => $baseUrl . '/index.php', 'changefreq' => 'daily', 'priority' => '1.0'],
        ['loc' => $baseUrl . '/clientarea.php', 'changefreq' => 'weekly', 'priority' => '0.8'],
        ['loc' => $baseUrl . '/cart.php', 'changefreq' => 'weekly', 'priority' => '0.8'],
        ['loc' => $baseUrl . '/register.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['loc' => $baseUrl . '/login.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['loc' => $baseUrl . '/announcements.php', 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['loc' => $baseUrl . '/knowledgebase.php', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['loc' => $baseUrl . '/contact.php', 'changefreq' => 'yearly', 'priority' => '0.4'],
    ];
    
    // Consulta produtos ativos e seus slugs
    $products = Capsule::table('tblproducts')
        ->join('tblproducts_slugs', 'tblproducts.id', '=', 'tblproducts_slugs.product_id')
        ->where('tblproducts.hidden', '=', 0) // Apenas produtos visíveis
        ->where('tblproducts.retired', '=', 0) // Excluir produtos retirados
        ->where('tblproducts_slugs.active', '=', 1) // Apenas slugs ativos
        ->get(['tblproducts.id', 'tblproducts.name', 'tblproducts_slugs.slug', 'tblproducts_slugs.group_slug']);
    
    foreach ($products as $product) {
        // Usa o slug para gerar a URL amigável do produto
        $urls[] = [
            'loc' => $baseUrl . '/store/' . $product->group_slug . '/' . $product->slug,
            'changefreq' => 'monthly',
            'priority' => '0.7'
        ];
    }
    
    // Gerar o XML
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    
    foreach ($urls as $url) {
        $sitemap .= '    <url>' . PHP_EOL;
        $sitemap .= '        <loc>' . htmlspecialchars($url['loc']) . '</loc>' . PHP_EOL;
        $sitemap .= '        <changefreq>' . $url['changefreq'] . '</changefreq>' . PHP_EOL;
        $sitemap .= '        <priority>' . $url['priority'] . '</priority>' . PHP_EOL;
        $sitemap .= '    </url>' . PHP_EOL;
    }
    
    $sitemap .= '</urlset>';
    
    // Salvar o arquivo sitemap.xml na raiz do WHMCS
    $filePath = ROOTDIR . '/sitemap.xml';
    file_put_contents($filePath, $sitemap);
});
?>