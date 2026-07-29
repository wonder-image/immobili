<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Document;

use Wonder\Plugin\Immobili\Pdf\Block\EnergyClassBox;
use Wonder\Plugin\Immobili\Pdf\Block\LogoBlock;
use Wonder\Plugin\Immobili\Pdf\Block\QrBlock;

/**
 * Cartello immobile A4 orizzontale (materiale da vetrina/agenzia): logo, tipo di
 * contratto in grande, pannello con prezzo e testo breve, QR code, riquadro
 * classe energetica e telefono. Sfondo nel colore primario del branding.
 */
final class CartelloImmobile extends ImmobileDocument
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
        return 'cartello';
    }

    protected function render(): void
    {
        $header = (array) ($this->config['header'] ?? []);
        $contactsCfg = (array) ($this->config['contacts'] ?? []);

        // Sfondo + bordo.
        $this->fill($this->ctx->primary);
        $this->pdf->Rect(0, 0, 297, 210, 'F');
        $this->draw($this->ctx->secondary);
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Rect(7.5, 7.5, 282, 195, 'D');

        // Logo (centrato in alto).
        if (($header['logo'] ?? false) && $this->ctx->logo !== '') {
            LogoBlock::render($this->pdf, $this->ctx->logo, 58.5, 14, 180, 20, 'center');
        }

        // Tipo di contratto in grande.
        $this->text($this->ctx->secondary);
        $this->pdf->FontBold(70);
        $this->pdf->SetXY(0, 44);
        $this->pdf->MultiCell(297, 28, $this->t((string) ($this->immobile->contratto ?? ''), true), 0, 'C', false);

        // Pannello bianco: prezzo + testo breve.
        $this->pdf->SetFillColor(255, 255, 255);
        $this->pdf->Rect(15, 95, 267, 62, 'F');

        $prezzo = (string) ($this->immobile->prettyPrezzo ?? ($this->immobile->prezzo ?? ''));
        if ($prezzo !== '') {
            $this->text($this->ctx->primary);
            $this->pdf->FontBold(34);
            $this->pdf->SetXY(15, 101);
            $this->pdf->MultiCell(267, 16, $this->t($prezzo), 0, 'C', false);
        }

        $breve = trim((string) ($this->immobile->descrizione_breve ?? ''))
            ?: (string) ($this->immobile->prettyName ?? '');
        if ($breve !== '') {
            $this->text(new \Wonder\Plugin\Immobili\Pdf\Support\Color(30, 30, 30));
            $this->pdf->Font(16);
            $this->pdf->SetXY(25, 123);
            $this->pdf->MultiCell(247, 7, $this->t($breve), 0, 'C', false);
        }

        // QR code (in basso a sinistra).
        QrBlock::render($this->pdf, (string) ($this->immobile->qrcode ?? ''), 18, 166, 30);

        // Riquadro classe energetica (in basso a destra).
        if ($this->config['energy'] ?? false) {
            EnergyClassBox::render(
                $this->pdf,
                $this->ctx,
                251,
                162,
                28,
                (string) ($this->immobile->classe ?? ''),
                (string) ($this->row['ipe'] ?? ''),
            );
        }

        // Telefono.
        if (($contactsCfg['tel'] ?? false) && $this->ctx->contacts->tel !== '') {
            $this->text($this->ctx->primary->neutral());
            $this->pdf->FontBold(30);
            $this->pdf->SetXY(0, 178);
            $this->pdf->MultiCell(297, 14, $this->t($this->ctx->contacts->tel), 0, 'C', false);
        }
    }
}
