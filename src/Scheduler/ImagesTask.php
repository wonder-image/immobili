<?php

namespace Wonder\Plugin\Immobili\Scheduler;

use Wonder\App\Scheduler\AbstractTask;
use Wonder\App\Scheduler\Context;
use Wonder\Plugin\Immobili\Media\ImageProcessor;

/**
 * Secondo piano della pipeline immagini, come attività dello scheduler del
 * core: scarica gli originali e genera le varianti responsive a lotti.
 *
 * Separata dalla sincronizzazione perché le foto sono molte e pesanti; giri
 * brevi e frequenti tengono il worker sotto il timeout. Nasce **accesa**:
 * senza immagini in coda non fa niente.
 */
final class ImagesTask extends AbstractTask
{
    public function key(): string
    {
        return 'immobili.images';
    }

    public function label(): string
    {
        return 'Immobili: ridimensionamento delle immagini';
    }

    public function expression(): string
    {
        return '*/5 * * * *';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function timeout(): int
    {
        return 300;
    }

    public function defaultParameters(): array
    {
        return ['limit' => 30];
    }

    public function validate(array $parameters): array
    {
        $unknown = array_diff(array_keys($parameters), ['limit']);

        if ($unknown !== []) {
            throw new \InvalidArgumentException('Parametri non ammessi: '.implode(', ', $unknown).'.');
        }

        $limit = $parameters['limit'] ?? 30;

        if (filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]) === false) {
            throw new \InvalidArgumentException('limit deve essere un intero da 1 a 200.');
        }

        return ['limit' => (int) $limit];
    }

    public function run(Context $context): array
    {
        $limit = $this->validate($context->parameters + $this->defaultParameters())['limit'];
        $result = (new ImageProcessor())->process($limit);

        if ((int) ($result['failed'] ?? 0) > 0) {
            throw new \RuntimeException('Immagini non elaborate: '.(int) $result['failed'].' su '.((int) ($result['processed'] ?? 0) + (int) $result['failed']).'. In coda: '.(int) ($result['pending'] ?? 0).'.');
        }

        return [
            'processed' => (int) ($result['processed'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
        ];
    }
}
