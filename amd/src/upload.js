// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Image upload widget for local_byblos portfolio pages.
 *
 * Provides drag-drop, file browse, and URL paste for uploading images
 * to portfolio pages. Uploads are sent directly to upload.php as
 * multipart form data.
 *
 * @module     local_byblos/upload
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';

/**
 * Create and render the upload widget inside a container element.
 *
 * @param {HTMLElement} container  The DOM element to render the widget into.
 * @param {number}      pageId    The portfolio page ID.
 * @param {string}      sesskey   The Moodle session key.
 * @param {string|null} currentUrl Current image URL for preview, or null.
 * @param {Function}    onUpload  Callback fired after successful upload: (url, filename) => void.
 */
export const createWidget = (container, pageId, sesskey, currentUrl, onUpload) => {
    // Build the widget markup.
    container.innerHTML = '';
    container.classList.add('byblos-upload-widget');

    // Preview area.
    const preview = document.createElement('div');
    preview.classList.add('byblos-upload-preview', 'mb-2');
    if (currentUrl) {
        const img = document.createElement('img');
        img.src = currentUrl;
        img.alt = 'Page image';
        img.classList.add('img-fluid', 'rounded');
        img.style.maxHeight = '200px';
        preview.appendChild(img);
    } else {
        const placeholder = document.createElement('div');
        placeholder.classList.add('byblos-upload-placeholder', 'text-center', 'text-muted', 'p-4');
        placeholder.innerHTML = '<i class="fa fa-image fa-3x"></i><br><small>No image</small>';
        preview.appendChild(placeholder);
    }
    container.appendChild(preview);

    // Drop zone.
    const dropZone = document.createElement('div');
    dropZone.classList.add(
        'byblos-dropzone', 'border', 'border-dashed', 'rounded',
        'text-center', 'p-3', 'mb-2'
    );
    dropZone.style.borderStyle = 'dashed';
    dropZone.style.cursor = 'pointer';
    dropZone.innerHTML = '<i class="fa fa-cloud-upload"></i> Drag &amp; drop image here, or click to browse';
    container.appendChild(dropZone);

    // Hidden file input.
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    fileInput.style.display = 'none';
    container.appendChild(fileInput);

    // Upload button + "From library" button grouped together.
    const btnRow = document.createElement('div');
    btnRow.classList.add('byblos-upload-btn-row', 'mb-2');

    const uploadBtn = document.createElement('button');
    uploadBtn.type = 'button';
    uploadBtn.classList.add('btn', 'btn-sm', 'btn-outline-primary', 'mr-1');
    uploadBtn.textContent = 'Upload image';
    btnRow.appendChild(uploadBtn);

    const libraryBtn = document.createElement('button');
    libraryBtn.type = 'button';
    libraryBtn.classList.add('btn', 'btn-sm', 'btn-outline-secondary');
    libraryBtn.innerHTML = '<i class="fa fa-folder-open-o"></i> From library';
    btnRow.appendChild(libraryBtn);

    container.appendChild(btnRow);

    // URL paste input.
    const urlRow = document.createElement('div');
    urlRow.classList.add('input-group', 'input-group-sm', 'mb-2');
    const urlInput = document.createElement('input');
    urlInput.type = 'url';
    urlInput.classList.add('form-control');
    urlInput.placeholder = 'Or paste image URL';
    urlRow.appendChild(urlInput);
    const urlBtn = document.createElement('button');
    urlBtn.type = 'button';
    urlBtn.classList.add('btn', 'btn-outline-secondary');
    urlBtn.textContent = 'Use URL';
    urlRow.appendChild(urlBtn);
    container.appendChild(urlRow);

    // Spinner.
    const spinner = document.createElement('div');
    spinner.classList.add('byblos-upload-spinner', 'text-center', 'd-none', 'my-2');
    spinner.innerHTML = '<div class="spinner-border spinner-border-sm" role="status">' +
        '<span class="sr-only">Uploading...</span></div>';
    container.appendChild(spinner);

    // Error area.
    const errorBox = document.createElement('div');
    errorBox.classList.add('byblos-upload-error', 'alert', 'alert-danger', 'd-none', 'py-1', 'px-2', 'small');
    container.appendChild(errorBox);

    /**
     * Show an error message in the widget.
     *
     * @param {string} msg The error text to display.
     */
    const showError = (msg) => {
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
    };

    /**
     * Clear the error display.
     */
    const clearError = () => {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    };

    /**
     * Set the loading (spinner) state.
     *
     * @param {boolean} loading True to show spinner, false to hide.
     */
    const setLoading = (loading) => {
        spinner.classList.toggle('d-none', !loading);
        uploadBtn.disabled = loading;
        dropZone.style.pointerEvents = loading ? 'none' : '';
    };

    /**
     * Update the preview thumbnail after a successful upload.
     *
     * @param {string} url The image URL to display.
     */
    const updatePreview = (url) => {
        preview.innerHTML = '';
        const img = document.createElement('img');
        img.src = url;
        img.alt = 'Page image';
        img.classList.add('img-fluid', 'rounded');
        img.style.maxHeight = '200px';
        preview.appendChild(img);
    };

    /**
     * Upload a File object to the server.
     *
     * @param {File} file The file to upload.
     * @returns {Promise<void>}
     */
    const uploadFile = async(file) => {
        clearError();

        // Client-side MIME check.
        if (!file.type.startsWith('image/')) {
            showError('Only image files are allowed.');
            return;
        }

        // Client-side size check (10 MB).
        const maxBytes = 10 * 1024 * 1024;
        if (file.size > maxBytes) {
            showError('File exceeds 10 MB limit.');
            return;
        }

        setLoading(true);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('pageid', pageId);
        formData.append('sesskey', sesskey);

        try {
            const response = await fetch(M.cfg.wwwroot + '/local/byblos/upload.php', {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (!response.ok || data.error) {
                showError(data.error || 'Upload failed.');
                return;
            }

            updatePreview(data.url);
            if (typeof onUpload === 'function') {
                onUpload(data.url, data.filename);
            }
        } catch (err) {
            Notification.exception(err);
            showError('Upload failed. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    // --- Event listeners ---

    // Click drop zone or button → open file dialog.
    dropZone.addEventListener('click', () => fileInput.click());
    uploadBtn.addEventListener('click', () => fileInput.click());

    // File selected via dialog.
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            uploadFile(fileInput.files[0]);
            fileInput.value = '';
        }
    });

    // Drag-and-drop.
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.add('bg-light');
    });

    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('bg-light');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('bg-light');

        if (e.dataTransfer.files.length > 0) {
            uploadFile(e.dataTransfer.files[0]);
        }
    });

    /**
     * "From library" button — lazy-loads the artefact picker and
     * inserts the chosen image as though it had just been uploaded.
     */
    libraryBtn.addEventListener('click', () => {
        clearError();
        require(['local_byblos/artefact_picker'], (Picker) => {
            Picker.open({
                typefilter: 'image',
                title: 'Select image from library',
                /**
                 * Handle the artefact selection from the picker.
                 * @param {{id: number, title: string, url: string, artefacttype: string, thumburl: string}} artefact
                 */
                onPick: (artefact) => {
                    updatePreview(artefact.url);
                    if (typeof onUpload === 'function') {
                        onUpload(artefact.url, artefact.title || '');
                    }
                }
            });
        });
    });

    // URL paste button.
    urlBtn.addEventListener('click', () => {
        clearError();
        const url = urlInput.value.trim();
        if (!url) {
            showError('Please enter a URL.');
            return;
        }
        // For URL-based images, pass directly to callback without uploading.
        updatePreview(url);
        if (typeof onUpload === 'function') {
            onUpload(url, '');
        }
        urlInput.value = '';
    });
};
