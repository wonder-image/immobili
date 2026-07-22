<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Immagine (o planimetria) di un immobile.
 *
 * La sincronizzazione registra soltanto l'immagine a **massima risoluzione**
 * (`source_url`) con `resized = 'false'`. Un processo separato (ImageProcessor)
 * scarica l'originale in locale (`file`) e genera le varianti responsive
 * (webp + formati di default del sistema wonder-image/app) impostando
 * `resized = 'true'`. Le due fasi restano su piani distinti perché gli immobili
 * hanno molte immagini pesanti.
 *
 * Le immagini caricate a mano (immobili manuali) arrivano già con `file`
 * valorizzato e `resized = 'true'`.
 */
final class ImmobileImmagine extends Model
{
    public static string $table  = 'immobili_immagini';
    public static string $folder = 'immobili';
    public static string $icon   = 'bi bi-images';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'immobile_id',
                'external_id',
                'tipo',
                'planimetria',
                'position',
                'titolo',
                'source_url',
                'file',
                'upload',
                'resized',
            ]),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_immobile' => ['index' => 'immobile_id'],
            'ind_resized'  => ['index' => 'resized'],
            'ind_position' => ['index' => 'position'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('immobile_id')->number()->decimals(0),
            Field::key('external_id')->text(),
            Field::key('tipo')->text(),
            Field::key('planimetria')->text(),
            Field::key('position')->number()->decimals(0),
            Field::key('titolo')->text()->sanitizeFirst(),

            // URL remoto a massima risoluzione fornito dal feed.
            Field::key('source_url')->text()->sanitize(false),
            // Path locale relativo dell'originale scaricato (base per le varianti).
            Field::key('file')->text()->sanitize(false),
            // Immagine caricata a mano (immobili manuali): il framework genera
            // automaticamente webp + varianti responsive all'upload.
            Field::key('upload')->image(),
            // 'true' quando le varianti responsive sono state generate.
            Field::key('resized')->text(),
        ];
    }
}
