import { ref } from 'vue';
import { useToast } from './useToast';

/**
 * Get cookie value by name.
 * Specifically reads the encrypted 'XSRF-TOKEN' cookie created by Laravel session.
 */
function getCookie(name) {
    if (typeof document === 'undefined' || !document.cookie) return '';
    const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
    return match ? decodeURIComponent(match[3]) : '';
}

export function useImportPolling() {
    const isUploading = ref(false);
    const importErrors = ref([]);
    const progressPercent = ref(0);
    const progressMessage = ref('');
    const toast = useToast();
    let pollTimer = null;

    const stopPolling = () => {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    /**
     * Start the import process with progress toast and live status polling.
     *
     * @param {Object} options
     * @param {string} options.url - The endpoint to POST the file to
     * @param {File} options.file - The uploaded file object
     * @param {string} [options.title] - Toast title
     * @param {Function} [options.onSuccess] - Callback when import successfully finishes
     * @param {Function} [options.onError] - Callback when import fails
     */
    const startImport = async ({
        url,
        file,
        title = 'Import Data Excel',
        onSuccess,
        onError,
    }) => {
        if (!file || isUploading.value) return;

        isUploading.value = true;
        importErrors.value = [];
        progressPercent.value = 0;
        progressMessage.value = 'Mengunggah file ke server...';

        const toastId = toast.progress('Mengunggah file ke server...', {
            title,
            indeterminate: true,
        });

        const formData = new FormData();
        formData.append('file', file);

        // In Laravel:
        // 1. The XSRF-TOKEN cookie contains an ENCRYPTED session token.
        //    It MUST be passed via 'X-XSRF-TOKEN' header (Laravel automatically decrypts it).
        // 2. The meta tag contains the UNENCRYPTED csrf_token().
        //    It MUST be passed via 'X-CSRF-TOKEN' header.
        const xsrfCookie = getCookie('XSRF-TOKEN');
        const metaCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        };

        if (xsrfCookie) {
            headers['X-XSRF-TOKEN'] = xsrfCookie;
        } else if (metaCsrfToken) {
            headers['X-CSRF-TOKEN'] = metaCsrfToken;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers,
            });

            let result = {};
            try {
                result = await response.json();
            } catch (_) {
                result = { message: 'Format response server tidak valid.' };
            }

            // Case 1: Result has import_log_id and is processing asynchronously
            if (result.import_log_id && (result.status === 'processing' || result.status === 'pending')) {
                const logId = result.import_log_id;
                progressMessage.value = 'Memproses data di background...';
                toast.update(toastId, {
                    message: 'Memproses data di background...',
                    indeterminate: true,
                });

                // Start polling status every 1200ms
                pollTimer = setInterval(async () => {
                    try {
                        const statusRes = await fetch(`/imports/${logId}/status`, {
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        });
                        const logData = await statusRes.json();

                        progressPercent.value = logData.progress_percentage || 0;
                        progressMessage.value = `Memproses: ${logData.processed_rows} / ${logData.total_rows} baris (${logData.progress_percentage}%)`;

                        toast.update(toastId, {
                            progress: logData.progress_percentage,
                            message: progressMessage.value,
                            indeterminate: false,
                        });

                        if (logData.status === 'success') {
                            stopPolling();
                            isUploading.value = false;
                            toast.finish(toastId, {
                                status: 'success',
                                title: 'Import Berhasil',
                                message: `Import selesai: ${logData.success_rows} data berhasil disimpan.`,
                            });
                            if (onSuccess) onSuccess(logData);
                        } else if (logData.status === 'failed') {
                            stopPolling();
                            isUploading.value = false;
                            importErrors.value = logData.error_details || logData.errors || [logData.error_message || 'Terjadi kesalahan validasi data.'];
                            toast.finish(toastId, {
                                status: 'error',
                                title: 'Import Gagal',
                                message: logData.error_message || `Terdapat ${logData.failed_rows} baris bermasalah.`,
                            });
                            if (onError) onError(logData);
                        }
                    } catch (pollErr) {
                        // Keep polling or wait
                    }
                }, 1200);

                return;
            }

            // Case 2: Result returned synchronously
            if (result.success || result.status === 'success') {
                isUploading.value = false;
                toast.finish(toastId, {
                    status: 'success',
                    title: 'Import Berhasil',
                    message: result.message || `Import data selesai: ${result.success_count || 0} baris berhasil disimpan.`,
                });
                if (onSuccess) onSuccess(result);
            } else {
                isUploading.value = false;
                const errorsList = result.error_details || result.errors || [result.message || 'Import data gagal. Periksa format data file.'];
                importErrors.value = errorsList;
                toast.finish(toastId, {
                    status: 'error',
                    title: 'Import Gagal',
                    message: result.message || 'Terdapat kesalahan pada data import.',
                });
                if (onError) onError(result);
            }
        } catch (err) {
            isUploading.value = false;
            importErrors.value = [{
                row: '-',
                field: 'Network',
                value: '-',
                message: err.message || 'Terjadi kesalahan koneksi saat mengunggah file.',
            }];
            toast.finish(toastId, {
                status: 'error',
                title: 'Import Gagal',
                message: err.message || 'Terjadi kesalahan jaringan saat mengunggah file.',
            });
            if (onError) onError({ message: err.message });
        }
    };

    return {
        isUploading,
        importErrors,
        progressPercent,
        progressMessage,
        startImport,
        stopPolling,
    };
}
