# Feed e gestionali

Ogni sito può collegare **uno o più feed**. Un feed è una riga gestita dal backend
(**Immobili → Feed**) e indica quale gestionale usare (`provider`) e le relative credenziali.

## Aggiungere un feed

1. Backend → **Immobili → Feed → Aggiungi feed**.
2. Compila:
   - **Nome**: etichetta interna (es. "Getrix agenzia Milano").
   - **Gestionale**: `Getrix` o `Gestim` (o un provider custom registrato).
   - **Attivo**: se spento, il feed viene ignorato dalla sincronizzazione.
   - **Credenziali**: variano per provider (vedi sotto).
   - **Opzioni**: salvataggio immagini in locale, default di visibilità/evidenza/venduto.
3. Salva e premi **Sincronizza ora** (o attendi la pianificazione).

## Credenziali per provider

I due campi generici (`Codice / ID Agenzia`, `ID Sito`) assumono significato diverso
a seconda del gestionale:

| Campo             | Getrix               | Gestim         |
| ----------------- | -------------------- | -------------- |
| Codice / ID Agenzia | Getrix ID (account)  | `society_id`   |
| ID Sito           | —                    | `site_id`      |

Il form del feed **mostra solo i campi pertinenti** al gestionale selezionato:
scegliendo **Getrix** resta il solo *Codice / ID Agenzia*; scegliendo **Gestim** compare
anche *ID Sito*. Gestim **non** richiede né URL feed né password: tutti i dati (immobili,
agenzie, tipologie/lookup) arrivano nello ZIP inviato in push.

Il pannello mostra inoltre, come testo secondario, il **token API** dedicato alla
sincronizzazione (serve per l'URL di notifica di Gestim) e il link alle pianificazioni.

Dettagli in [Getrix](../provider/getrix.md) e [Gestim](../provider/gestim.md).

## Più feed sullo stesso sito

Puoi collegare più feed contemporaneamente (es. due agenzie Getrix + un feed Gestim). Gli immobili
di ogni feed sono tracciati da `provider` e `feed_source_id`: la sincronizzazione di un feed **non**
tocca gli immobili degli altri.

## Sincronizzazione automatica

Il modulo registra le sue attività nello **scheduler di wonder-image/app**: nessun cron da
inserire a mano. Il modo di aggiornare dipende dal gestionale:

- **Getrix — pull**: l'attività `immobili.sync` legge i feed pull ogni 30 minuti.
- **Gestim — push**: è Gestim a chiamare il tuo URL (con il token dell'utente API `@immobili`);
  la sincronizzazione pianificata lo salta.
- **Immagini**: l'attività `immobili.images` elabora il resize webp ogni 5 minuti, per entrambi.

Frequenze e parametri si gestiscono in **Backend → Dev → Automazioni → Pianificazioni**. Dettagli in
[Pianificazioni](../riferimento/api-e-sync.md#pianificazioni), la pagina linkata dal pannello del feed.
