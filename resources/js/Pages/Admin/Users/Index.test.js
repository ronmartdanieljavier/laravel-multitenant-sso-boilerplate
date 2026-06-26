import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import UsersPage from './Index.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        Head: { template: '<slot />' },
        Link: { template: '<a><slot /></a>', props: ['href'] },
        router: { post: vi.fn() },
        usePage: () => ({
            props: {
                auth: { user: { name: 'Admin', email: 'admin@example.com', profile_picture_url: null } },
                missingRequiredSettings: [],
            },
        }),
        useForm: (initial) => {
            const data = reactive({ ...initial, errors: {}, processing: false });
            data.reset = () => Object.assign(data, { ...initial });
            data.clearErrors = () => {};
            data.post = vi.fn();
            data.put = vi.fn();
            return data;
        },
    };
});

const sampleApps = [
    { id: 1, name: 'Admin Portal', slug: 'admin' },
    { id: 2, name: 'Tenant App', slug: 'tenant' },
];

const sampleTenants = [
    { id: 1, name: 'Acme Corp' },
    { id: 2, name: 'Beta Inc' },
];

const sampleRoles = ['admin', 'user'];

const activeUser = {
    id: 10,
    name: 'Alice Admin',
    email: 'alice@example.com',
    is_active: true,
    profile_picture_url: null,
    apps: [{ app_id: 1, app_name: 'Admin Portal', role: 'admin', tenant_ids: [] }],
};

const pendingUser = {
    id: 11,
    name: 'Bob Pending',
    email: 'bob@example.com',
    is_active: false,
    profile_picture_url: null,
    apps: [],
};

function mountPage(users = [activeUser, pendingUser]) {
    return mount(UsersPage, {
        props: { users, apps: sampleApps, tenants: sampleTenants, roles: sampleRoles },
        attachTo: document.body,
    });
}

function bodyText() {
    return document.body.textContent ?? '';
}

describe('Admin/Users/Index', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });
    it('renders the page heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('User Management');
    });

    it('renders a row for each user', () => {
        const wrapper = mountPage();
        const rows = wrapper.findAll('tbody tr');
        expect(rows.length).toBe(2);
    });

    it('displays user name and email in each row', () => {
        const wrapper = mountPage();
        const text = wrapper.text();
        expect(text).toContain('Alice Admin');
        expect(text).toContain('alice@example.com');
        expect(text).toContain('Bob Pending');
        expect(text).toContain('bob@example.com');
    });

    it('shows Active badge for active users', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Active');
    });

    it('shows Pending badge for inactive users', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Pending');
    });

    it('displays app names and roles for users with apps', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Admin Portal');
        expect(wrapper.text()).toContain('admin');
    });

    it('shows None when user has no apps', () => {
        const wrapper = mountPage([pendingUser]);
        expect(wrapper.text()).toContain('None');
    });

    it('shows the empty state when no users are provided', () => {
        const wrapper = mountPage([]);
        expect(wrapper.text()).toContain('No users found.');
    });

    it('renders an Edit button for each user', () => {
        const wrapper = mountPage();
        const editButtons = wrapper.findAll('button').filter(b => b.text() === 'Edit');
        expect(editButtons.length).toBe(2);
    });

    it('renders the Users nav item as active', () => {
        const wrapper = mountPage();
        const activeLink = wrapper.findAll('a').find(a => a.classes().includes('border-blue-500'));
        expect(activeLink?.text()).toContain('Users');
    });

    it('does not show missing settings banner when settings are complete', () => {
        const wrapper = mountPage();
        expect(wrapper.find('.bg-amber-500\\/10').exists()).toBe(false);
    });

    describe('Invite modal', () => {
        it('invite modal is hidden by default', () => {
            mountPage();
            expect(bodyText()).not.toContain('Send Invitation');
        });

        it('opens the invite modal when Invite User button is clicked', async () => {
            const wrapper = mountPage();
            await wrapper.find('header button').trigger('click');

            expect(bodyText()).toContain('Invite User');
        });

        it('invite modal contains Name and Email fields', async () => {
            const wrapper = mountPage();
            await wrapper.find('header button').trigger('click');

            expect(bodyText()).toContain('Name');
            expect(bodyText()).toContain('Email');
        });

        it('invite modal contains Add App button', async () => {
            const wrapper = mountPage();
            await wrapper.find('header button').trigger('click');

            expect(bodyText()).toContain('+ Add App');
        });

        it('invite modal contains Send Invitation button', async () => {
            const wrapper = mountPage();
            await wrapper.find('header button').trigger('click');

            const submitBtn = document.querySelector('button[type="submit"]');
            expect(submitBtn.textContent).toContain('Send Invitation');
        });

        it('closes the invite modal when Cancel is clicked', async () => {
            const wrapper = mountPage();
            await wrapper.find('header button').trigger('click');
            expect(bodyText()).toContain('Send Invitation');

            const cancelBtns = document.querySelectorAll('button[type="button"]');
            const cancelBtn = Array.from(cancelBtns).find(b => b.textContent.trim() === 'Cancel');
            cancelBtn.click();
            await wrapper.vm.$nextTick();

            expect(bodyText()).not.toContain('Send Invitation');
        });

        it('adds an app row when Add App is clicked', async () => {
            const wrapper = mountPage();
            await wrapper.find('header button').trigger('click');

            const addAppBtn = Array.from(document.querySelectorAll('button[type="button"]'))
                .find(b => b.textContent.trim() === '+ Add App');
            addAppBtn.click();
            await wrapper.vm.$nextTick();

            expect(document.querySelector('select')).not.toBeNull();
        });

        it('renders tenant toggle buttons when app is added', async () => {
            const wrapper = mountPage();
            await wrapper.find('header button').trigger('click');

            const addAppBtn = Array.from(document.querySelectorAll('button[type="button"]'))
                .find(b => b.textContent.trim() === '+ Add App');
            addAppBtn.click();
            await wrapper.vm.$nextTick();

            expect(bodyText()).toContain('Acme Corp');
            expect(bodyText()).toContain('Beta Inc');
        });
    });

    describe('?invite query param deep-link', () => {
        afterEach(() => {
            window.history.replaceState({}, '', window.location.pathname);
        });

        it('opens the invite modal when mounted with ?invite param', async () => {
            window.history.replaceState({}, '', '?invite=1');
            const wrapper = mountPage();
            await wrapper.vm.$nextTick();

            expect(bodyText()).toContain('Invite User');
        });

        it('strips the ?invite param from the URL after mounting', async () => {
            window.history.replaceState({}, '', '?invite=1');
            const wrapper = mountPage();
            await wrapper.vm.$nextTick();

            expect(window.location.search).not.toContain('invite');
        });

        it('does not open the invite modal when mounted without ?invite param', async () => {
            const wrapper = mountPage();
            await wrapper.vm.$nextTick();

            expect(bodyText()).not.toContain('Send Invitation');
        });
    });

    describe('Edit modal', () => {
        it('edit modal is hidden by default', () => {
            mountPage();
            expect(bodyText()).not.toContain('Save Changes');
        });

        it('opens the edit modal when Edit is clicked', async () => {
            const wrapper = mountPage();
            const editBtn = wrapper.findAll('button').find(b => b.text() === 'Edit');
            await editBtn.trigger('click');

            expect(bodyText()).toContain('Save Changes');
        });

        it('pre-fills the edit form with the user name and email', async () => {
            const wrapper = mountPage();
            const editBtn = wrapper.findAll('button').find(b => b.text() === 'Edit');
            await editBtn.trigger('click');

            const inputs = Array.from(document.querySelectorAll('input[type="text"], input[type="email"]'));
            const values = inputs.map(i => i.value);
            expect(values).toContain('Alice Admin');
            expect(values).toContain('alice@example.com');
        });

        it('closes the edit modal when Cancel is clicked', async () => {
            const wrapper = mountPage();
            const editBtn = wrapper.findAll('button').find(b => b.text() === 'Edit');
            await editBtn.trigger('click');
            expect(bodyText()).toContain('Save Changes');

            const cancelBtns = document.querySelectorAll('button[type="button"]');
            const cancelBtn = Array.from(cancelBtns).find(b => b.textContent.trim() === 'Cancel');
            cancelBtn.click();
            await wrapper.vm.$nextTick();

            expect(bodyText()).not.toContain('Save Changes');
        });

        it('renders existing app permissions in the edit form', async () => {
            const wrapper = mountPage();
            const editBtn = wrapper.findAll('button').find(b => b.text() === 'Edit');
            await editBtn.trigger('click');

            const selects = document.querySelectorAll('select');
            expect(selects.length).toBeGreaterThan(0);
        });
    });
});
