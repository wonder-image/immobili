/**
 * Adapter dei marker immobili per Wonder\Elements\Media\GoogleMap.
 *
 * Il ciclo di vita Google Maps è centralizzato dall'Element nell'API
 * window.WonderMaps, che inizializza MapManager (e, per i percorsi,
 * MapNavigator). Qui resta soltanto il markup specifico del dominio.
 */

(function (root) {
    'use strict';

    var categoryIconRules = [
        {
            keywords: ['box', 'garage', 'autorimessa', 'posto auto', 'parking'],
            icon: 'bi-car-front',
        },
        {
            keywords: ['terreno', 'agricolo', 'lotto', 'land'],
            icon: 'bi-tree',
        },
        {
            keywords: [
                'capannone',
                'magazzino',
                'deposito',
                'laboratorio',
                'industriale',
                'warehouse',
                'factory',
            ],
            icon: 'bi-box-seam',
        },
        {
            keywords: ['negozio', 'commerciale', 'showroom', 'retail', 'shop'],
            icon: 'bi-shop',
        },
        {
            keywords: ['ufficio', 'studio professionale', 'office'],
            icon: 'bi-briefcase',
        },
        {
            keywords: [
                'villa',
                'villetta',
                'villino',
                'casa',
                'casale',
                'rustico',
                'chalet',
                'house',
            ],
            icon: 'bi-house-door',
        },
        {
            keywords: [
                'appartamento',
                'attico',
                'loft',
                'mansarda',
                'monolocale',
                'bilocale',
                'trilocale',
                'quadrilocale',
                'penthouse',
                'apartment',
            ],
            icon: 'bi-building',
        },
        {
            keywords: ['hotel', 'albergo', 'residence', 'bed and breakfast'],
            icon: 'bi-buildings',
        },
    ];

    var allowedMarkerIcons = categoryIconRules
        .map(function (rule) {
            return rule.icon;
        })
        .concat(['bi-building']);

    function safeUrl(value) {
        var candidate = String(value || '').trim();

        if (candidate === '') {
            return '';
        }

        try {
            var url = new URL(candidate, document.baseURI);

            return url.protocol === 'http:' || url.protocol === 'https:'
                ? url.href
                : '';
        } catch (error) {
            return '';
        }
    }

    function text(value) {
        return value === undefined || value === null
            ? ''
            : String(value).trim();
    }

    function markerVariant(value) {
        var variant = text(value).toLowerCase();

        return ['default', 'featured', 'rent', 'sold'].indexOf(variant) !== -1
            ? variant
            : 'default';
    }

    function markerMode(value) {
        var mode = text(value)
            .toLowerCase()
            .replace(/[_+\s]+/g, '-');

        return mode === 'icon' ? 'icon' : 'icon-price';
    }

    function normalizedCategory(value) {
        var category = text(value).toLowerCase();

        if (typeof category.normalize === 'function') {
            category = category.normalize('NFD');
        }

        return category
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    function categoryIcon(properties) {
        var explicitIcon = text(properties.markerIcon).toLowerCase();

        if (explicitIcon !== '' && explicitIcon.indexOf('bi-') !== 0) {
            explicitIcon = 'bi-' + explicitIcon;
        }

        if (allowedMarkerIcons.indexOf(explicitIcon) !== -1) {
            return explicitIcon;
        }

        var category = normalizedCategory(properties.category);

        for (var i = 0; i < categoryIconRules.length; i += 1) {
            var rule = categoryIconRules[i];
            var matches = rule.keywords.some(function (keyword) {
                return category.indexOf(keyword) !== -1;
            });

            if (matches) {
                return rule.icon;
            }
        }

        return 'bi-building';
    }

    function appendIcon(element, iconName) {
        var icon = document.createElement('i');
        icon.className = 'bi ' + iconName;
        icon.setAttribute('aria-hidden', 'true');
        element.appendChild(icon);
    }

    function markerContent(properties) {
        properties = properties && typeof properties === 'object'
            ? properties
            : {};

        var nameText = text(properties.name) || 'Immobile';
        var priceText = text(properties.price);
        var surfaceText = text(properties.surface);
        var variant = markerVariant(properties.variant);
        var variantLabel = text(properties.variantLabel);
        var requestedMode = markerMode(properties.markerMode);
        var compactMode = requestedMode === 'icon-price' && priceText !== ''
            ? 'icon-price'
            : 'icon';
        var iconName = categoryIcon(properties);

        var content = document.createElement('div');
        content.classList.add('wi-marker', 'property');
        content.classList.add('property--' + variant);
        content.classList.add('property--mode-' + compactMode);
        content.setAttribute('aria-expanded', 'false');
        content.setAttribute('aria-label', nameText);
        content.dataset.markerMode = requestedMode;
        content.dataset.markerIcon = iconName;

        if (properties.id !== undefined && properties.id !== null) {
            content.dataset.propertyId = String(properties.id).replace(/[^A-Za-z0-9_-]/g, '');
        }

        var compact = document.createElement('div');
        compact.className = 'property__compact';

        var icon = document.createElement('span');
        icon.className = 'property__compact-icon';
        icon.setAttribute('aria-hidden', 'true');
        appendIcon(icon, iconName);
        compact.appendChild(icon);

        if (compactMode === 'icon-price') {
            var compactLabel = document.createElement('span');
            compactLabel.className = 'property__compact-label';
            compactLabel.textContent = priceText;
            compact.appendChild(compactLabel);
        }

        content.appendChild(compact);

        var card = document.createElement('article');
        card.className = 'property__card';

        var media = document.createElement('div');
        media.className = 'property__media';

        var cover = safeUrl(properties.cover);
        if (cover !== '') {
            var image = document.createElement('img');
            image.className = 'property__image';
            image.src = cover;
            image.alt = '';
            image.loading = 'lazy';
            image.decoding = 'async';
            image.addEventListener('error', function () {
                image.remove();
                media.classList.add('property__media--fallback');
            }, { once: true });
            media.appendChild(image);
        } else {
            media.classList.add('property__media--fallback');
        }

        var mediaFallback = document.createElement('span');
        mediaFallback.className = 'property__media-fallback';
        mediaFallback.setAttribute('aria-hidden', 'true');
        appendIcon(mediaFallback, iconName);
        media.appendChild(mediaFallback);
        card.appendChild(media);

        var details = document.createElement('div');
        details.className = 'property__details';

        if (variantLabel !== '') {
            var eyebrow = document.createElement('span');
            eyebrow.className = 'property__eyebrow';
            eyebrow.textContent = variantLabel;
            details.appendChild(eyebrow);
        }

        var href = safeUrl(properties.url);
        var name = document.createElement(href !== '' ? 'a' : 'div');
        name.className = 'property__name';
        name.textContent = nameText;

        if (href !== '') {
            name.href = href;
            name.setAttribute('aria-label', 'Apri la scheda di ' + nameText);
            name.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        }

        details.appendChild(name);

        var facts = document.createElement('div');
        facts.className = 'property__facts';

        if (priceText !== '') {
            var price = document.createElement('strong');
            price.className = 'property__price';
            price.textContent = priceText;
            facts.appendChild(price);
        }

        if (surfaceText !== '') {
            var surface = document.createElement('span');
            surface.className = 'property__surface';
            appendIcon(surface, 'bi-arrows-fullscreen');

            var surfaceLabel = document.createElement('span');
            surfaceLabel.textContent = surfaceText;
            surface.appendChild(surfaceLabel);
            facts.appendChild(surface);
        }

        details.appendChild(facts);
        card.appendChild(details);
        content.appendChild(card);

        function syncExpandedState() {
            var expanded = content.classList.contains('highlight');
            content.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            compact.setAttribute('aria-hidden', expanded ? 'true' : 'false');
            card.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        }

        syncExpandedState();

        if (typeof MutationObserver === 'function') {
            var observer = new MutationObserver(syncExpandedState);
            observer.observe(content, {
                attributes: true,
                attributeFilter: ['class'],
            });
        }

        return content;
    }

    var api = root.ImmobiliMaps || {};
    api.markerContent = markerContent;
    root.ImmobiliMaps = api;
})(window);
