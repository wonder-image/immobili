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
3. Salva e premi **Sincronizza ora** (o attendi il cron).

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
sincronizzazione e il link alla guida per configurare i cron.

Dettagli in [Getrix](../provider/getrix.md) e [Gestim](../provider/gestim.md).

## Più feed sullo stesso sito

Puoi collegare più feed contemporaneamente (es. due agenzie Getrix + un feed Gestim). Gli immobili
di ogni feed sono tracciati da `provider` e `feed_source_id`: la sincronizzazione di un feed **non**
tocca gli immobili degli altri.

## Sincronizzazione automatica

Gli endpoint HTTP si autenticano con il **token di un utente API dedicato** (`@immobili`), creato
automaticamente dal modulo; la CLI locale non richiede token. Il modo di aggiornare dipende dal
gestionale:

- **Getrix — pull**: serve un cron che legge il feed periodicamente.
- **Gestim — push**: è Gestim a chiamare il tuo URL; **niente** cron di sync.
- **Immagini**: cron separato per il resize webp, per entrambi i provider.

I comandi cron esatti, sia CLI sia HTTP, sono nella
[guida ai cron](../riferimento/api-e-sync.md), la stessa linkata dal pannello del feed. Per ogni task
va configurata una sola modalità.
