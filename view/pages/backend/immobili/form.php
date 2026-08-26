<?php \Wonder\View\View::layout('backend.form'); ?>

<?php
$isFeedRecord = !in_array(
    strtolower(trim((string) ($VALUES['provider'] ?? 'manual'))),
    ['', 'manual'],
    true
);

// Il renderer dei file usa il contesto legacy per risolvere la directory
// `/uploads/immobili/` delle immagini già caricate.
if (is_object($NAME ?? null)) {
    \Wonder\App\LegacyGlobals::set('NAME', $NAME);
}
?>

<?php if ($isFeedRecord): ?>
    <div class="alert alert-info" role="status">
        <?=htmlspecialchars(
            \Wonder\Plugin\Immobili\Support\Forms\ImmobileForm::text('messages.feed_read_only'),
            ENT_QUOTES,
            'UTF-8'
        )?>
    </div>
<?php endif; ?>

<?=\Wonder\Backend\Support\ResourceFormLayoutRenderer::render(
    $FORM_LAYOUT,
    [
        'id' => 'immobile-resource-form',
        'method' => (string) ($FORM_METHOD ?? 'POST'),
        'enctype' => (string) ($FORM_ENCTYPE ?? 'multipart/form-data'),
        'action' => (string) ($FORM_ACTION ?? ''),
    ]
)?>

<script>
    (() => {
        const form = document.getElementById('immobile-resource-form');

        if (!form) {
            return;
        }

        const feedRecord = <?=json_encode($isFeedRecord, JSON_THROW_ON_ERROR)?>;
        const imagePreviewAlt = <?=json_encode(
            \Wonder\Plugin\Immobili\Support\Forms\ImmobileForm::text('fields.image_preview'),
            JSON_THROW_ON_ERROR
        )?>;
        const floorPlanPreviewAlt = <?=json_encode(
            \Wonder\Plugin\Immobili\Support\Forms\ImmobileForm::text('fields.floor_plan_preview'),
            JSON_THROW_ON_ERROR
        )?>;
        const input = (name) => form.querySelector(`[name="${name}"]`);
        const mediaPreviewSelector = [
            '[name^="images["][name$="[preview_url]"]',
            '[name^="floor_plans["][name$="[preview_url]"]',
        ].join(', ');

        const decorateMediaRow = (row) => {
            const previewInput = row.querySelector(mediaPreviewSelector);

            if (!previewInput) {
                return;
            }

            row.classList.add('col-md-6', 'col-lg-4', 'col-xxl-3');
            row.querySelector(':scope > .card')?.classList.add('h-100');

            const actionColumn = row
                .querySelector('.wi-repeater-delete')
                ?.closest('.col-1, .col-3');

            if (actionColumn) {
                actionColumn.classList.remove('col-1', 'col-3');
                actionColumn.classList.add('col-12');
            }

            const filePondRoot = row.querySelector('.filepond--root');
            const currentPoster = filePondRoot?.querySelector(
                ':scope > [data-immobili-image-poster="true"]'
            );
            const value = previewInput.value.trim();
            let source = '';

            if (filePondRoot && filePondRoot.dataset.immobiliAspectRatio !== 'true') {
                const filePond = typeof window.FilePond?.find === 'function'
                    ? window.FilePond.find(filePondRoot)
                    : null;

                if (filePond && typeof filePond.setOptions === 'function') {
                    filePond.setOptions({
                        stylePanelAspectRatio: '4:3',
                        styleItemPanelAspectRatio: '4:3',
                    });
                    filePondRoot.dataset.immobiliAspectRatio = 'true';
                }
            }

            if (value !== '') {
                try {
                    const url = new URL(value, window.location.origin);

                    if (url.protocol === 'http:' || url.protocol === 'https:') {
                        source = url.href;
                    }
                } catch (error) {
                    source = '';
                }
            }

            if (!filePondRoot || source === '') {
                currentPoster?.remove();

                return;
            }

            const previewAlt = previewInput.name.startsWith('floor_plans[')
                ? floorPlanPreviewAlt
                : imagePreviewAlt;

            if (currentPoster) {
                const image = currentPoster.querySelector('img');

                if (image) {
                    image.src = source;
                    image.alt = previewAlt;
                }

                return;
            }

            const previewWrapper = document.createElement('div');
            const preview = document.createElement('div');
            const previewImage = document.createElement('img');

            previewWrapper.className = 'filepond--image-preview-wrapper';
            previewWrapper.setAttribute('data-immobili-image-poster', 'true');
            preview.className = 'filepond--image-preview';
            previewImage.className = 'w-100 h-100 object-fit-cover';
            previewImage.src = source;
            previewImage.alt = previewAlt;
            previewImage.loading = 'lazy';
            previewImage.decoding = 'async';

            preview.appendChild(previewImage);
            previewWrapper.appendChild(preview);
            filePondRoot.appendChild(previewWrapper);
        };

        const initializeMediaRows = () => {
            form.querySelectorAll(mediaPreviewSelector).forEach((previewInput) => {
                const row = previewInput.closest('.wi-repeater-row');

                if (row) {
                    decorateMediaRow(row);
                }
            });
        };
        const resetClearedMediaRow = (row) => {
            if (!row?.isConnected || !row.querySelector(mediaPreviewSelector)) {
                return;
            }

            const filePondRoot = row.querySelector('.filepond--root');
            const filePond = filePondRoot && typeof window.FilePond?.find === 'function'
                ? window.FilePond.find(filePondRoot)
                : null;

            if (filePond && typeof filePond.destroy === 'function') {
                filePond.destroy();
            }

            const restoredFileInput = row.querySelector('input[type="file"][data-wi-uploader]');

            if (restoredFileInput) {
                restoredFileInput.value = '';
                restoredFileInput.dataset.wiValue = '';
            }

            if (typeof window.setInput === 'function') {
                window.setInput(row);
            }

            decorateMediaRow(row);
            lockFeedFields();
        };

        const lockFeedFields = () => {
            if (!feedRecord) {
                return;
            }

            form.noValidate = true;
            form.querySelectorAll('input, select, textarea, button').forEach((field) => {
                if (field.name === 'stato' || field.name === 'upload' || field.type === 'submit') {
                    return;
                }

                field.required = false;
                field.disabled = true;
            });
        };

        lockFeedFields();

        let pendingRepeaterDeleteRow = null;

        const initializeRepeaterRows = () => {
            const confirmDelete = window.wiRepeaterConfirmDelete;

            if (typeof confirmDelete === 'function' && !confirmDelete.immobiliInitialized) {
                const wrappedConfirmDelete = function (onConfirm, config = {}) {
                    const affectedRow = pendingRepeaterDeleteRow;

                    return confirmDelete.call(this, () => {
                        onConfirm();
                        resetClearedMediaRow(affectedRow);
                        initializeMediaRows();
                    }, config);
                };

                wrappedConfirmDelete.immobiliInitialized = true;
                window.wiRepeaterConfirmDelete = wrappedConfirmDelete;
            }

            const removeRow = window.wiRepeaterRemoveRow;

            if (typeof removeRow === 'function' && !removeRow.immobiliInitialized) {
                const wrappedRemoveRow = function (button) {
                    pendingRepeaterDeleteRow = button?.closest('.wi-repeater-row') || null;

                    try {
                        return removeRow.call(this, button);
                    } finally {
                        pendingRepeaterDeleteRow = null;
                    }
                };

                wrappedRemoveRow.immobiliInitialized = true;
                window.wiRepeaterRemoveRow = wrappedRemoveRow;
            }

            const addRow = window.wiRepeaterAddRow;

            if (typeof addRow !== 'function' || addRow.immobiliInitialized) {
                return;
            }

            const wrappedAddRow = function (containerId, templateId) {
                const container = document.getElementById(containerId);
                const previousRows = container ? new Set(Array.from(container.children)) : new Set();

                addRow.call(this, containerId, templateId);

                if (!container) {
                    return;
                }

                const row = Array.from(container.children).find((element) => !previousRows.has(element));

                if (!row) {
                    return;
                }

                const suffix = String(row.dataset.wiRowKey || Date.now()).replace(/[^a-zA-Z0-9_-]/g, '_');
                const ids = new Map();

                row.querySelectorAll('[id]').forEach((element) => {
                    const oldId = element.id;
                    const newId = `${oldId}-${suffix}`;
                    ids.set(oldId, newId);
                    element.id = newId;
                });

                row.querySelectorAll('[for], [aria-describedby], [aria-controls]').forEach((element) => {
                    ['for', 'aria-describedby', 'aria-controls'].forEach((attribute) => {
                        const oldId = element.getAttribute(attribute);

                        if (oldId && ids.has(oldId)) {
                            element.setAttribute(attribute, ids.get(oldId));
                        }
                    });
                });

                if (typeof window.setInput === 'function' && !row.querySelector('.filepond--root')) {
                    window.setInput(row);
                }

                decorateMediaRow(row);
                lockFeedFields();
            };

            wrappedAddRow.immobiliInitialized = true;
            window.wiRepeaterAddRow = wrappedAddRow;
        };

        initializeRepeaterRows();
        initializeMediaRows();

        const bindFilter = (sourceName, targetName, filterName) => {
            const source = input(sourceName);
            const target = input(targetName);

            if (!source || !target || typeof filterSelect !== 'function') {
                return;
            }

            const update = () => {
                const previousValue = target.value;
                filterSelect(target, filterName, source.value);

                if (target.value !== previousValue) {
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                }
            };
            source.addEventListener('change', update);
            update();
        };

        const updateRooms = () => {
            const rooms = input('n_locali');

            if (!rooms) {
                return;
            }

            const total = Number(input('n_camere')?.value || 0)
                + Number(input('n_altre_camere')?.value || 0);
            const autoNumeric = window.AutoNumeric?.getAutoNumericElement(rooms);

            if (autoNumeric) {
                autoNumeric.set(total);
            } else {
                rooms.value = String(total);
            }
        };

        const updatePriceUnit = () => {
            const contract = input('contratto_id')?.value || '';
            const price = input('prezzo');
            const autoNumeric = price ? window.AutoNumeric?.getAutoNumericElement(price) : null;

            if (autoNumeric) {
                autoNumeric.update({ currencySymbol: contract === 'A' ? '€/Mese' : '€' });
            }
        };

        const updateHeatingFuel = () => {
            if (feedRecord) {
                return;
            }

            const heating = input('riscaldamento_id')?.value || '';
            const fuel = input('tipo_riscaldamento_id');

            if (!fuel) {
                return;
            }

            fuel.disabled = heating === '' || heating === '255';

            if (fuel.disabled) {
                fuel.value = '';
            }
        };

        bindFilter('categoria_id', 'macrotipologia_id', 'categoria');
        bindFilter('macrotipologia_id', 'tipologia_id', 'macrotipologia');
        bindFilter('comune_id', 'quartiere_id', 'comune');
        bindFilter('quartiere_id', 'quartiere_zona_id', 'quartiere');
        bindFilter('legge_classe_energetica_id', 'classe_energetica', 'legge');

        form.querySelectorAll('[data-calc-locali="true"]').forEach((field) => {
            field.addEventListener('input', updateRooms);
            field.addEventListener('change', updateRooms);
        });

        input('contratto_id')?.addEventListener('change', updatePriceUnit);
        input('riscaldamento_id')?.addEventListener('change', updateHeatingFuel);
        window.addEventListener('loaded', () => {
            updateRooms();
            updatePriceUnit();
            updateHeatingFuel();
            initializeMediaRows();
            lockFeedFields();
        });
    })();
</script>

<?php \Wonder\View\View::end(); ?>
