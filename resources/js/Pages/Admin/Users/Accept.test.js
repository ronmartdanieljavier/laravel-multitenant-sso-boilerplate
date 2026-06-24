import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import AcceptPage from './Accept.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    useForm: (initial) => {
        const data = { ...initial };
        const errors = {};
        let processing = false;

        return new Proxy(data, {
            get(target, key) {
                if (key === 'errors') return errors;
                if (key === 'processing') return processing;
                if (key === 'reset') return vi.fn();
                if (key === 'post') return vi.fn();
                return target[key];
            },
            set(target, key, value) {
                target[key] = value;
                return true;
            },
        });
    },
}));

function mountPage(props = {}) {
    return mount(AcceptPage, {
        props: {
            token: 'abc123',
            name: 'Jane Doe',
            email: 'jane@example.com',
            ...props,
        },
    });
}

describe('Admin/Users/Accept', () => {
    it('renders the page heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Set up your account');
    });

    it('displays the email as read-only text', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('jane@example.com');
        expect(wrapper.find('input[type="email"]').exists()).toBe(false);
    });

    it('pre-fills the name field with the prop value', () => {
        const wrapper = mountPage();
        const nameInput = wrapper.find('input[type="text"]');
        expect(nameInput.element.value).toBe('Jane Doe');
    });

    it('renders the password field', () => {
        const wrapper = mountPage();
        const passwordInputs = wrapper.findAll('input[type="password"]');
        expect(passwordInputs.length).toBeGreaterThanOrEqual(2);
    });

    it('renders the Activate Account button', () => {
        const wrapper = mountPage();
        const btn = wrapper.find('button[type="submit"]');
        expect(btn.text()).toBe('Activate Account');
    });

    it('renders the confirm password field', () => {
        const wrapper = mountPage();
        const labels = wrapper.findAll('label');
        const labelTexts = labels.map(l => l.text());
        expect(labelTexts).toContain('Confirm Password');
    });

    it('shows the invitation description text', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain("You've been invited to join");
    });

    it('name field allows input update', async () => {
        const wrapper = mountPage();
        const nameInput = wrapper.find('input[type="text"]');
        await nameInput.setValue('Updated Name');
        expect(nameInput.element.value).toBe('Updated Name');
    });
});
