import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeAll } from 'vitest';
import LoginPage from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    useForm: () => ({
        email: '',
        password: '',
        processing: false,
        errors: {},
        post: vi.fn(),
    }),
}));

describe('Login/Index', () => {
    it('renders the email and password fields', () => {
        const wrapper = mount(LoginPage);

        expect(wrapper.find('input[type="email"]').exists()).toBe(true);
        expect(wrapper.find('input[type="password"]').exists()).toBe(true);
    });

    it('renders the sign in button', () => {
        const wrapper = mount(LoginPage);

        expect(wrapper.find('button[type="submit"]').text()).toContain('Sign in');
    });

    it('renders the brand heading', () => {
        const wrapper = mount(LoginPage);

        expect(wrapper.text()).toContain('Welcome back');
    });
});
