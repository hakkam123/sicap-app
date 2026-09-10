import { ref } from 'vue';
import { useToast } from './useToast';

export function useExportWithToast() {
    const isExporting = ref(false);
    const toast = useToast();

    /**
     * Download a file via URL with real-time progress toast feedback.
     *
     * @param {Object} options
     * @param {string} options.url - The endpoint URL
     * @param {string} [options.filename] - The expected filename to save as
     * @param {string} [options.title] - The toast title
     * @param {string} [options.loadingMessage] - Message during processing
     * @param {string} [options.successMessage] - Message upon successful download
     */
    const download = async ({
        url,
        filename,
        title = 'Ekspor File',
        loadingMessage = 'Memproses ekspor file...',
        successMessage = 'File ekspor berhasil diunduh.',
    }) => {
        if (isExporting.value) return;

        isExporting.value = true;
        const toastId = toast.progress(loadingMessage, {
            title,
            indeterminate: true,
        });

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
            });

            if (!response.ok) {
                let errorMsg = 'Gagal memproses file ekspor dari server.';
                try {
                    const errorJson = await response.json();
                    if (errorJson.message) errorMsg = errorJson.message;
                } catch (_) {}
                throw new Error(errorMsg);
            }

            // Extract filename from header if not provided
            let finalFilename = filename;
            if (!finalFilename) {
                const disposition = response.headers.get('content-disposition');
                if (disposition && disposition.indexOf('filename=') !== -1) {
                    const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                    if (matches != null && matches[1]) {
                        finalFilename = matches[1].replace(/['"]/g, '');
                    }
                }
            }
            if (!finalFilename) {
                finalFilename = 'export_' + new Date().toISOString().slice(0, 10) + '.xlsx';
            }

            const blob = await response.blob();
            const blobUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = blobUrl;
            a.download = finalFilename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(blobUrl);

            toast.finish(toastId, {
                status: 'success',
                title: 'Ekspor Selesai',
                message: successMessage,
            });
        } catch (err) {
            toast.finish(toastId, {
                status: 'error',
                title: 'Ekspor Gagal',
                message: err.message || 'Terjadi kesalahan saat mengunduh file ekspor.',
            });
        } finally {
            isExporting.value = false;
        }
    };

    return {
        isExporting,
        download,
    };
}
