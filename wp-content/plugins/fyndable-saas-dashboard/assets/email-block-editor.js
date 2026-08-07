/**
 * Email Block Editor
 *
 * Limited block editor for email templates (Optie B).
 * Blocks: heading, text, button, image, spacer, divider.
 * Users add blocks from a palette and reorder them with up/down buttons.
 * The block list is serialised to JSON in a hidden input (#body_blocks_json)
 * which is submitted alongside the regular form fields.
 */
(function () {
    'use strict';

    var SSEO = window.SSEOEmailBlockEditor || {};
    window.SSEOEmailBlockEditor = SSEO;

    SSEO.blocks = [];
    SSEO.container = null;
    SSEO.listEl = null;
    SSEO.hiddenInput = null;
    SSEO.i18n = SSEO.i18n || {};

    var blockLabels = {
        heading: SSEO.i18n.heading || 'Heading',
        text: SSEO.i18n.text || 'Text',
        button: SSEO.i18n.button || 'Button',
        image: SSEO.i18n.image || 'Image',
        spacer: SSEO.i18n.spacer || 'Spacer',
        divider: SSEO.i18n.divider || 'Divider'
    };

    var defaults = {
        heading: { type: 'heading', text: '', level: 'h2', align: 'left' },
        text: { type: 'text', text: '', align: 'left' },
        button: { type: 'button', text: '', url: '', align: 'center', color: '#379fd3' },
        image: { type: 'image', src: '', alt: '', width: '100%', align: 'center' },
        spacer: { type: 'spacer', height: 24 },
        divider: { type: 'divider', color: '#e5e7eb' }
    };

    function init() {
        SSEO.container = document.getElementById('sseo-email-block-editor');
        if (!SSEO.container) {
            return;
        }
        SSEO.listEl = document.getElementById('sseo-email-block-list');
        SSEO.hiddenInput = document.getElementById('body_blocks_json');

        // Load existing blocks from the hidden input.
        try {
            SSEO.blocks = JSON.parse(SSEO.hiddenInput.value || '[]');
        } catch (e) {
            SSEO.blocks = [];
        }

        bindPalette();
        render();
    }

    function bindPalette() {
        var buttons = SSEO.container.querySelectorAll('.sseo-block-palette-btn');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].addEventListener('click', function () {
                var type = this.getAttribute('data-block-type');
                addBlock(type);
            });
        }
    }

    function addBlock(type) {
        var block = JSON.parse(JSON.stringify(defaults[type] || { type: type }));
        SSEO.blocks.push(block);
        render();
    }

    function removeBlock(index) {
        SSEO.blocks.splice(index, 1);
        render();
    }

    function moveBlock(index, direction) {
        var newIndex = index + direction;
        if (newIndex < 0 || newIndex >= SSEO.blocks.length) {
            return;
        }
        var tmp = SSEO.blocks[index];
        SSEO.blocks[index] = SSEO.blocks[newIndex];
        SSEO.blocks[newIndex] = tmp;
        render();
    }

    function updateBlock(index, key, value) {
        SSEO.blocks[index][key] = value;
        serialize();
    }

    function serialize() {
        SSEO.hiddenInput.value = JSON.stringify(SSEO.blocks);
    }

    function render() {
        SSEO.listEl.innerHTML = '';
        serialize();

        if (SSEO.blocks.length === 0) {
            SSEO.listEl.innerHTML = '<p class="sseo-block-empty">' + (SSEO.i18n.empty || 'No blocks yet. Add one from the palette above.') + '</p>';
            return;
        }

        for (var i = 0; i < SSEO.blocks.length; i++) {
            SSEO.listEl.appendChild(renderBlockItem(SSEO.blocks[i], i));
        }
    }

    function renderBlockItem(block, index) {
        var item = document.createElement('div');
        item.className = 'sseo-block-item';

        // Header row: label + controls.
        var header = document.createElement('div');
        header.className = 'sseo-block-item-header';

        var label = document.createElement('span');
        label.className = 'sseo-block-item-label';
        label.textContent = blockLabels[block.type] || block.type;
        header.appendChild(label);

        var controls = document.createElement('div');
        controls.className = 'sseo-block-item-controls';

        var upBtn = document.createElement('button');
        upBtn.type = 'button';
        upBtn.className = 'button button-small';
        upBtn.textContent = '↑';
        upBtn.title = SSEO.i18n.moveUp || 'Move up';
        upBtn.addEventListener('click', moveBlock.bind(null, index, -1));
        controls.appendChild(upBtn);

        var downBtn = document.createElement('button');
        downBtn.type = 'button';
        downBtn.className = 'button button-small';
        downBtn.textContent = '↓';
        downBtn.title = SSEO.i18n.moveDown || 'Move down';
        downBtn.addEventListener('click', moveBlock.bind(null, index, 1));
        controls.appendChild(downBtn);

        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'button button-small button-link-delete';
        delBtn.textContent = '✕';
        delBtn.title = SSEO.i18n.remove || 'Remove block';
        delBtn.addEventListener('click', removeBlock.bind(null, index));
        controls.appendChild(delBtn);

        header.appendChild(controls);
        item.appendChild(header);

        // Body: type-specific fields.
        var body = document.createElement('div');
        body.className = 'sseo-block-item-body';
        body.appendChild(renderBlockFields(block, index));
        item.appendChild(body);

        return item;
    }

    function renderBlockFields(block, index) {
        var frag = document.createDocumentFragment();

        switch (block.type) {
            case 'heading':
                frag.appendChild(textField(block, index, 'text', SSEO.i18n.text || 'Text'));
                frag.appendChild(selectField(block, index, 'level', SSEO.i18n.level || 'Level', [
                    { value: 'h1', label: 'H1' },
                    { value: 'h2', label: 'H2' },
                    { value: 'h3', label: 'H3' }
                ]));
                frag.appendChild(selectField(block, index, 'align', SSEO.i18n.align || 'Alignment', [
                    { value: 'left', label: SSEO.i18n.left || 'Left' },
                    { value: 'center', label: SSEO.i18n.center || 'Center' },
                    { value: 'right', label: SSEO.i18n.right || 'Right' }
                ]));
                break;
            case 'text':
                frag.appendChild(textareaField(block, index, 'text', SSEO.i18n.text || 'Text'));
                frag.appendChild(selectField(block, index, 'align', SSEO.i18n.align || 'Alignment', [
                    { value: 'left', label: SSEO.i18n.left || 'Left' },
                    { value: 'center', label: SSEO.i18n.center || 'Center' },
                    { value: 'right', label: SSEO.i18n.right || 'Right' }
                ]));
                break;
            case 'button':
                frag.appendChild(textField(block, index, 'text', SSEO.i18n.buttonText || 'Button text'));
                frag.appendChild(textField(block, index, 'url', SSEO.i18n.url || 'URL'));
                frag.appendChild(colorField(block, index, 'color', SSEO.i18n.color || 'Color'));
                frag.appendChild(selectField(block, index, 'align', SSEO.i18n.align || 'Alignment', [
                    { value: 'left', label: SSEO.i18n.left || 'Left' },
                    { value: 'center', label: SSEO.i18n.center || 'Center' },
                    { value: 'right', label: SSEO.i18n.right || 'Right' }
                ]));
                break;
            case 'image':
                frag.appendChild(textField(block, index, 'src', SSEO.i18n.imageUrl || 'Image URL'));
                frag.appendChild(textField(block, index, 'alt', SSEO.i18n.altText || 'Alt text'));
                frag.appendChild(textField(block, index, 'width', SSEO.i18n.width || 'Width'));
                frag.appendChild(selectField(block, index, 'align', SSEO.i18n.align || 'Alignment', [
                    { value: 'left', label: SSEO.i18n.left || 'Left' },
                    { value: 'center', label: SSEO.i18n.center || 'Center' },
                    { value: 'right', label: SSEO.i18n.right || 'Right' }
                ]));
                break;
            case 'spacer':
                frag.appendChild(numberField(block, index, 'height', SSEO.i18n.height || 'Height (px)', 8, 80));
                break;
            case 'divider':
                frag.appendChild(colorField(block, index, 'color', SSEO.i18n.color || 'Color'));
                break;
        }

        return frag;
    }

    // ─── Field helpers ──────────────────────────────────────────────

    function fieldWrapper(labelText) {
        var wrap = document.createElement('label');
        wrap.className = 'sseo-block-field';
        var lbl = document.createElement('span');
        lbl.className = 'sseo-block-field-label';
        lbl.textContent = labelText;
        wrap.appendChild(lbl);
        return wrap;
    }

    function textField(block, index, key, labelText) {
        var wrap = fieldWrapper(labelText);
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'regular-text';
        input.value = block[key] || '';
        input.addEventListener('input', function () {
            updateBlock(index, key, this.value);
        });
        wrap.appendChild(input);
        return wrap;
    }

    function textareaField(block, index, key, labelText) {
        var wrap = fieldWrapper(labelText);
        var ta = document.createElement('textarea');
        ta.rows = 3;
        ta.className = 'large-text';
        ta.value = block[key] || '';
        ta.addEventListener('input', function () {
            updateBlock(index, key, this.value);
        });
        wrap.appendChild(ta);
        return wrap;
    }

    function numberField(block, index, key, labelText, min, max) {
        var wrap = fieldWrapper(labelText);
        var input = document.createElement('input');
        input.type = 'number';
        input.className = 'small-text';
        input.min = min;
        input.max = max;
        input.value = block[key] || '';
        input.addEventListener('input', function () {
            updateBlock(index, key, parseInt(this.value, 10) || min);
        });
        wrap.appendChild(input);
        return wrap;
    }

    function selectField(block, index, key, labelText, options) {
        var wrap = fieldWrapper(labelText);
        var sel = document.createElement('select');
        for (var i = 0; i < options.length; i++) {
            var opt = document.createElement('option');
            opt.value = options[i].value;
            opt.textContent = options[i].label;
            if (block[key] === options[i].value) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        }
        sel.addEventListener('change', function () {
            updateBlock(index, key, this.value);
        });
        wrap.appendChild(sel);
        return wrap;
    }

    function colorField(block, index, key, labelText) {
        var wrap = fieldWrapper(labelText);
        var input = document.createElement('input');
        input.type = 'color';
        input.value = block[key] || '#379fd3';
        input.addEventListener('input', function () {
            updateBlock(index, key, this.value);
        });
        wrap.appendChild(input);
        return wrap;
    }

    // Init on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
