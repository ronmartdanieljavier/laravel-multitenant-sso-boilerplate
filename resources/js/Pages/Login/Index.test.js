import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import LoginPage from './Index.vue';

const postMock = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    useForm: () => ({
        email: '',
        password: '',
        processing: false,
        errors: {},
        post: postMock,
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

        expect(wrapper.text()).toContain('Sign in');
    });

    it('submits to the web login route', async () => {
        const wrapper = mount(LoginPage);
        await wrapper.find('form').trigger('submit');

        expect(postMock).toHaveBeenCalledWith('/login');
    });
});
