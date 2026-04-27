/**
 * Asset Manager
 * Bild-Upload und Auswahl-Modal
 */
(function () {
    'use strict';

    const CONFIG = window.TEMPLATE_EDITOR_CONFIG;

    class AssetManager {
        constructor() {
            this.mode = 'image'; // 'image' oder 'background'
            this.modal = null;
            this.init();
        }

        init() {
            const modalEl = document.getElementById('assetManagerModal');
            if (!modalEl) return;

            this.modal = new bootstrap.Modal(modalEl);

            // Upload Handler
            document.getElementById('asset-upload-input')?.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.uploadFile(e.target.files[0]);
                }
            });
        }

        open(mode) {
            this.mode = mode || 'image';
            this.loadGallery();
            this.modal?.show();
        }

        async loadGallery() {
            const gallery = document.getElementById('asset-gallery');
            if (!gallery) return;

            gallery.innerHTML = '<div class="col-12 text-center py-3"><i class="fa-solid fa-spinner fa-spin"></i> Laden...</div>';

            try {
                const response = await fetch(CONFIG.basePath + 'api/documents/asset-list?template_id=' + CONFIG.templateId);
                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.error);
                }

                const assets = result.assets || [];
                if (assets.length === 0) {
                    gallery.innerHTML = '<div class="col-12 text-muted text-center py-3">Keine Bilder vorhanden</div>';
                    return;
                }

                let html = '';
                assets.forEach(asset => {
                    html += '<div class="col-4 col-md-3">';
                    html += '<div class="card h-100" style="cursor:pointer;" data-asset-id="' + asset.id + '" data-asset-url="' + this.escapeAttr(asset.url) + '">';
                    html += '<div class="card-body p-2 text-center">';
                    html += '<img src="' + this.escapeAttr(CONFIG.basePath + asset.url.replace(/^\//, '')) + '" class="img-fluid mb-1" style="max-height:80px;object-fit:contain;" alt="">';
                    html += '<div class="text-truncate" style="font-size:0.7rem;">' + this.escapeHtml(asset.original_name) + '</div>';
                    if (asset.width_px && asset.height_px) {
                        html += '<div style="font-size:0.65rem;color:var(--bs-secondary-color);">' + asset.width_px + 'x' + asset.height_px + '</div>';
                    }
                    html += '</div>';
                    html += '<div class="card-footer p-1 text-center">';
                    html += '<button class="btn btn-sm btn-outline-danger btn-delete-asset" data-id="' + asset.id + '" title="Löschen"><i class="fa-solid fa-trash" style="font-size:0.7rem;"></i></button>';
                    html += '</div>';
                    html += '</div></div>';
                });

                gallery.innerHTML = html;

                // Klick auf Asset-Karte = auswählen
                gallery.querySelectorAll('.card[data-asset-id]').forEach(card => {
                    card.addEventListener('click', (e) => {
                        // Ignoriere Klick auf Lösch-Button
                        if (e.target.closest('.btn-delete-asset')) return;
                        this.selectAsset(parseInt(card.dataset.assetId), card.dataset.assetUrl);
                    });
                });

                // Lösch-Buttons
                gallery.querySelectorAll('.btn-delete-asset').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        const ok = await showConfirm('Bild wirklich löschen?', { title: 'Bild löschen', danger: true, confirmText: 'Löschen' });
                        if (ok) {
                            await this.deleteAsset(parseInt(btn.dataset.id));
                            this.loadGallery();
                        }
                    });
                });
            } catch (err) {
                console.error('Gallery load error:', err);
                gallery.innerHTML = '<div class="col-12 text-danger text-center py-3">Fehler beim Laden</div>';
            }
        }

        selectAsset(assetId, assetUrl) {
            const editor = window.TemplateEditor;
            if (!editor) return;

            const fullUrl = CONFIG.basePath + assetUrl.replace(/^\//, '');

            if (this.mode === 'background') {
                editor.setBackgroundImage(fullUrl, assetId);
            } else {
                editor.addImage(fullUrl, assetId);
            }

            this.modal?.hide();
        }

        async uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('template_id', CONFIG.templateId);
            formData.append('asset_type', this.mode === 'background' ? 'background' : 'image');
            window.EditorCsrf.addToFormData(formData);

            try {
                const response = await fetch(CONFIG.basePath + 'api/documents/asset-upload', {
                    method: 'POST',
                    body: formData,
                });

                const result = await response.json();
                window.EditorCsrf.handleResponse(result);

                if (!result.success) {
                    throw new Error(result.error);
                }

                if (window.showToast) {
                    window.showToast('Bild hochgeladen', 'success');
                }

                // Lade Galerie neu
                this.loadGallery();

                // Reset Input
                const input = document.getElementById('asset-upload-input');
                if (input) input.value = '';

                // Direkt verwenden
                this.selectAsset(result.asset.id, result.asset.url);
            } catch (err) {
                console.error('Upload error:', err);
                if (window.showToast) {
                    window.showToast('Upload fehlgeschlagen: ' + err.message, 'danger');
                }
            }
        }

        async deleteAsset(assetId) {
            try {
                const response = await fetch(CONFIG.basePath + 'api/documents/asset-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(window.EditorCsrf.addToBody({ id: assetId })),
                });

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error);
                }
            } catch (err) {
                console.error('Delete error:', err);
                if (window.showToast) {
                    window.showToast('Löschen fehlgeschlagen: ' + err.message, 'danger');
                }
            }
        }

        escapeHtml(str) { return window.EditorUtils.escapeHtml(str); }
        escapeAttr(str) { return window.EditorUtils.escapeAttr(str); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.AssetManager = new AssetManager();
    });
})();
