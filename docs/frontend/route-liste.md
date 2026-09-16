# Route delle liste

| URL | Nome route | Selezione |
| --- | --- | --- |
| /immobili/ | immobili.list | Disponibili in vendita o affitto |
| /immobili/in-vendita/ | immobili.in_vendita | Disponibili, contratto vendita |
| /immobili/in-affitto/ | immobili.in_affitto | Disponibili, contratto affitto |
| /immobili/venduti/ | immobili.sold | Venduti, contratto vendita |
| /immobili/affittati/ | immobili.affittati | Venduti, contratto affitto |
| /residenze/ | residenze.list | Tutte le residenze visibili |
| /residenze/in-costruzione/ | residenze.in_costruzione | Lavori non completati |
| /residenze/realizzate/ | residenze.realizzate | Lavori completati |

Tutte le route immobili renderizzano `pages/frontend/immobili/list.php`; tutte le residenze renderizzano `pages/frontend/residenze/list.php`. ListingRoute centralizza i preset. Il nome corrente viene letto da ROUTE_META; gli override devono usare ListingRoute::filters e il relativo flag sold.

Il completamento segue ResidenzaQuery: mese di fine precedente al mese corrente; mese mancante = dicembre, anno mancante = non completata. Tutte le liste escludono record nascosti o eliminati.

Le query legacy contratto=V/A e stato=in_costruzione/completata reindirizzano con 301 alla route corrispondente, conservando gli altri filtri e la pagina. Sulle route dedicate il preset prevale: parametri duplicati o discordanti vengono rimossi con redirect. /residenze/completate/ resta un alias 301 verso /residenze/realizzate/.

Per aggiornare un sito: allineare le due view list, rimuovere route locali duplicate, aggiornare navigazione e traduzioni. Nessuna view sold separata è più usata dalle route.

Verifica: `php tests/listing-routes.php`, poi dal sito `php forge update --local` e `php forge start`.
