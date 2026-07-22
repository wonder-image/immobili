<?php

namespace Wonder\Plugin\Immobili\Feed\Contracts;

/**
 * Contratto opzionale per i provider che conservano il file sorgente ricevuto.
 *
 * Il path viene esposto al servizio di sincronizzazione solo dopo che il
 * provider ha iniziato a leggere il feed, così può essere registrato nello
 * storico anche quando il parsing fallisce.
 */
interface ArchivesFeedArtifact
{
    public function lastArtifactPath(): string;
}
