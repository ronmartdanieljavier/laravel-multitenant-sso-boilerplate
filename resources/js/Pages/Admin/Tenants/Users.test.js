import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, afterEach } from 'vitest';
import UsersPage from './Users.vue';

vi.mock('../../../Layouts/AdminTenantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        Head: { template: '<slot />' },
        Link: { template: '<a><slot /></a>', props: ['href'] },
        router: { post: vi.fn() },
        usePage: () => ({
            props: {
                auth: { user: { name: 'Admin', email: 'admin@example.com', profile_picture_url: null } },
                flash: {},
                missingRequiredSettings: [],
            },
        }),
        useForm: (initial) => {
            const data = reactive({ ...initial, errors: {}, processing: false });
            data.reset = () => Object.assign(data, { ...initial });
            data.clearErrors = vi.fn();
            data.put = vi.fn();
            return data;
        },
    };
});

const tenant = { id: 3, name: 'Gamma Ltd', slug: 'gamma' };

const apps = [
    { id: 1, name: 'Admin Portal', slug: 'admin' },
    { id: 2, name: 'Tenant App', slug: 'tenant' },
];

const tenants = [
    { id: 3, name: 'Gamma Ltd' },
];

const activeUser = {
    id: 10,
    name: 'Alice Active',
    email: 'alice@example.com',
    is_active: true,
    invitation_sent_at: null,
    profile_picture_url: null,
    apps: [{ app_id: 1, app_name: 'Admin Portal', role: 'admin', tenant_ids: [3] }],
};

const invitedUser = {
    id: 11,
    name: 'Bob Invited',
    email: 'bob@example.com',
    is_active: false,
    invitation_sent_at: '2026-06-25T10:00:00Z',
    profile_picture_url: null,
    apps: [],
};

const inactiveUser = {
    id: 12,
    name: 'Carol Inactive',
    email: 'carol@example.com',
    is_active: false,
    invitation_sent_at: null,
    profile_picture_url: null,
    apps: [],
};

function mountPage(users = [activeUser, invitedUser]) {
    return mount(UsersPage, {
        props: { tenant, users, apps, tenants },
        attachTo: document.body,
    });
}

describe('Admin/Tenants/Users', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('renders the tenant name in the heading', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Gamma Ltd');
    });

    it('renders a row for each user', () => {
        const wrapper = mountPage([activeUser, invitedUser, inactiveUser]);
        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(3);
    });

    it('shows user names in the table', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('Alice Active');
        expect(wrapper.text()).toContain('Bob Invited');
    });

    it('shows user emails in the table', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('alice@example.com');
    });

    it('shows Active status badge for active users', () => {
        const wrapper = mountPage([activeUser]);
        expect(wrapper.text()).toContain('Active');
    });

    it('shows Invited status badge for pending users', () => {
        const wrapper = mountPage([invitedUser]);
        expect(wrapper.text()).toContain('Invited');
    });

    it('shows Inactive status badge for inactive users', () => {
        const wrapper = mountPage([inactiveUser]);
        expect(wrapper.text()).toContain('Inactive');
    });

    it('opens edit modal when clicking Edit button', async () => {
        const wrapper = mountPage([activeUser]);
        const editBtn = wrapper.findAll('button').find(b => b.text() === 'Edit');
        await editBtn.trigger('click');
        expect(wrapper.text()).toContain('Edit User');
    });

    it('closes edit modal when clicking cancel', async () => {
        const wrapper = mountPage([activeUser]);
        const editBtn = wrapper.findAll('button').find(b => b.text() === 'Edit');
        await editBtn.trigger('click');

        const cancelBtn = wrapper.findAll('button').find(b => b.text() === 'Cancel');
        await cancelBtn.trigger('click');

        expect(wrapper.text()).not.toContain('Edit User');
    });

    it('renders app permissions for the user being edited', async () => {
        const wrapper = mountPage([activeUser]);
        const editBtn = wrapper.findAll('button').find(b => b.text() === 'Edit');
        await editBtn.trigger('click');
        expect(wrapper.text()).toContain('Admin Portal');
    });

    it('renders empty state when no users exist', () => {
        const wrapper = mountPage([]);
        expect(wrapper.text()).toContain('No users');
    });
});
