import { ref } from 'vue';

// Global reactive toasts state
const toasts = ref([]);
let toastSeq = 0;

export function useToast() {
    /**
     * Add a generic toast
     */
    const add = (options) => {
        const id = options.id || `toast_${++toastSeq}`;
        const type = options.type || 'info'; // 'success' | 'error' | 'warning' | 'info' | 'progress'
        const duration = options.duration !== undefined ? options.duration : (type === 'error' ? 7000 : 4500);

        const newToast = {
            id,
            type,
            title: options.title || '',
            message: options.message || '',
            progress: options.progress || 0,
            indeterminate: options.indeterminate ?? (type === 'progress' && options.progress === undefined),
            action: options.action || null, // { label: string, onClick: function, href?: string }
            timer: null,
            createdAt: Date.now(),
        };

        // Remove any existing toast with the same ID
        const existingIdx = toasts.value.findIndex(t => t.id === id);
        if (existingIdx > -1) {
            if (toasts.value[existingIdx].timer) clearTimeout(toasts.value[existingIdx].timer);
            toasts.value.splice(existingIdx, 1);
        }

        // Set auto dismiss if duration > 0 and not progress
        if (duration > 0 && type !== 'progress') {
            newToast.timer = setTimeout(() => {
                dismiss(id);
            }, duration);
        }

        toasts.value.unshift(newToast);
        return id;
    };

    /**
     * Convenience helpers
     */
    const success = (message, options = {}) => {
        return add({
            ...options,
            type: 'success',
            title: options.title || 'Berhasil',
            message,
        });
    };

    const error = (message, options = {}) => {
        return add({
            ...options,
            type: 'error',
            title: options.title || 'Terjadi Kesalahan',
            message,
        });
    };

    const info = (message, options = {}) => {
        return add({
            ...options,
            type: 'info',
            title: options.title || 'Informasi',
            message,
        });
    };

    const warning = (message, options = {}) => {
        return add({
            ...options,
            type: 'warning',
            title: options.title || 'Peringatan',
            message,
        });
    };

    /**
     * Start a progress toast
     */
    const progress = (message, options = {}) => {
        return add({
            ...options,
            type: 'progress',
            title: options.title || 'Memproses...',
            message,
            progress: options.progress !== undefined ? options.progress : 0,
            indeterminate: options.indeterminate ?? (options.progress === undefined),
            duration: 0, // do not auto-dismiss while in progress
        });
    };

    /**
     * Update an active toast (e.g. progress percentage or message)
     */
    const update = (id, options = {}) => {
        const target = toasts.value.find(t => t.id === id);
        if (!target) return;

        if (options.message !== undefined) target.message = options.message;
        if (options.title !== undefined) target.title = options.title;
        if (options.progress !== undefined) {
            target.progress = options.progress;
            target.indeterminate = false;
        }
        if (options.indeterminate !== undefined) target.indeterminate = options.indeterminate;
        if (options.type !== undefined) target.type = options.type;
        if (options.action !== undefined) target.action = options.action;

        // If updated to final status (success/error), set auto dismiss timer
        if (options.type && options.type !== 'progress') {
            if (target.timer) clearTimeout(target.timer);
            const duration = options.duration !== undefined ? options.duration : (options.type === 'error' ? 7000 : 4500);
            if (duration > 0) {
                target.timer = setTimeout(() => {
                    dismiss(id);
                }, duration);
            }
        }
    };

    /**
     * Finish a progress toast with success or error state
     */
    const finish = (id, { status = 'success', message, title, action, duration } = {}) => {
        update(id, {
            type: status === 'error' ? 'error' : 'success',
            title: title || (status === 'error' ? 'Gagal' : 'Selesai'),
            message: message || (status === 'error' ? 'Proses gagal.' : 'Proses berhasil diselesaikan.'),
            progress: 100,
            indeterminate: false,
            action,
            duration: duration ?? (status === 'error' ? 7000 : 4500),
        });
    };

    /**
     * Dismiss a toast by id
     */
    const dismiss = (id) => {
        const idx = toasts.value.findIndex(t => t.id === id);
        if (idx > -1) {
            if (toasts.value[idx].timer) clearTimeout(toasts.value[idx].timer);
            toasts.value.splice(idx, 1);
        }
    };

    /**
     * Clear all toasts
     */
    const clear = () => {
        toasts.value.forEach(t => {
            if (t.timer) clearTimeout(t.timer);
        });
        toasts.value = [];
    };

    return {
        toasts,
        add,
        success,
        error,
        info,
        warning,
        progress,
        update,
        finish,
        dismiss,
        clear,
    };
}
