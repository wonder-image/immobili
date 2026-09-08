# Filtri delle residenze

La lista `residenze.list` (`/residenze/` per default) accetta due parametri GET,
combinabili tra loro. Il form `residenze/filters` funziona senza JavaScript.
I risultati includono solo residenze visibili e non eliminate, ordinate per
`position ASC`.

## Parametri

| Parametro | Valori ammessi | Significato |
| --- | --- | --- |
| `stato` | `in_costruzione`, `completata` | Stato calcolato dal mese e anno di consegna |
| `stato_appartamenti` | `disponibili`, `non_disponibili` | Presenza o assenza di immobili collegati disponibili |

Un parametro assente, vuoto o non valido non applica il relativo filtro.
I valori dei parametri rimangono uguali anche nelle pagine in inglese.

## Query possibili

Gli URL seguenti usano il percorso predefinito; nelle view generare il percorso
con `__r('residenze.list')` per rispettare la configurazione delle route.

| Selezione | URL |
| --- | --- |
| Tutte | `/residenze/` |
| In costruzione | `/residenze/?stato=in_costruzione` |
| Completate | `/residenze/?stato=completata` |
| Con appartamenti disponibili | `/residenze/?stato_appartamenti=disponibili` |
| Senza appartamenti disponibili | `/residenze/?stato_appartamenti=non_disponibili` |
| In costruzione, con disponibilità | `/residenze/?stato=in_costruzione&stato_appartamenti=disponibili` |
| In costruzione, senza disponibilità | `/residenze/?stato=in_costruzione&stato_appartamenti=non_disponibili` |
| Completate, con disponibilità | `/residenze/?stato=completata&stato_appartamenti=disponibili` |
| Completate, senza disponibilità | `/residenze/?stato=completata&stato_appartamenti=non_disponibili` |

## Calcolo dello stato

Il filtro legge `fine_anno` e `fine_mese`. La residenza è **completata dal mese
successivo alla consegna**: una consegna a settembre 2026 resta “In costruzione”
per tutto settembre e diventa “Completata” il 1° ottobre 2026.

- Se il mese è assente o fuori dall'intervallo 1–12, viene usato dicembre.
- Se l'anno è assente o non positivo, la residenza resta “In costruzione”.
- Il confronto usa la data corrente del runtime PHP, senza confrontare il giorno.

Questo filtro è indipendente dai campi manuali `stato` e `sold` della residenza.
Le etichette delle card, gestite da `ResidenzaPresenter::stato()`, continuano a
seguire la propria logica, inclusi gli override manuali.

## Disponibilità e campo in decorate

Un immobile è disponibile quando è collegato tramite `residenza_id` e ha:

- `visible = 'true'`;
- `deleted = 'false'`;
- `sold = 'false'`.

Il conteggio include tutti gli immobili collegati che rispettano questi criteri,
senza ulteriori filtri per tipologia o contratto. Non viene ricavato dal campo
`unita_abitative` della residenza.

`Residenza::decorate()` aggiunge `appartamenti_disponibili` come intero. Il filtro
`disponibili` richiede almeno un immobile disponibile; `non_disponibili` richiede
zero immobili disponibili e include anche le residenze senza immobili collegati.

```php
use Wonder\Plugin\Immobili\Models\Residenza;

$residenza = Residenza::safeFind([
    'slug' => $slug,
    'visible' => 'true',
    'deleted' => 'false',
], 1);

if (is_array($residenza) && isset($residenza['id'])) {
    $disponibili = $residenza['appartamenti_disponibili']; // int
}
```

## Query da PHP

`ResidenzaQuery::filters()` normalizza i parametri e `where()` produce la
condizione SQL utilizzabile con `Residenza::safeFind()`.

```php
use Wonder\Plugin\Immobili\Catalog\ResidenzaQuery;
use Wonder\Plugin\Immobili\Models\Residenza;

$query = new ResidenzaQuery();
$filters = $query->filters([
    'stato' => 'in_costruzione',
    'stato_appartamenti' => 'disponibili',
]);

$rows = Residenza::safeFind($query->where($filters), null, 'position', 'ASC');
$rows = is_array($rows) && isset($rows['id'])
    ? [$rows]
    : (is_array($rows) ? $rows : []);

foreach ($rows as $residenza) {
    $disponibili = $residenza['appartamenti_disponibili'];
}
```

Per verificare il comportamento a una data precisa, `where()` accetta un secondo
argomento opzionale:

```php
$where = $query->where($filters, new \DateTimeImmutable('2026-10-01'));
```

La data opzionale modifica solo il confronto della consegna; il conteggio degli
appartamenti usa sempre i dati attuali del database. Filtro e conteggio condividono
il criterio definito da `ResidenzaQuery::availableApartmentsWhere()`.
