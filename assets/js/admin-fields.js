/**
 * Stagehand admin fields — vanilla JS, no jQuery required.
 *
 * Responsibilities:
 *  - Add row / remove row in the visual UI
 *  - HTML5 drag-and-drop reordering
 *  - Toggle between visual ↔ shorthand mode
 *  - Sync visual ↔ shorthand on toggle so the user never loses data
 *  - Detect a paste of pipe-shorthand into a single visual input and auto-explode
 *    it into rows (the headline UX win the README pitches).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.stagehand-field').forEach(initField);
        document.querySelectorAll('.stagehand-scalar[data-stagehand-type="image"]').forEach(initImagePicker);
        document.querySelectorAll('.stagehand-scalar[data-stagehand-type="color"]').forEach(initColorReadout);
    });

    function initImagePicker(field) {
        if (typeof wp === 'undefined' || !wp.media) return;
        const idInput = field.querySelector('[data-stagehand-image-id]');
        const preview = field.querySelector('[data-stagehand-image-preview]');
        const pick    = field.querySelector('[data-stagehand-image-pick]');
        const clear   = field.querySelector('[data-stagehand-image-clear]');
        if (!idInput || !pick) return;

        let frame = null;
        pick.addEventListener('click', function (e) {
            e.preventDefault();
            if (frame === null) {
                frame = wp.media({
                    title: (window.StagehandI18n && window.StagehandI18n.chooseImage) || 'Choose image',
                    button: { text: (window.StagehandI18n && window.StagehandI18n.useThisImage) || 'Use this image' },
                    multiple: false,
                    library: { type: 'image' },
                });
                frame.on('select', function () {
                    const att = frame.state().get('selection').first().toJSON();
                    idInput.value = att.id;
                    if (preview) {
                        const url = (att.sizes && att.sizes.medium && att.sizes.medium.url) || att.url;
                        preview.innerHTML = '';
                        const img = document.createElement('img');
                        img.src = url;
                        img.alt = att.alt || '';
                        preview.appendChild(img);
                    }
                });
            }
            frame.open();
        });

        if (clear) {
            clear.addEventListener('click', function (e) {
                e.preventDefault();
                idInput.value = '';
                if (preview) preview.innerHTML = '';
            });
        }
    }

    function initColorReadout(field) {
        const input    = field.querySelector('input[type="color"]');
        const readout  = field.querySelector('.stagehand-color-readout');
        if (!input || !readout) return;
        input.addEventListener('input', function () {
            readout.textContent = input.value;
        });
    }

    function initField(field) {
        const visual = field.querySelector('[data-stagehand-visual]');
        const shorthand = field.querySelector('[data-stagehand-shorthand]');
        const mode = field.dataset.displayMode || 'both';

        if (mode === 'both') {
            const initial = visual ? 'visual' : 'shorthand';
            setMode(field, initial);
            field.querySelectorAll('.stagehand-mode').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const next = btn.dataset.mode;
                    syncBeforeMode(field, next);
                    setMode(field, next);
                });
            });
        }

        if (visual) {
            visual.querySelector('[data-stagehand-add]').addEventListener('click', function () {
                addRow(field, visual);
            });
            wireRowEvents(field, visual);
            wirePasteShortcut(field, visual);
        }
    }

    function setMode(field, mode) {
        field.dataset.displayMode = mode;
        field.querySelectorAll('.stagehand-mode').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.mode === mode);
        });
    }

    function syncBeforeMode(field, nextMode) {
        const visual = field.querySelector('[data-stagehand-visual]');
        const shorthand = field.querySelector('[data-stagehand-shorthand] textarea');
        if (!visual || !shorthand) return;

        if (nextMode === 'shorthand') {
            // Visual → shorthand: serialize each row.
            const rows = visual.querySelectorAll('[data-stagehand-row]');
            const lines = [];
            rows.forEach(function (row) {
                const cells = [];
                row.querySelectorAll('input, textarea').forEach(function (input) {
                    let v = input.value || '';
                    v = v.replace(/\\/g, '\\\\').replace(/\|/g, '\\|').replace(/\n/g, '\\n');
                    cells.push(v);
                });
                while (cells.length && cells[cells.length - 1] === '') cells.pop();
                if (cells.length) lines.push(cells.join(' | '));
            });
            shorthand.value = lines.join('\n');
        } else {
            // Shorthand → visual: explode lines into rows, replace existing rows.
            const text = shorthand.value || '';
            const rows = text.split(/\r?\n/).filter(function (l) { return l.trim() !== ''; });
            const container = visual.querySelector('[data-stagehand-rows]');
            container.innerHTML = '';
            if (rows.length === 0) {
                addRow(field, visual);
            } else {
                rows.forEach(function (line) {
                    const cells = splitLine(line);
                    const newRow = makeRow(field);
                    const inputs = newRow.querySelectorAll('input, textarea');
                    inputs.forEach(function (input, i) {
                        input.value = cells[i] !== undefined ? cells[i] : '';
                    });
                    container.appendChild(newRow);
                });
                wireRowEvents(field, visual);
            }
        }
    }

    function splitLine(line) {
        const cells = [];
        let buf = '';
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (ch === '\\' && i + 1 < line.length) {
                const nx = line[i + 1];
                if (nx === '|') { buf += '|'; i++; continue; }
                if (nx === 'n') { buf += '\n'; i++; continue; }
                if (nx === '\\') { buf += '\\'; i++; continue; }
            }
            if (ch === '|' && line[i - 1] === ' ' && line[i + 1] === ' ') {
                cells.push(buf.replace(/\s+$/, ''));
                buf = '';
                i++;
                continue;
            }
            buf += ch;
        }
        cells.push(buf.replace(/^\s+|\s+$/g, ''));
        return cells;
    }

    function addRow(field, visual) {
        const tpl = visual.querySelector('[data-stagehand-row-template]');
        const container = visual.querySelector('[data-stagehand-rows]');
        const newRow = makeRow(field);
        container.appendChild(newRow);
        wireRowEvents(field, visual);
        const first = newRow.querySelector('input, textarea');
        if (first) first.focus();
    }

    function makeRow(field) {
        const visual = field.querySelector('[data-stagehand-visual]');
        const tpl = visual.querySelector('[data-stagehand-row-template]');
        const container = visual.querySelector('[data-stagehand-rows]');
        const index = container.querySelectorAll('[data-stagehand-row]').length;
        const html = tpl.innerHTML.replace(/__index__/g, String(index));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        return wrapper.firstChild;
    }

    function wireRowEvents(field, visual) {
        visual.querySelectorAll('[data-stagehand-row]').forEach(function (row) {
            if (row.dataset.shWired === '1') return;
            row.dataset.shWired = '1';

            const remove = row.querySelector('[data-stagehand-remove]');
            if (remove) {
                remove.addEventListener('click', function () {
                    row.parentNode.removeChild(row);
                    reindex(visual);
                });
            }

            row.setAttribute('draggable', 'true');
            row.addEventListener('dragstart', function (e) {
                row.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
            });
            row.addEventListener('dragend', function () {
                row.classList.remove('is-dragging');
                reindex(visual);
            });
            row.addEventListener('dragover', function (e) {
                e.preventDefault();
                const dragging = visual.querySelector('.is-dragging');
                if (!dragging || dragging === row) return;
                const rect = row.getBoundingClientRect();
                const after = (e.clientY - rect.top) > rect.height / 2;
                row.parentNode.insertBefore(dragging, after ? row.nextSibling : row);
            });
        });
    }

    function reindex(visual) {
        const rows = visual.querySelectorAll('[data-stagehand-row]');
        rows.forEach(function (row, idx) {
            row.querySelectorAll('input, textarea').forEach(function (input) {
                input.name = input.name.replace(/\[(\d+|__index__)\]/, '[' + idx + ']');
            });
        });
    }

    function wirePasteShortcut(field, visual) {
        // If the user pastes a multi-line pipe-shorthand into ANY input within
        // the visual UI, auto-explode it into proper rows. Catches the common
        // workflow of pasting a Notion table into the first cell.
        visual.addEventListener('paste', function (e) {
            const target = e.target;
            if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement)) return;
            const text = (e.clipboardData || window.clipboardData).getData('text');
            if (!text || text.indexOf('\n') === -1 || text.indexOf(' | ') === -1) return;
            e.preventDefault();
            const shorthand = field.querySelector('[data-stagehand-shorthand] textarea');
            if (shorthand) {
                shorthand.value = text;
                syncBeforeMode(field, 'visual');
            }
        });
    }
})();
