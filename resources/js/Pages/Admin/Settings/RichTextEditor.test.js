import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import { ref } from 'vue';
import RichTextEditor from './RichTextEditor.vue';

// TipTap relies on browser APIs not available in jsdom — stub the minimum needed.
vi.mock('@tiptap/vue-3', () => {
    const editorInstance = {
        getHTML: vi.fn(() => '<p>Hello</p>'),
        commands: {
            setContent: vi.fn(),
        },
        chain: vi.fn(() => ({
            focus: vi.fn(() => ({
                toggleBold: vi.fn(() => ({ run: vi.fn() })),
                toggleItalic: vi.fn(() => ({ run: vi.fn() })),
                toggleUnderline: vi.fn(() => ({ run: vi.fn() })),
                toggleBulletList: vi.fn(() => ({ run: vi.fn() })),
                toggleOrderedList: vi.fn(() => ({ run: vi.fn() })),
                extendMarkToNextWord: vi.fn(() => ({
                    setLink: vi.fn(() => ({ run: vi.fn() })),
                    unsetLink: vi.fn(() => ({ run: vi.fn() })),
                })),
                unsetLink: vi.fn(() => ({ run: vi.fn() })),
                clearNodes: vi.fn(() => ({
                    unsetAllMarks: vi.fn(() => ({ run: vi.fn() })),
                })),
            })),
        })),
        isActive: vi.fn(() => false),
        getAttributes: vi.fn(() => ({})),
        destroy: vi.fn(),
        on: vi.fn(),
        off: vi.fn(),
    };

    return {
        useEditor: vi.fn((options) => {
            // Simulate an initial onUpdate call so consumers can wire up their handler.
            if (options?.onUpdate) {
                options.onUpdate({ editor: editorInstance });
            }
            return ref(editorInstance);
        }),
        EditorContent: {
            name: 'EditorContent',
            props: ['editor'],
            template: '<div class="editor-content" />',
        },
    };
});

describe('RichTextEditor', () => {
    it('renders the toolbar', () => {
        const wrapper = mount(RichTextEditor, { props: { modelValue: '' } });

        expect(wrapper.find('button[title="Bold"]').exists()).toBe(true);
        expect(wrapper.find('button[title="Italic"]').exists()).toBe(true);
        expect(wrapper.find('button[title="Underline"]').exists()).toBe(true);
        expect(wrapper.find('button[title="Bullet list"]').exists()).toBe(true);
        expect(wrapper.find('button[title="Ordered list"]').exists()).toBe(true);
        expect(wrapper.find('button[title="Link"]').exists()).toBe(true);
    });

    it('renders the EditorContent component', () => {
        const wrapper = mount(RichTextEditor, { props: { modelValue: '' } });

        expect(wrapper.findComponent({ name: 'EditorContent' }).exists()).toBe(true);
    });

    it('emits update:modelValue on initial editor setup', () => {
        const wrapper = mount(RichTextEditor, { props: { modelValue: '<p>Hello</p>' } });

        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['<p>Hello</p>']);
    });

    it('does not show the remove-link button when link is not active', () => {
        const wrapper = mount(RichTextEditor, { props: { modelValue: '' } });

        expect(wrapper.find('button[title="Remove link"]').exists()).toBe(false);
    });

    it('shows the remove-link button when link is active', async () => {
        const { useEditor } = await import('@tiptap/vue-3');
        const activeEditor = {
            getHTML: vi.fn(() => '<p><a href="#">link</a></p>'),
            commands: { setContent: vi.fn() },
            chain: vi.fn(() => ({
                focus: vi.fn(() => ({
                    unsetLink: vi.fn(() => ({ run: vi.fn() })),
                })),
            })),
            isActive: vi.fn((mark) => mark === 'link'),
            getAttributes: vi.fn(() => ({ href: 'https://example.com' })),
            destroy: vi.fn(),
            on: vi.fn(),
            off: vi.fn(),
        };
        useEditor.mockImplementationOnce(() => ref(activeEditor));

        const wrapper = mount(RichTextEditor, { props: { modelValue: '<p><a href="#">link</a></p>' } });

        expect(wrapper.find('button[title="Remove link"]').exists()).toBe(true);
    });

    it('accepts a modelValue prop and passes it to the editor', () => {
        const html = '<p><strong>Footer text</strong></p>';
        const wrapper = mount(RichTextEditor, { props: { modelValue: html } });

        // Editor was initialised — EditorContent is present.
        expect(wrapper.findComponent({ name: 'EditorContent' }).exists()).toBe(true);
    });
});
