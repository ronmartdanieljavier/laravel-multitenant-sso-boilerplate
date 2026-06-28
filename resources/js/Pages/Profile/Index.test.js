import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import ProfilePage from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<slot />' },
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    router: { post: vi.fn() },
    useForm: vi.fn((initial) => ({
        ...initial,
        processing: false,
        errors: {},
        put: vi.fn(),
        post: vi.fn(),
        reset: vi.fn(),
    })),
    usePage: vi.fn(),
}));

vi.mock('../../composables/useIdleTimeout', () => ({ useIdleTimeout: vi.fn() }));
vi.mock('../Partials/TourButton.vue', () => ({ default: { template: '<button data-testid="tour-btn" />' } }));
vi.mock('../../composables/useTour', () => ({ useTour: vi.fn(() => ({ startTour: vi.fn() })) }));

const baseUser = { name: 'Alice Admin', email: 'alice@example.com', profile_picture_url: null };

function makePage(user = baseUser, flash = {}) {
    return { props: { auth: { user }, flash, idleTimeoutMinutes: null }, url: '/profile' };
}

async function mountPage(user = baseUser, flash = {}) {
    const { usePage } = await import('@inertiajs/vue3');
    const page = makePage(user, flash);
    usePage.mockReturnValue(page);

    return mount(ProfilePage, {
        attachTo: document.body,
        global: {
            config: {
                globalProperties: { $page: page },
            },
        },
    });
}

describe('Profile/Index', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the My Profile heading', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('My Profile');
    });

    it('renders the Display Name input pre-filled with user name', async () => {
        const wrapper = await mountPage();
        const input = wrapper.find('input[type="text"]');
        expect(input.element.value).toBe('Alice Admin');
    });

    it('renders a Save Name button', async () => {
        const wrapper = await mountPage();
        const btn = wrapper.findAll('button').find(b => b.text().includes('Save Name'));
        expect(btn?.exists()).toBe(true);
    });

    it('does not render a Dashboard nav link', async () => {
        const wrapper = await mountPage();
        const hrefs = wrapper.findAll('a').map(a => a.attributes('href'));
        expect(hrefs).not.toContain('/admin');
    });

    it('renders the Display Name section', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Display Name');
    });

    it('renders the Profile Picture section', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Profile Picture');
    });

    it('renders the Change Password section', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Change Password');
    });

    it('renders user initial in the avatar', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('A');
    });

    it('renders the Display Name section heading', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Display Name');
    });

    it('renders profile picture img when url is provided', async () => {
        const wrapper = await mountPage({ ...baseUser, profile_picture_url: 'https://example.com/pic.jpg' });
        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://example.com/pic.jpg');
    });

    it('renders user initial avatar when no profile picture', async () => {
        const wrapper = await mountPage({ ...baseUser, profile_picture_url: null });
        expect(wrapper.text()).toContain('A');
    });

    it('calls nameForm.put when Save Name button is submitted', async () => {
        const wrapper = await mountPage();
        const form = wrapper.find('form');
        await form.trigger('submit');
        const { useForm } = await import('@inertiajs/vue3');
        const mockForm = useForm.mock.results[0]?.value;
        expect(mockForm?.put).toHaveBeenCalledWith('/profile/name', expect.any(Object));
    });

    it('renders flash success message when present', async () => {
        const wrapper = await mountPage(baseUser, { success: 'Profile updated!' });
        expect(wrapper.text()).toContain('Profile updated!');
    });
});
