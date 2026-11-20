<?php

// php tests/listing-status.php [site-root] — no database or network required.
$module = dirname(__DIR__);
$site = $argv[1] ?? null;
require ($site ?: $module).'/vendor/autoload.php';

use Wonder\Plugin\Immobili\Support\ListingStatus as S;

$check = static function (bool $result, string $message): void {
    if (!$result) { throw new RuntimeException($message); }
};

// Valori coerenti per ogni stato; venduto/affittato seguono il contratto.
$check(S::values('purchased', 'V') === ['stato' => 'purchased', 'visible' => 'true', 'sold' => 'true'], 'Sale marked sold');
$check(S::values('purchased', 'A')['stato'] === 'rented', 'Sold on a rental becomes rented');
$check(S::values('rented', 'V')['stato'] === 'purchased', 'Rented on a sale becomes purchased');
$check(S::values('active', 'A') === ['stato' => 'active', 'visible' => 'true', 'sold' => 'false'], 'Back to available');
$check(S::values('suspended') === ['stato' => 'suspended', 'visible' => 'false', 'sold' => 'false'], 'Suspended is hidden');

// Lettura: stato coerente con i flag, altrimenti derivato dai flag.
$check(S::fromRow(['stato' => 'active', 'visible' => 'true', 'sold' => 'false']) === 'active', 'Coherent stato kept');
$check(S::fromRow(['stato' => 'active', 'visible' => 'false', 'sold' => 'false']) === 'suspended', 'Visible toggle wins over stale stato');
$check(S::fromRow(['stato' => 'active', 'visible' => 'true', 'sold' => 'true', 'contratto_id' => 'A']) === 'rented', 'Feed sold on rental reads rented');
$check(S::fromRow(['stato' => 'purchased']) === 'purchased', 'Stato without flags kept');
$check(S::fromValues(['stato' => 'rented', 'visible' => 'false']) === 'rented', 'Form stato wins');
$check(S::fromValues(['sold' => 'true', 'contratto_id' => 'V']) === 'purchased', 'Legacy flags derive stato');

// Politica di default: elimina, ma conserva venduti/affittati.
$sale = ['contratto_id' => 'V', 'visible' => 'true', 'sold' => 'false', 'stato' => 'active'];
$rent = ['contratto_id' => 'A', 'visible' => 'true', 'sold' => 'false', 'stato' => 'active'];
$check(S::vanishedPolicy('') === 'delete' && S::vanishedPolicy('RETAIN') === 'retain', 'Policy normalized');
$check(S::vanished($sale, 'delete') === S::DELETE, 'Default deletes available listings');
$check(S::vanished(['sold' => 'true'] + $sale, 'delete') === S::KEEP, 'Default keeps sold listings');

// Politica retain (Agliati): vendita nascosta, affitto affittato, solo alla transizione.
$check(S::vanished($sale, 'retain') === S::values('suspended'), 'Vanished sale is hidden');
$check(S::vanished($rent, 'retain') === S::values('rented', 'A'), 'Vanished rental becomes rented');
$check(S::vanished(['sold' => 'true', 'stato' => 'purchased'] + $sale, 'retain') === S::KEEP, 'Sold sale stays among sold');
$check(S::vanished($sale, 'retain', false) === S::KEEP, 'Manual changes survive later syncs');

// Ritorno nel feed: ripristina solo lo stato impostato dalla regola.
$check(S::reappeared(S::values('suspended') + $sale, 'retain') === S::values('active'), 'Hidden sale comes back');
$check(S::reappeared(S::values('rented', 'A') + $rent, 'retain') === S::values('active', 'A'), 'Rented rental comes back');
$check(S::reappeared(S::values('purchased') + $sale, 'retain') === [], 'Manual sold sale untouched');
$check(S::reappeared(S::values('suspended') + $sale, 'delete') === [], 'Default policy never restores');

echo "listing-status: ok\n";
