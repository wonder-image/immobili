<?php

namespace Wonder\Plugin\Immobili\Resources;

use Wonder\App\ResourceSchema\{ FormField, NavigationSchema, PermissionSchema, RepeaterColumn };
use Wonder\App\Resources\Support\SingletonResource;
use Wonder\Plugin\Immobili\Models\Settings;
use Wonder\Plugin\Immobili\Support\AttributeCatalog;
use Wonder\App\Support\FpdfFonts;
use Wonder\Elements\Form\{ Form };
use Wonder\Elements\Components\{ Container, Card, SectionTitle };

/**
 * Centrale di controllo del modulo Immobili.
 *
 * Da qui si configura la scheda PDF (logo, colori, font e quali attributi
 * dell'immobile mostrare, nell'ordine scelto) e la scheda web (quali attributi
 * mostrare). Le liste di attributi pescano dal catalogo condiviso
 * `AttributeCatalog`; l'ordine è quello dei repeater ordinabili.
 */
final class SettingsResource extends SingletonResource
{
    public static string $model = Settings::class;

    public static function path(): string
    {
        return 'immobili-settings';
    }

    public static function icon(): string
    {
        return 'bi bi-sliders';
    }

    public static function textSchema(): array
    {
        return [
            'label'        => 'impostazioni',
            'plural_label' => 'impostazioni',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'pdf_logo'            => 'Logo PDF',
            'pdf_color_primary'   => 'Colore primario',
            'pdf_color_secondary' => 'Colore secondario',
            'pdf_font'            => 'Font',
            'pdf_font_bold'       => 'Font (grassetto)'
        ];
    }

    public static function formSchema(): array
    {
        $fonts   = FpdfFonts::all();
        $catalog = AttributeCatalog::options();

        return [
            FormField::key('pdf_logo')->select(self::logoOptions())->value('main')->required(),
            FormField::key('pdf_color_primary')->color(),
            FormField::key('pdf_color_secondary')->color(),
            FormField::key('pdf_font')->select($fonts),
            FormField::key('pdf_font_bold')->select($fonts),

            // Liste ordinabili di attributi (righe [{key: <attributo>}]). `nested()`
            // è indispensabile: senza, i campi si chiamerebbero `key[]` e non
            // finirebbero in `$_POST['pdf_facts']` (il valore non verrebbe salvato).
            FormField::key('pdf_facts')
                ->repeater([RepeaterColumn::key('key')->select($catalog)->label('Attributo')->columnSpan(12)])
                ->repeaterSortable(true)
                ->repeaterAddLabel('Aggiungi dato')
                ->nested()
                ->label(false),

            FormField::key('scheda_facts')
                ->repeater([RepeaterColumn::key('key')->select($catalog)->label('Attributo')->columnSpan(12)])
                ->repeaterSortable(true)
                ->repeaterAddLabel('Aggiungi dato')
                ->nested(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form())->components([
            (new Container())->components([

                // Scheda PDF: branding + dati mostrati.
                (new Card())->components([

                    (new SectionTitle('Scheda PDF'))->columnSpan(12),
                    static::getInput('pdf_logo')->columnSpan(12),

                    (new SectionTitle('Colori'))->columnSpan(12),
                    static::getInput('pdf_color_primary')->columnSpan(6),
                    static::getInput('pdf_color_secondary')->columnSpan(6),

                    (new SectionTitle('Font'))->columnSpan(12),
                    static::getInput('pdf_font')->columnSpan(6),
                    static::getInput('pdf_font_bold')->columnSpan(6),

                    (new SectionTitle('Dati mostrati sul PDF'))->columnSpan(12),
                    static::getInput('pdf_facts')->columnSpan(12),

                ])->columns(12)->columnSpan(6),

                // Scheda web dell'immobile: dati mostrati.
                (new Card())->components([

                    (new SectionTitle('Scheda immobile'))->columnSpan(12),
                    static::getInput('scheda_facts')->columnSpan(12),

                ])->columns(12)->columnSpan(6),

            ])->columns(12),
        ]);
    }

    /**
     * I campi `pdf_facts` / `scheda_facts` sono repeater SENZA relazione: il
     * framework non li converte da/verso la colonna JSON in automatico. In
     * SCRITTURA trasformiamo le righe POST del repeater ([{key: attributo}, …])
     * nella lista ordinata di chiavi valide salvata come JSON.
     */
    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        foreach (['pdf_facts', 'scheda_facts'] as $key) {
            $values[$key] = AttributeCatalog::keysFrom($values[$key] ?? null);
        }

        return $values;
    }

    /**
     * In LETTURA: la lista JSON di chiavi salvata torna righe repeater
     * ([{key: attributo}, …]) così l'input mostra la selezione corrente. Se non
     * ancora configurato, mostra i default del contesto (WYSIWYG: quanto salvi è
     * ciò che viene usato da PDF e scheda).
     */
    public static function mutateFormValues(
        array $values,
        string $mode,
        string $context = 'backend'
    ): array {
        foreach (['pdf_facts' => 'pdf', 'scheda_facts' => 'scheda'] as $key => $ctx) {
            $keys = AttributeCatalog::selectedKeys($values[$key] ?? null, $ctx);
            $values[$key] = array_map(
                static fn (string $attr): array => ['key' => $attr],
                $keys
            );
        }

        return $values;
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin', 'immobili_manager']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('immobili', 'Immobili', 'bi-house-door', 500, ['admin', 'immobili_manager'])
            ->title('Impostazioni')
            ->order(40)
            ->authority(['admin', 'immobili_manager']);
    }

    /**
     * Varianti disponibili nella risorsa Media → Logo del framework.
     *
     * @return array<string, string>
     */
    private static function logoOptions(): array
    {
        return [
            'main'       => 'Logo',
            'black'      => 'Logo nero',
            'white'      => 'Logo bianco',
            'icon'       => 'Icona',
            'icon_black' => 'Icona nera',
            'icon_white' => 'Icona bianca',
        ];
    }
}
