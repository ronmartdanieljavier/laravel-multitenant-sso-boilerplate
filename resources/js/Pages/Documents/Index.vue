<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TenantLayout from '../../Layouts/TenantLayout.vue';

defineOptions({ layout: TenantLayout });

const props = defineProps({
    documents: {
        type: Object,
        required: true,
    },
    tenant: {
        type: Object,
        required: true,
    },
    uploadConstraints: {
        type: Object,
        default: () => ({ allowedTypes: ['pdf', 'doc', 'text', 'excel', 'image', 'csv'], maxSizes: {} }),
    },
});

const uploadForm = useForm({
    title: '',
    description: '',
    file: null,
});

const fileInput = ref(null);
const selectedFileName = ref(null);

function submitUpload() {
    uploadForm.post('/documents', {
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
            selectedFileName.value = null;
            if (fileInput.value) { fileInput.value.value = ''; }
        },
    });
}

function deleteDocument(id) {
    if (!confirm('Delete this document?')) { return; }
    router.delete(`/documents/${id}`, { preserveScroll: true });
}

function goToPage(url) {
    if (url) { router.visit(url, { preserveScroll: true }); }
}

function formatBytes(bytes) {
    if (bytes < 1024) { return bytes + ' B'; }
    if (bytes < 1_048_576) { return (bytes / 1024).toFixed(1) + ' KB'; }
    return (bytes / 1_048_576).toFixed(1) + ' MB';
}

function formatDate(iso) {
    if (!iso) { return '—'; }
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
    });
}

const TYPE_LABELS = {
    pdf: 'PDF',
    doc: 'Word (DOC/DOCX)',
    text: 'Text',
    excel: 'Excel (XLS/XLSX)',
    image: 'Images (JPG/PNG/GIF/WebP)',
    csv: 'CSV',
};

const TYPE_EXTENSIONS = {
    pdf: '.pdf',
    doc: '.doc,.docx',
    text: '.txt',
    excel: '.xls,.xlsx',
    image: '.jpg,.jpeg,.png,.gif,.webp',
    csv: '.csv',
};

const allowedExtensions = computed(() => {
    return props.uploadConstraints.allowedTypes
        .map(t => TYPE_EXTENSIONS[t] ?? '')
        .filter(Boolean)
        .join(',');
});

const allowedHint = computed(() => {
    const parts = props.uploadConstraints.allowedTypes.map(t => {
        const label = TYPE_LABELS[t] ?? t.toUpperCase();
        const maxMb = props.uploadConstraints.maxSizes?.[t] ?? 5;
        return `${label} (max ${maxMb} MB)`;
    });
    return parts.join(', ');
});

function onFileSelected(event) {
    const file = event.target.files[0];
    if (!file) { return; }
    uploadForm.file = file;
    selectedFileName.value = file.name;
}

const SOURCE_LABELS = {
    upload: 'User Upload',
    report: 'Report',
    subscription: 'Subscription',
};

const SOURCE_CLASSES = {
    upload: 'bg-slate-700 text-slate-300',
    report: 'bg-blue-500/15 text-blue-300',
    subscription: 'bg-purple-500/15 text-purple-300',
};

function sourceLabel(source) {
    return SOURCE_LABELS[source] ?? source;
}

function sourceClass(source) {
    return SOURCE_CLASSES[source] ?? 'bg-slate-700 text-slate-300';
}
</script>

<template>
    <Head title="Documents" />

    <header class="h-16 bg-slate-900/50 border-b border-white/5 flex items-center px-8">
        <h2 class="text-lg font-semibold">Documents</h2>
    </header>

    <main class="p-8 space-y-6 max-w-6xl">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success"
             class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm px-4 py-3 rounded-xl">
            {{ $page.props.flash.success }}
        </div>

        <!-- Upload -->
        <section class="bg-slate-900 border border-white/5 rounded-xl p-6">
            <h3 class="font-semibold text-white mb-4">Upload Document</h3>
            <form @submit.prevent="submitUpload" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-slate-400 mb-1.5">Title</label>
                        <input
                            v-model="uploadForm.title"
                            type="text"
                            class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-emerald-500 transition"
                            placeholder="Document title"
                        />
                        <p v-if="uploadForm.errors.title" class="text-red-400 text-xs mt-1">{{ uploadForm.errors.title }}</p>
                    </div>
                    <div>
                        <label class="block text-sm text-slate-400 mb-1.5">File</label>
                        <div class="flex items-center gap-3">
                            <input ref="fileInput" type="file" class="hidden" :accept="allowedExtensions" @change="onFileSelected" />
                            <button
                                type="button"
                                @click="fileInput.click()"
                                class="shrink-0 bg-slate-800 hover:bg-slate-700 border border-white/10 text-slate-300 text-sm px-4 py-2 rounded-lg transition"
                            >
                                Choose File
                            </button>
                            <span class="text-sm text-slate-400 truncate">{{ selectedFileName ?? 'No file chosen' }}</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">{{ allowedHint }}</p>
                        <p v-if="uploadForm.errors.file" class="text-red-400 text-xs mt-1">{{ uploadForm.errors.file }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1.5">Description <span class="text-slate-600">(optional)</span></label>
                    <input
                        v-model="uploadForm.description"
                        type="text"
                        class="w-full bg-slate-800 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-emerald-500 transition"
                        placeholder="Brief description…"
                    />
                    <p v-if="uploadForm.errors.description" class="text-red-400 text-xs mt-1">{{ uploadForm.errors.description }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="uploadForm.processing || !uploadForm.file || !uploadForm.title"
                    class="bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    {{ uploadForm.processing ? 'Uploading…' : 'Upload' }}
                </button>
            </form>
        </section>

        <!-- Document Table -->
        <section class="bg-slate-900 border border-white/5 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                <h3 class="font-semibold text-white">All Documents</h3>
                <span class="text-xs text-slate-500">{{ documents.total }} {{ documents.total === 1 ? 'file' : 'files' }}</span>
            </div>

            <div v-if="documents.data.length === 0" class="px-6 py-16 text-center text-slate-500 text-sm">
                No documents yet. Upload one above.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/5">
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">File name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Source</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-400 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Uploaded by</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">Upload date</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-white/[0.02] transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-800 border border-white/10 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <span class="text-white font-medium truncate max-w-[200px]">{{ doc.file_name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-300 truncate max-w-[180px]">{{ doc.title }}</td>
                            <td class="px-6 py-4">
                                <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium', sourceClass(doc.source)]">
                                    {{ sourceLabel(doc.source) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-slate-400 tabular-nums whitespace-nowrap">{{ formatBytes(doc.file_size) }}</td>
                            <td class="px-6 py-4 text-slate-400 whitespace-nowrap">{{ doc.uploaded_by_name }}</td>
                            <td class="px-6 py-4 text-slate-400 whitespace-nowrap">{{ formatDate(doc.created_at) }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a
                                        :href="`/documents/${doc.id}/download`"
                                        class="inline-flex items-center gap-1.5 text-xs text-emerald-400 hover:text-emerald-300 px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 transition"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        Download
                                    </a>
                                    <button
                                        @click="deleteDocument(doc.id)"
                                        class="inline-flex items-center gap-1.5 text-xs text-red-400 hover:text-red-300 px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 transition"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="documents.last_page > 1" class="px-6 py-4 border-t border-white/5 flex items-center justify-between text-sm text-slate-400">
                <span>Page {{ documents.current_page }} of {{ documents.last_page }}</span>
                <div class="flex gap-2">
                    <button
                        @click="goToPage(documents.prev_page_url)"
                        :disabled="!documents.prev_page_url"
                        class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300"
                    >
                        Previous
                    </button>
                    <button
                        @click="goToPage(documents.next_page_url)"
                        :disabled="!documents.next_page_url"
                        class="px-3 py-1.5 rounded-lg border border-white/10 hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed transition text-slate-300"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>

    </main>
</template>
