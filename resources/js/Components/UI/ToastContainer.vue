<script setup>
import { useToast } from '@/composables/useToast';
import { 
    CheckCircle2, 
    AlertCircle, 
    AlertTriangle, 
    Info, 
    Loader2, 
    X,
    ExternalLink
} from 'lucide-vue-next';

const { toasts, dismiss } = useToast();
</script>

<template>
    <Teleport to="body">
        <div
            aria-live="assertive"
            class="fixed top-5 right-5 z-[99999] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0"
            style="z-index: 99999 !important;"
        >
        <TransitionGroup
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto w-full bg-white rounded-xl shadow-xl border overflow-hidden transition-all relative"
                :class="[
                    toast.type === 'success' ? 'border-emerald-200 bg-emerald-50/80 text-emerald-900' :
                    toast.type === 'error' ? 'border-red-200 bg-red-50/80 text-red-900' :
                    toast.type === 'warning' ? 'border-amber-200 bg-amber-50/80 text-amber-900' :
                    toast.type === 'progress' ? 'border-slate-200 bg-white text-slate-800' :
                    'border-blue-200 bg-blue-50/80 text-blue-900'
                ]"
            >
                <div class="p-4 flex items-start gap-3">
                    <!-- Icon -->
                    <div class="shrink-0 mt-0.5">
                        <CheckCircle2
                            v-if="toast.type === 'success'"
                            class="w-5 h-5 text-emerald-600"
                        />
                        <AlertCircle
                            v-else-if="toast.type === 'error'"
                            class="w-5 h-5 text-red-600"
                        />
                        <AlertTriangle
                            v-else-if="toast.type === 'warning'"
                            class="w-5 h-5 text-amber-600"
                        />
                        <Loader2
                            v-else-if="toast.type === 'progress'"
                            class="w-5 h-5 text-blue-600 animate-spin"
                        />
                        <Info
                            v-else
                            class="w-5 h-5 text-blue-600"
                        />
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0 pr-2">
                        <div class="flex items-center justify-between gap-2">
                            <h4
                                v-if="toast.title"
                                class="text-xs font-bold leading-tight truncate"
                                :class="[
                                    toast.type === 'success' ? 'text-emerald-950' :
                                    toast.type === 'error' ? 'text-red-950' :
                                    toast.type === 'warning' ? 'text-amber-950' :
                                    toast.type === 'progress' ? 'text-slate-900' :
                                    'text-blue-950'
                                ]"
                            >
                                {{ toast.title }}
                            </h4>
                            <span
                                v-if="toast.type === 'progress' && !toast.indeterminate"
                                class="text-[11px] font-bold text-slate-600 tabular-nums"
                            >
                                {{ Math.round(toast.progress) }}%
                            </span>
                        </div>

                        <p
                            v-if="toast.message"
                            class="text-xs mt-0.5 leading-relaxed break-words"
                            :class="[
                                toast.type === 'success' ? 'text-emerald-800' :
                                toast.type === 'error' ? 'text-red-800' :
                                toast.type === 'warning' ? 'text-amber-800' :
                                toast.type === 'progress' ? 'text-slate-600' :
                                'text-blue-800'
                            ]"
                        >
                            {{ toast.message }}
                        </p>

                        <!-- Progress Bar (for type === 'progress') -->
                        <div
                            v-if="toast.type === 'progress'"
                            class="mt-2.5 w-full bg-slate-100 rounded-full h-1.5 overflow-hidden"
                        >
                            <div
                                v-if="toast.indeterminate"
                                class="h-full bg-blue-600 rounded-full animate-indeterminate w-1/3"
                            ></div>
                            <div
                                v-else
                                class="h-full bg-blue-600 rounded-full transition-all duration-300 ease-out"
                                :style="{ width: `${Math.min(100, Math.max(0, toast.progress))}%` }"
                            ></div>
                        </div>

                        <!-- Action Button -->
                        <div v-if="toast.action" class="mt-2.5 flex items-center gap-2">
                            <button
                                type="button"
                                @click="toast.action.onClick ? toast.action.onClick() : null"
                                class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded bg-slate-900 text-white hover:bg-slate-800 transition"
                            >
                                <span>{{ toast.action.label }}</span>
                                <ExternalLink class="w-3 h-3" />
                            </button>
                        </div>
                    </div>

                    <!-- Dismiss Button -->
                    <button
                        type="button"
                        @click="dismiss(toast.id)"
                        class="shrink-0 p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/50 transition"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
@keyframes indeterminate {
    0% {
        transform: translateX(-100%);
    }
    50% {
        transform: translateX(100%);
    }
    100% {
        transform: translateX(300%);
    }
}

.animate-indeterminate {
    animation: indeterminate 1.5s infinite ease-in-out;
}
</style>

