# Immagini e media

Le immagini seguono una pipeline su **due piani distinti**, perché ogni immobile ha molte immagini
pesanti (anche ~20 da 3-4 MB): tenere sincronizzazione e ridimensionamento separati evita che la sync
diventi lenta o vada in timeout.

## Primo piano — sincronizzazione (veloce)

Durante la sync di un feed vengono registrate solo le **URL a massima risoluzione** (`source_url`)
delle immagini, con `resized = 'false'`. Nessun download, nessun resize: la sync resta rapida.

Getrix usa la variante `xxxl`; Gestim l'immagine piena.

## Secondo piano — resize (separato)

Un processo dedicato scarica l'originale in locale e genera le **varianti responsive** (webp + i
formati di default del sistema, `RESPONSIVE_IMAGE_SIZES`) tramite l'SDK del framework
`Wonder\Plugin\Custom\Image\ResponsiveImage`, impostando `resized = 'true'`.

```
GET /api/immobili/images/            → elabora un lotto (default 30)
GET /api/immobili/images/?limit=50   → dimensione del lotto
```

Da agganciare a un **cron separato** da quello di sincronizzazione, ad esempio:

```
*/5 * * * * curl -s "https://tuosito.it/api/immobili/images/?limit=40&token=SEGRETO" > /dev/null
```

Finché un'immagine non è processata, il frontend mostra comunque la `source_url` remota (nessuna
immagine rotta).

## Immagini degli immobili manuali

Le immagini caricate a mano (immobili manuali) passano dal campo Image del framework, che genera
**subito** webp e varianti responsive all'upload: non serve il secondo piano.

## Colonne di `immobili_immagini`

`immobile_id`, `external_id`, `tipo` (F/P), `planimetria`, `position`, `titolo`, `source_url`
(URL remota max-res), `file` (originale scaricato), `upload` (immagine caricata a mano), `resized`.
