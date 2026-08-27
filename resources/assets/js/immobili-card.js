/**
 * Gallery sfogliabile dentro le card di lista (view/components/card/media.php).
 *
 * Un solo listener delegato sul documento, non un'istanza per card: in una
 * griglia di dodici immobili la differenza è dodici oggetti contro uno.
 * Nessuna dipendenza: gli slide sono già nel DOM, qui si sposta solo la classe
 * attiva.
 *
 * Le frecce vivono dentro un <a>: ogni interazione ferma la propagazione,
 * altrimenti sfogliare la gallery aprirebbe la scheda dell'immobile.
 */
(function () {
    'use strict';

    var GALLERY = '[data-immobili-gallery]';
    var SLIDE = '.immobili-card__slide';
    var DOT = '.immobili-card__dot';

    function show(gallery, index) {
        var slides = gallery.querySelectorAll(SLIDE);
        var dots = gallery.querySelectorAll(DOT);

        if (!slides.length) {
            return;
        }

        // Indice circolare: dall'ultima si torna alla prima e viceversa.
        var next = ((index % slides.length) + slides.length) % slides.length;

        slides.forEach(function (slide, i) {
            slide.classList.toggle('is-active', i === next);
        });

        dots.forEach(function (dot, i) {
            dot.classList.toggle('is-active', i === next);
        });

        gallery.dataset.immobiliGalleryIndex = String(next);
    }

    function currentIndex(gallery) {
        return parseInt(gallery.dataset.immobiliGalleryIndex || '0', 10) || 0;
    }

    document.addEventListener('click', function (event) {
        var prev = event.target.closest('[data-immobili-gallery-prev]');
        var next = event.target.closest('[data-immobili-gallery-next]');

        if (!prev && !next) {
            return;
        }

        var gallery = (prev || next).closest(GALLERY);

        if (!gallery) {
            return;
        }

        // La card è un link: senza questo, sfogliare navigherebbe.
        event.preventDefault();
        event.stopPropagation();

        show(gallery, currentIndex(gallery) + (next ? 1 : -1));
    });
}());
