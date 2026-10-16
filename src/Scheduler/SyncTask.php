<?php

namespace Wonder\Plugin\Immobili\Scheduler;

use Wonder\App\Scheduler\AbstractTask;
use Wonder\App\Scheduler\Context;
use Wonder\Plugin\Immobili\Feed\FeedSourceConfig;
use Wonder\Plugin\Immobili\Models\System\FeedSource;
use Wonder\Plugin\Immobili\Sync\FeedSyncService;

/**
 * Sincronizzazione dei feed pull (Getrix), come attività dello scheduler del
 * core: nessun cron da inserire a mano.
 *
 * Senza parametri legge tutti i feed attivi; con `feed` solo quello, così si
 * possono aggiungere dal backend più pianificazioni con frequenze diverse.
 * I feed push (Gestim) sono saltati: li aggiorna il gestionale chiamando
 * l'endpoint. Nasce **accesa**: senza feed pull non fa niente.
 */
final class SyncTask extends AbstractTask
{
    public function key(): string
    {
        return 'immobili.sync';
    }

    public function label(): string
    {
        return 'Immobili: sincronizzazione dei feed';
    }

    public function expression(): string
    {
        return '*/30 * * * *';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function timeout(): int
    {
        return 900;
    }

    public function defaultParameters(): array
    {
        return ['feed' => ''];
    }

    public function validate(array $parameters): array
    {
        $unknown = array_diff(array_keys($parameters), ['feed']);

        if ($unknown !== []) {
            throw new \InvalidArgumentException('Parametri non ammessi: '.implode(', ', $unknown).'.');
        }

        $feed = trim((string) ($parameters['feed'] ?? ''));

        if ($feed !== '' && preg_match('/^[1-9][0-9]*$/D', $feed) !== 1) {
            throw new \InvalidArgumentException('feed deve essere l\'id di un feed o vuoto (tutti i feed).');
        }

        return ['feed' => $feed];
    }

    public function run(Context $context): array
    {
        $feed = $this->validate($context->parameters + $this->defaultParameters())['feed'];
        $service = new FeedSyncService();

        if ($feed !== '') {
            $row = FeedSource::findById($feed);

            if (is_array($row) && isset($row['id']) && FeedSyncService::isPush(FeedSourceConfig::fromRow($row))) {
                throw new \RuntimeException('Il feed '.$feed.' è push: lo aggiorna il gestionale, non lo scheduler.');
            }

            $results = [$service->syncById($feed)];
        } else {
            $results = $service->syncAll();
        }

        $imported = 0;
        $errors = [];

        foreach ($results as $result) {
            $imported += (int) ($result['count'] ?? 0);
            $context->log((empty($result['success']) ? 'ERRORE: '.($result['message'] ?? '') : 'OK').' - '.(int) ($result['count'] ?? 0)." immobili\n");

            if (empty($result['success'])) {
                $errors[] = (string) ($result['message'] ?? 'errore sconosciuto');
            }
        }

        if ($errors !== []) {
            throw new \RuntimeException('Sincronizzazione fallita: '.implode('; ', $errors));
        }

        return ['feeds' => count($results), 'imported' => $imported];
    }
}
