<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Underline from '@tiptap/extension-underline'

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            heading: false,
            blockquote: false,
            codeBlock: false,
            code: false,
            horizontalRule: false,
        }),
        Underline,
        Link.configure({
            openOnClick: false,
            HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-invert prose-sm max-w-none min-h-[120px] px-3 py-2 focus:outline-none text-sm text-white',
        },
    },
    onUpdate({ editor: e }) {
        emit('update:modelValue', e.getHTML())
    },
})

watch(() => props.modelValue, (value) => {
    if (editor.value && editor.value.getHTML() !== value) {
        editor.value.commands.setContent(value, false)
    }
})

onBeforeUnmount(() => editor.value?.destroy())

function setLink() {
    const previous = editor.value.getAttributes('link').href
    const url = window.prompt('URL', previous)
    if (url === null) { return }
    if (url === '') {
        editor.value.chain().focus().extendMarkToNextWord().unsetLink().run()
        return
    }
    editor.value.chain().focus().extendMarkToNextWord().setLink({ href: url }).run()
}
</script>

<template>
    <div class="bg-slate-800 border border-white/10 rounded-lg overflow-hidden focus-within:border-violet-500 transition">
        <!-- Toolbar -->
        <div class="flex items-center gap-1 px-2 py-1.5 border-b border-white/10 flex-wrap">
            <button type="button" title="Bold"
                    @click="editor?.chain().focus().toggleBold().run()"
                    :class="editor?.isActive('bold') ? 'bg-white/20 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                    class="px-2 py-1 rounded text-xs font-bold transition">
                B
            </button>
            <button type="button" title="Italic"
                    @click="editor?.chain().focus().toggleItalic().run()"
                    :class="editor?.isActive('italic') ? 'bg-white/20 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                    class="px-2 py-1 rounded text-xs italic transition">
                I
            </button>
            <button type="button" title="Underline"
                    @click="editor?.chain().focus().toggleUnderline().run()"
                    :class="editor?.isActive('underline') ? 'bg-white/20 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                    class="px-2 py-1 rounded text-xs underline transition">
                U
            </button>

            <span class="w-px h-4 bg-white/10 mx-1"></span>

            <button type="button" title="Bullet list"
                    @click="editor?.chain().focus().toggleBulletList().run()"
                    :class="editor?.isActive('bulletList') ? 'bg-white/20 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                    class="px-2 py-1 rounded text-xs transition">
                ≡
            </button>
            <button type="button" title="Ordered list"
                    @click="editor?.chain().focus().toggleOrderedList().run()"
                    :class="editor?.isActive('orderedList') ? 'bg-white/20 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                    class="px-2 py-1 rounded text-xs transition">
                1.
            </button>

            <span class="w-px h-4 bg-white/10 mx-1"></span>

            <button type="button" title="Link" @click="setLink"
                    :class="editor?.isActive('link') ? 'bg-white/20 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                    class="px-2 py-1 rounded text-xs transition">
                🔗
            </button>
            <button type="button" title="Remove link"
                    v-if="editor?.isActive('link')"
                    @click="editor?.chain().focus().unsetLink().run()"
                    class="px-2 py-1 rounded text-xs text-slate-400 hover:text-white hover:bg-white/10 transition">
                ✕
            </button>

            <span class="w-px h-4 bg-white/10 mx-1"></span>

            <button type="button" title="Clear formatting"
                    @click="editor?.chain().focus().clearNodes().unsetAllMarks().run()"
                    class="px-2 py-1 rounded text-xs text-slate-400 hover:text-white hover:bg-white/10 transition">
                Tx
            </button>
        </div>

        <EditorContent :editor="editor" />
    </div>
</template>
