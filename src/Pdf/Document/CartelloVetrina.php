<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Document;

use Wonder\Plugin\Immobili\Pdf\Block\EnergyClassBox;
use Wonder\Plugin\Immobili\Pdf\Block\LogoBlock;
use Wonder\Plugin\Immobili\Pdf\Block\QrBlock;
use Wonder\Plugin\Immobili\Pdf\Support\ImageFitter;

/**
 * Cartello "vetrina" A4 orizzontale: foto di copertina a tutto sfondo incorniciata
 * da bande nel colore primario, con logo, contratto, QR, classe energetica e
 * telefono. Se `config['sold']` è attivo, sovrappone la fascia diagonale
 * VENDUTO/AFFITTATO (in base al tipo di contratto).
 */
final class CartelloVetrina extends ImmobileDocument
{
    protected function orientation(): string
    {
        return 'L';
    }

    protected function margins(): array
    {
        return [0.0, 0.0, 0.0];
    }

    protected function prefix(): string
    {
        return 'vetrina';
    }

    /** File di cache distinto per la variante "venduto". */
    protected function cacheKind(): string
    {
        return 'vetrina'.(!empty($this->config['sold']) ? '-sold' : '');
    }

    protected function render(): void
    {
        $header = (array) ($this->config['header'] ?? []);
        $contactsCfg = (array) ($this->config['contacts'] ?? []);

        // Sfondo primario (in caso manchi la foto).
        $this->fill($this->ctx->primary);
        $this->pdf->Rect(0, 0, 297, 210, 'F');

        // Foto di copertina a tutto riquadro.
        $cover = (string) ($this->immobile->cover ?? '');
        if ($cover === '') {
            $images = is_array($this->immobile->images ?? null) ? $this->immobile->images : [];
            $cover = (string) ($images[0]['url'] ?? ($images[0]['src'] ?? ''));
        }
        $geom = ImageFitter::contain($cover, 7.5, 7.5, 282, 195, 'center');
        if ($geom['w'] > 0.0) {
            $this->pdf->Image($cover, $geom['x'], $geom['y'], $geom['w'], $geom['h']);
        }

        // Bande di cornice nel colore primario.
        $this->fill($this->ctx->primary);
        $this->pdf->Rect(0, 0, 297, 35, 'F');
        $this->pdf->Rect(0, 180, 297, 30, 'F');
        $this->pdf->Rect(0, 0, 15, 210, 'F');
        $this->pdf->Rect(282, 0, 15, 210, 'F');

        // Bordo.
        $this->draw($this->ctx->secondary);
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Rect(7.5, 7.5, 282, 195, 'D');

        // Logo (in alto a sinistra).
        if (($header['logo'] ?? false) && $this->ctx->logo !== '') {
            LogoBlock::render($this->pdf, $this->ctx->logo, 15, 13, 100, 15, 'left');
        }

        // Tipo di contratto (in alto a destra).
        $this->text($this->ctx->secondary);
        $this->pdf->FontBold(40);
        $this->pdf->SetXY(120, 14);
        $this->pdf->MultiCell(162, 15, $this->t((string) ($this->immobile->contratto ?? ''), true), 0, 'R', false);

        // QR code.
        QrBlock::render($this->pdf, (string) ($this->immobile->qrcode ?? ''), 131, 178 - 33, 30);

        // Classe energetica (in basso a sinistra).
        if ($this->config['energy'] ?? false) {
            EnergyClassBox::render(
                $this->pdf,
                $this->ctx,
                20,
                150,
                26,
                (string) ($this->immobile->classe ?? ''),
                (string) ($this->row['ipe'] ?? ''),
            );
        }

        // Testo breve (banda inferiore, sinistra).
        $breve = (string) ($this->immobile->descrizione_breve ?? '');
        if ($breve !== '') {
            $this->text($this->ctx->primary->neutral());
            $this->pdf->Font(12);
            $this->pdf->SetXY(15, 184);
            $this->pdf->MultiCell(150, 5, $this->t($breve), 0, 'L', false);
        }

        // Telefono (banda inferiore, destra).
        if (($contactsCfg['tel'] ?? false) && $this->ctx->contacts->tel !== '') {
            $this->text($this->ctx->primary->neutral());
            $this->pdf->FontBold(26);
            $this->pdf->SetXY(147, 186);
            $this->pdf->MultiCell(135, 12, $this->t($this->ctx->contacts->tel), 0, 'R', false);
        }

        // Fascia diagonale VENDUTO / AFFITTATO.
        if (!empty($this->config['sold'])) {
            $this->soldBanner();
        }
    }

    private function soldBanner(): void
    {
        $isRent = strtoupper((string) ($this->row['contratto_id'] ?? '')) === 'A';
        $label = $isRent ? 'AFFITTATO' : 'VENDUTO';

        $this->fill($this->ctx->secondary);
        $this->text($this->ctx->secondary->neutral());

        $this->pdf->StartTransform();
        $this->pdf->Rotate(45, 100, 210);
        $this->pdf->Rect(100, 210, 300, 30, 'F');
        $this->pdf->FontBold(60);
        $this->pdf->Text($isRent ? 190 : 175, 231, $this->t($label));
        $this->pdf->StopTransform();
    }
}
