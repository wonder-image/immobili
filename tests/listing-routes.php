<?php
require dirname(__DIR__).'/vendor/autoload.php';
use Wonder\Plugin\Immobili\Catalog\ListingRoute;
use Wonder\Plugin\Immobili\Catalog\ImmobileQuery;
use Wonder\Plugin\Immobili\Catalog\ResidenzaQuery;
use Wonder\Http\Route;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
Route::reset();
require dirname(__DIR__).'/config/routes/route.frontend.php';
$routes = array_column(Route::all(), null, 'name');
foreach (ListingRoute::PROPERTIES as $name => $preset) {
    check(str_ends_with($routes[$name]['handler'], '/immobili/list.php'), 'Shared property view: '.$name);
    $filters = ListingRoute::filters($name, ['contratto' => 'other']);
    $where = (new ImmobileQuery())->where($filters, $preset['sold']);
    check(str_contains($where, "`sold` = '".($preset['sold'] ? 'true' : 'false')."'"), 'Correct sold state');
    if (isset($preset['filters']['contratto'])) {
        check($filters['contratto'] === $preset['filters']['contratto'], 'Route contract wins over query');
    }
}
foreach (ListingRoute::RESIDENCES as $name => $preset) {
    check(str_ends_with($routes[$name]['handler'], '/residenze/list.php'), 'Shared residence view');
    $filters = ListingRoute::filters($name, []);
    $where = (new ResidenzaQuery())->where($filters, new DateTimeImmutable('2026-09-16'));
    if ($name === 'residenze.in_costruzione') check(str_contains($where, 'NOT (COALESCE'), 'Under construction');
    if ($name === 'residenze.realizzate') check(str_contains($where, '< 202609') && !str_contains($where, 'NOT'), 'Completed');
}
$redirect = ListingRoute::canonical('immobili.list', ['contratto'=>'A', 'page'=>'2','comune'=>'Milano']);
check($redirect === ['route'=>'immobili.in_affitto','query'=>['page'=>'2','comune'=>'Milano']], 'Preserve other query parameters');
check(ListingRoute::canonical('immobili.in_affitto', ['contratto'=>'V']) === ['route'=>'immobili.in_affitto','query'=>[]], 'Conflicting contract removed');
check(ListingRoute::canonical('residenze.list', ['stato'=>'completata'])['route'] === 'residenze.realizzate', 'Legacy residence query');
check(ListingRoute::canonical('immobili.list', []) === null, 'No redirect loop');
echo "Listing route checks passed\n";
