# Traduzioni e bilingua

Il modulo è predisposto per **italiano e inglese**.

## File di lingua

```
lang/it/pages.json · components.json · urls.json
lang/en/pages.json · components.json · urls.json
```

- `pages.json` / `components.json`: testi dell'interfaccia, sotto la chiave namespace `immobili`
  (es. `pages.immobili.list.title`, `components.immobili.filters.search`). Usati nelle view con
  `__t('pages.immobili.list.title')`.
- `urls.json`: slug localizzati delle rotte (es. `it/immobili`, `en/properties`), risolti con
  `__r('immobili.list')`.

Il path lingua è registrato al boot in `config/module.php` via
`LanguageContext::addUrlsPath(Immobili::langPath())`.

## Contenuti dinamici

Le **descrizioni** degli immobili sono già multilingua: i feed le forniscono per lingua e vengono
salvate in `immobili_descrizioni` (una riga per `lingua`). Il dettaglio mostra la descrizione nella
lingua corrente (`__l()`) con fallback all'italiano.

## Aggiungere una lingua

1. Duplica `lang/it/` in `lang/<locale>/` e traduci i valori.
2. Assicurati che il sito abbia la lingua abilitata.
3. I feed che forniscono descrizioni in quella lingua le mostreranno automaticamente.

## Personalizzare i testi dal sito

Puoi sovrascrivere una chiave definendo la stessa nel `lang/<locale>/` del sito: le traduzioni del
sito hanno priorità su quelle del modulo.
