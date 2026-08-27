# Idealista (export)

A differenza di Getrix e Gestim (che **importano** immobili nel sito), Idealista è un **portale di
pubblicazione**: il modulo **esporta** gli immobili pubblicati nel formato feed di Idealista
(`<ads><ad>…`), che il crawler del portale scarica periodicamente.

## Endpoint del feed

```
GET /immobili/idealista/
```

Restituisce un XML con tutti gli immobili **visibili e non venduti** (feed + manuali). Comunica
questo URL a Idealista come sorgente del feed.

## Campi esportati

Per ogni immobile: id/reference, tipo di operazione (sale/rent), prezzo, superficie, locali/bagni,
classe energetica, indirizzo e coordinate, descrizioni multilingua (`<adComments>` con codice lingua)
e immagini (`<multimediaPath>` a massima risoluzione).

## Note

- Il mapping copre i campi principali del formato di esempio. I **codici/tipi specifici** di Idealista
  (es. `propertyType`, codici lingua) vanno rifiniti sulla specifica ufficiale, inclusa nel progetto
  in [`docs/specifiche/idealista/`](../specifiche/idealista/).
- L'esportatore è in `src/Export/IdealistaExporter.php`: si estende facilmente per coprire ulteriori
  campi richiesti dal portale.
- Per aggiungere altri portali di export (Immobiliare.it, ecc.) si può replicare lo stesso schema.
