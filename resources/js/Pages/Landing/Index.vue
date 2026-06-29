<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';

const isScrolled = ref(false);
function onScroll() { isScrolled.value = window.scrollY > 24; }
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }));
onUnmounted(() => window.removeEventListener('scroll', onScroll));

const githubUrl = 'https://github.com/ronmartdanieljavier/laravel-multitenant-sso-boilerplate';

// ── Why this exists ──
const goals = [
    {
        tag: 'Skill Showcase',
        accent: 'text-blue-400',
        border: 'border-blue-500/20',
        bg: 'bg-blue-500/[0.06]',
        icon: 'M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5',
        title: 'Senior full-stack capability',
        body: 'This repo demonstrates architectural discipline at scale — strict layered architecture, full API parity, DTO contracts, PHPUnit test coverage, and a clean module structure that scales across teams.',
    },
    {
        tag: 'AI-Assisted Dev',
        accent: 'text-violet-400',
        border: 'border-violet-500/20',
        bg: 'bg-violet-500/[0.06]',
        icon: 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z M18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z',
        title: 'Claude AI in the dev workflow',
        body: 'The entire codebase — architecture decisions, module scaffolding, test suites, and this UI — was built with Claude Code as a collaborative engineering partner, demonstrating real-world AI-assisted development.',
    },
    {
        tag: 'Starter Kit',
        accent: 'text-emerald-400',
        border: 'border-emerald-500/20',
        bg: 'bg-emerald-500/[0.06]',
        icon: 'M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z',
        title: 'Your multi-tenant project foundation',
        body: 'Clone it, rename the modules, and start building your product. Every tenant isolation pattern, SSO flow, queue system, and error tracker is already wired — skip the boilerplate, ship the feature.',
    },
];

// ── Features ──
const features = [
    { icon: 'key',      tag: 'Auth',          accent: 'text-blue-400',    border: 'border-blue-500/20',    glow: 'from-blue-500/15 to-transparent',    title: 'Single Sign-On',      body: 'One login for all your tenants. Users authenticate once and access every permitted app via Sanctum tokens.' },
    { icon: 'building', tag: 'Multi-tenancy', accent: 'text-violet-400',  border: 'border-violet-500/20',  glow: 'from-violet-500/12 to-transparent',  title: 'True Tenant Isolation', body: 'Each tenant gets isolated data, runtime config, and branding. Per-request DB connection from central resolver.' },
    { icon: 'shield',   tag: 'Permissions',  accent: 'text-indigo-400',  border: 'border-indigo-500/20',  glow: 'from-indigo-500/12 to-transparent',  title: 'Role-Based Access',    body: 'Fine-grained roles per user, per tenant, per app. Assign and revoke independently at any level.' },
    { icon: 'chart',    tag: 'Queues',        accent: 'text-sky-400',     border: 'border-sky-500/20',     glow: 'from-sky-500/12 to-transparent',     title: 'Horizon Job Queues',   body: 'Horizon-powered queues with per-tenant visibility, health monitoring, and scheduled report delivery.' },
    { icon: 'bell',     tag: 'Observability', accent: 'text-rose-400',    border: 'border-rose-500/20',    glow: 'from-rose-500/12 to-transparent',    title: 'Error Tracking',       body: 'Capture exceptions per tenant with unique codes, production masking, and a resolve/reopen workflow.' },
    { icon: 'cog',      tag: 'Config',        accent: 'text-emerald-400', border: 'border-emerald-500/20', glow: 'from-emerald-500/12 to-transparent', title: 'Runtime Tenant Config', body: 'Override mail, S3, branding, and report config per tenant at runtime — no redeploy required.' },
];

// ── AI workflow steps ──
const aiSteps = [
    { num: '01', title: 'Architecture planning', body: 'Claude reviewed module boundaries, proposed the Controller→Service→Repository→Model chain, and enforced DTO contracts throughout every layer.' },
    { num: '02', title: 'Code generation', body: 'Features were scaffolded via Claude Code — models, migrations, services, repositories, form requests, API controllers, and tests in one coherent pass.' },
    { num: '03', title: 'Test authorship', body: '701 PHPUnit tests written collaboratively with Claude — covering happy paths, failure cases, permission boundaries, and edge cases across every module.' },
    { num: '04', title: 'UI/UX design', body: 'This entire interface was redesigned with Claude using the ui-ux-pro-max skill — from the landing page to every admin and tenant page, in a single session.' },
];

// ── Install steps ──
const installMethod = ref('manual');

const manualSteps = [
    { cmd: 'git clone https://github.com/ronmartdanieljavier/laravel-multitenant-sso-boilerplate.git my-app', comment: '# Clone the repo' },
    { cmd: 'cd my-app && composer install && npm install', comment: '# Install dependencies' },
    { cmd: 'cp .env.example .env && php artisan key:generate', comment: '# Set up environment' },
    { cmd: 'php artisan migrate --seed', comment: '# Run migrations & seed admin user' },
    { cmd: 'composer run dev', comment: '# Start Laravel + Vite + Horizon' },
];

const dockerSteps = [
    { cmd: 'git clone https://github.com/ronmartdanieljavier/laravel-multitenant-sso-boilerplate.git my-app && cd my-app', comment: '# Clone the repo' },
    { cmd: 'cp .env.example .env', comment: '# Set up environment (Docker services are pre-configured)' },
    { cmd: 'docker compose up -d', comment: '# Start all services (PHP, Nginx, Redis, PostgreSQL)' },
    { cmd: 'docker compose exec app composer install && docker compose exec app npm install && npm run build', comment: '# Install dependencies & build assets' },
    { cmd: 'docker compose exec app php artisan key:generate && php artisan migrate --seed', comment: '# Generate key & seed the database' },
];

const installSteps = computed(() => installMethod.value === 'docker' ? dockerSteps : manualSteps);

// ── New project steps ──
const newProjectSteps = [
    { step: '1', title: 'Clone & rename', body: 'Clone this repo, update APP_NAME in .env, and replace "SSO Admin" branding in the layouts. Your multi-tenant foundation is ready.' },
    { step: '2', title: 'Add your modules', body: 'Follow the existing module structure under app/{ModuleName}/. Each module has its own Controllers, Services, Repositories, DTOs, Routes, and Tests.' },
    { step: '3', title: 'Create your first tenant', body: 'Use the Admin panel to create tenants, invite users, and assign roles. No code needed — everything is wired up in the UI.' },
    { step: '4', title: 'Extend the report engine', body: 'Drop a new GenerateXReport job into the queue system. The scheduling, delivery, and per-tenant config are already handled.' },
    { step: '5', title: 'Ship', body: 'Deploy to Laravel Cloud or any VPS. The layered architecture, test suite, and strict conventions make it safe to extend and maintain at scale.' },
];

const stack = [
    { name: 'Laravel 13',     role: 'Backend framework' },
    { name: 'Inertia.js v3',  role: 'SPA without the SPA' },
    { name: 'Vue 3',          role: 'Frontend reactivity' },
    { name: 'Tailwind CSS',   role: 'Utility-first styling' },
    { name: 'Horizon',        role: 'Queue monitoring' },
    { name: 'Sanctum',        role: 'API auth tokens' },
    { name: 'PHPUnit 12',     role: '701 tests' },
    { name: 'Spatie Data',    role: 'DTO layer' },
    { name: 'Larastan',       role: 'Static analysis' },
    { name: 'Laravel Pint',   role: 'Code style' },
    { name: 'Claude Code',    role: 'AI dev partner' },
    { name: 'SQLite / PgSQL', role: 'Central + tenant DBs' },
];

// motion helpers
const fadeUp = { initial: { opacity: 0, y: 28 }, visibleOnce: { opacity: 1, y: 0, transition: { duration: 550, ease: 'easeOut' } } };
const fadeIn  = { initial: { opacity: 0 },        visibleOnce: { opacity: 1,    transition: { duration: 500, ease: 'easeOut' } } };
const heroV   = (delay = 0) => ({ initial: { opacity: 0, y: 28 }, enter: { opacity: 1, y: 0, transition: { duration: 650, delay, ease: 'easeOut' } } });
const cardV   = (i) => ({ initial: { opacity: 0, y: 24, scale: 0.98 }, visibleOnce: { opacity: 1, y: 0, scale: 1, transition: { duration: 480, delay: i * 75, ease: 'easeOut' } } });
const staggerV = (i, d = 80) => ({ initial: { opacity: 0, x: -16 }, visibleOnce: { opacity: 1, x: 0, transition: { duration: 420, delay: i * d, ease: 'easeOut' } } });
</script>

<template>
    <Head title="Multi-Tenant SSO Boilerplate — Ron Mart Daniel Javier" />

    <!-- Fonts -->
    <component :is="'link'" rel="preconnect" href="https://fonts.googleapis.com" />
    <component :is="'link'" rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <component :is="'link'" rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&family=DM+Sans:wght@400;500&family=DM+Mono:wght@400;500&display=swap" />

    <div class="page-root">

        <!-- ── Aurora ── -->
        <div class="aurora" aria-hidden="true">
            <div class="orb orb-1" /><div class="orb orb-2" /><div class="orb orb-3" />
            <div class="noise" />
        </div>

        <!-- ── Navbar ── -->
        <header :class="['nav', isScrolled && 'nav--scrolled']">
            <div class="nav-inner">
                <div v-motion :initial="{ opacity:0, x:-16 }" :enter="{ opacity:1, x:0, transition:{duration:500} }" class="brand">
                    <div class="brand-mark">
                        <svg class="brand-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <span class="font-grotesk font-semibold text-white text-sm">MultiTenant<span class="text-blue-400">SSO</span></span>
                </div>
                <nav v-motion :initial="{ opacity:0, x:16 }" :enter="{ opacity:1, x:0, transition:{duration:500} }" class="nav-links">
                    <a href="#goals"   class="nav-link">About</a>
                    <a href="#features" class="nav-link">Features</a>
                    <a href="#install"  class="nav-link">Install</a>
                    <a href="#start"    class="nav-link">Quick Start</a>
                    <a :href="githubUrl" target="_blank" rel="noopener" class="ghost-btn text-sm px-4 py-2 rounded-lg">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                        GitHub
                    </a>
                    <Link href="/login" class="cta-btn text-sm px-5 py-2 rounded-lg">Sign In →</Link>
                </nav>
            </div>
        </header>

        <!-- ── Hero ── -->
        <section class="hero">
            <div class="hero-inner">
                <!-- Left copy -->
                <div class="hero-copy">
                    <div v-motion :initial="{ opacity:0, scale:0.9 }" :enter="{ opacity:1, scale:1, transition:{duration:450} }" class="badge-pill">
                        <span class="badge-dot" />
                        Senior Full-Stack · AI-Assisted · Open Source
                    </div>

                    <h1 v-motion :initial="heroV(100).initial" :enter="heroV(100).enter" class="hero-h1 font-grotesk">
                        The multi-tenant SaaS boilerplate<br />
                        <span class="grad-text">built with Claude AI</span>
                    </h1>

                    <p v-motion :initial="heroV(200).initial" :enter="heroV(200).enter" class="hero-sub font-sans">
                        A production-ready Laravel starter kit that demonstrates senior full-stack architecture, real-world AI-assisted development, and everything you need to launch a multi-tenant product — on day one.
                    </p>

                    <div v-motion :initial="heroV(310).initial" :enter="heroV(310).enter" class="hero-ctas">
                        <a :href="githubUrl" target="_blank" rel="noopener" class="cta-btn px-7 py-3.5 rounded-xl text-sm font-semibold inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                            View on GitHub
                        </a>
                        <a href="#install" class="ghost-btn px-7 py-3.5 rounded-xl text-sm font-medium">Quick Install ↓</a>
                        <Link href="/login" class="ghost-btn px-7 py-3.5 rounded-xl text-sm font-medium">Live Demo →</Link>
                    </div>

                    <!-- Stats -->
                    <div v-motion :initial="heroV(420).initial" :enter="heroV(420).enter" class="hero-stats">
                        <div v-for="s in [['701+','Tests passing'],['19','Modules & routes'],['100%','API parity'],['0','Config to start']]" :key="s[0]" class="stat-item">
                            <div class="stat-val font-grotesk">{{ s[0] }}</div>
                            <div class="stat-lbl font-mono">{{ s[1] }}</div>
                        </div>
                    </div>
                </div>

                <!-- Right: author card -->
                <div v-motion :initial="{ opacity:0, x:40, scale:0.96 }" :enter="{ opacity:1, x:0, scale:1, transition:{duration:700, delay:250} }" class="author-card hidden lg:block">
                    <div class="author-card-inner">
                        <div class="author-top">
                            <div class="author-avatar font-grotesk">R</div>
                            <div>
                                <div class="author-name font-grotesk">Ron Mart Daniel Javier</div>
                                <div class="author-title font-mono">Senior Full-Stack Engineer</div>
                            </div>
                        </div>
                        <div class="author-skills">
                            <div v-for="skill in ['Laravel / PHP','Vue.js / Inertia','Multi-tenant architecture','AI-assisted dev (Claude)','PostgreSQL / Redis / Horizon','PHPUnit · 700+ tests']" :key="skill" class="skill-row font-sans">
                                <svg class="w-3.5 h-3.5 text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ skill }}
                            </div>
                        </div>
                        <div class="author-footer font-mono">
                            <span class="live-dot" />
                            Available for opportunities
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Goals ── -->
        <section id="goals" class="section">
            <div class="container">
                <div v-motion="fadeUp" class="section-label">
                    <span class="tag font-mono">Why this repo exists</span>
                    <h2 class="section-h2 font-grotesk">Three goals. One codebase.</h2>
                    <p class="section-sub font-sans">This isn't just a demo — it's a production-grade starting point that solves real problems.</p>
                </div>
                <div class="goals-grid">
                    <div v-for="(g, i) in goals" :key="g.tag" v-motion="cardV(i)" :class="['goal-card', g.border]">
                        <div :class="['goal-icon-wrap', g.accent, g.border, g.bg]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="g.icon" />
                            </svg>
                        </div>
                        <div :class="['goal-tag font-mono', g.accent]">{{ g.tag }}</div>
                        <h3 class="goal-title font-grotesk">{{ g.title }}</h3>
                        <p class="goal-body font-sans">{{ g.body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Features ── -->
        <section id="features" class="section">
            <div class="container">
                <div v-motion="fadeUp" class="section-label">
                    <span class="tag font-mono">What ships with it</span>
                    <h2 class="section-h2 font-grotesk">The full stack, <span class="grad-text">not just the skeleton</span></h2>
                    <p class="section-sub font-sans">Every module is production-tested, fully typed, and ready to extend.</p>
                </div>
                <div class="features-grid">
                    <div v-for="(f, i) in features" :key="f.tag" v-motion="cardV(i)" :class="['feat-card', f.border]">
                        <div class="feat-glow" :style="`background: radial-gradient(ellipse at top left, var(--glow-from, rgba(59,130,246,0.12)), transparent 60%)`" />
                        <div :class="['feat-tag font-mono', f.accent]">{{ f.tag }}</div>
                        <div :class="['feat-icon', f.accent, f.border]">
                            <!-- key -->
                            <svg v-if="f.icon==='key'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            <!-- building -->
                            <svg v-if="f.icon==='building'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <!-- shield -->
                            <svg v-if="f.icon==='shield'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <!-- chart -->
                            <svg v-if="f.icon==='chart'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <!-- bell -->
                            <svg v-if="f.icon==='bell'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <!-- cog -->
                            <svg v-if="f.icon==='cog'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <h3 class="feat-title font-grotesk">{{ f.title }}</h3>
                        <p class="feat-body font-sans">{{ f.body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── AI Workflow ── -->
        <section class="section ai-section">
            <div class="ai-glow" aria-hidden="true" />
            <div class="container">
                <div class="ai-grid">
                    <div v-motion="fadeUp">
                        <span class="tag font-mono">AI-Assisted development</span>
                        <h2 class="section-h2 font-grotesk mt-3">
                            Built with<br /><span class="grad-text">Claude Code</span>
                        </h2>
                        <p class="section-sub font-sans mt-3 max-w-md">
                            Every part of this project — from architecture decisions to test suites to this UI — was developed collaboratively with Claude Code as an AI engineering partner.
                        </p>
                        <div class="mt-6 flex items-center gap-3">
                            <div class="claude-badge">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                                Claude Code
                            </div>
                            <span class="text-slate-600 text-sm font-mono">×</span>
                            <div class="human-badge font-mono">Human direction</div>
                        </div>
                    </div>
                    <div class="ai-steps">
                        <div v-for="(step, i) in aiSteps" :key="step.num" v-motion="staggerV(i, 100)" class="ai-step">
                            <div class="ai-step-num font-mono">{{ step.num }}</div>
                            <div>
                                <div class="ai-step-title font-grotesk">{{ step.title }}</div>
                                <div class="ai-step-body font-sans">{{ step.body }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Install ── -->
        <section id="install" class="section">
            <div class="container">
                <div v-motion="fadeUp" class="section-label">
                    <span class="tag font-mono">Installation</span>
                    <h2 class="section-h2 font-grotesk">Up and running <span class="grad-text">in minutes</span></h2>
                    <p class="section-sub font-sans">Pick your setup method — bare-metal or Docker.</p>
                </div>

                <!-- Method tabs -->
                <div v-motion="fadeIn" class="install-tabs">
                    <button
                        :class="['install-tab font-mono', installMethod === 'manual' && 'install-tab--active']"
                        @click="installMethod = 'manual'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Manual
                    </button>
                    <button
                        :class="['install-tab font-mono', installMethod === 'docker' && 'install-tab--active']"
                        @click="installMethod = 'docker'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        Docker
                    </button>
                </div>

                <!-- Requirements note -->
                <p v-motion="fadeIn" class="install-req font-mono">
                    <template v-if="installMethod === 'manual'">
                        Requires PHP 8.2+, Composer, Node.js 20+, and Redis. SQLite works out of the box.
                    </template>
                    <template v-else>
                        Requires Docker Desktop. Everything else (PHP, Redis, PostgreSQL, Nginx) runs in containers.
                    </template>
                </p>

                <div v-motion="fadeUp" class="terminal">
                    <div class="terminal-bar">
                        <span class="dot bg-red-500/80" /><span class="dot bg-yellow-500/80" /><span class="dot bg-green-500/80" />
                        <span class="ml-3 font-mono text-slate-500 text-xs">bash</span>
                    </div>
                    <div class="terminal-body">
                        <div v-for="step in installSteps" :key="step.cmd" class="t-line">
                            <div class="font-mono text-slate-600 text-xs mb-0.5">{{ step.comment }}</div>
                            <div class="flex items-start gap-2">
                                <span class="text-blue-400 font-mono select-none shrink-0">❯</span>
                                <span class="font-mono text-slate-200 text-sm break-all">{{ step.cmd }}</span>
                            </div>
                        </div>
                        <div class="flex items-center mt-4 gap-2">
                            <span class="text-blue-400 font-mono select-none">❯</span>
                            <span class="terminal-cursor" />
                        </div>
                    </div>
                </div>
                <p v-motion="fadeIn" class="mt-4 text-center text-slate-600 text-xs font-mono">
                    Default admin: <span class="text-slate-400">admin@example.com</span> / <span class="text-slate-400">password</span>
                    <template v-if="installMethod === 'docker'"> · app runs at <span class="text-slate-400">http://localhost:8080</span></template>
                    — change credentials before going to production.
                </p>
            </div>
        </section>

        <!-- ── New project ── -->
        <section id="start" class="section">
            <div class="container">
                <div v-motion="fadeUp" class="section-label">
                    <span class="tag font-mono">Starter kit usage</span>
                    <h2 class="section-h2 font-grotesk">Start your <span class="grad-text">multi-tenant project</span></h2>
                    <p class="section-sub font-sans">This repo is designed to be cloned and extended. Here's how to make it yours.</p>
                </div>
                <div class="steps-grid">
                    <div v-for="(s, i) in newProjectSteps" :key="s.step" v-motion="staggerV(i, 90)" class="step-card">
                        <div class="step-num font-mono">{{ s.step }}</div>
                        <div>
                            <div class="step-title font-grotesk">{{ s.title }}</div>
                            <div class="step-body font-sans">{{ s.body }}</div>
                        </div>
                    </div>
                </div>

                <!-- Module structure callout -->
                <div v-motion="fadeUp" class="module-box">
                    <div class="module-box-label font-mono">New module structure</div>
                    <pre class="module-tree font-mono">app/<span class="text-blue-400">{YourModule}</span>/
  Data/               <span class="text-slate-600"># Service-layer DTOs</span>
  Http/
    Controllers/
      <span class="text-slate-300">{Module}Controller.php</span>     <span class="text-slate-600"># Inertia / web</span>
      <span class="text-slate-300">{Module}ApiController.php</span>   <span class="text-slate-600"># REST API</span>
    Requests/           <span class="text-slate-600"># One FormRequest per action</span>
  Routes/
    <span class="text-slate-300">web_{module}.php</span>
    <span class="text-slate-300">api_{module}.php</span>
  Services/
    <span class="text-slate-300">{Module}Service.php</span>
  Tests/
    <span class="text-slate-300">{Module}WebTest.php</span>
    <span class="text-slate-300">{Module}ApiTest.php</span>
    <span class="text-slate-300">{Module}ServiceTest.php</span></pre>
                </div>
            </div>
        </section>

        <!-- ── Stack ── -->
        <section class="section">
            <div class="container">
                <div v-motion="fadeUp" class="section-label">
                    <span class="tag font-mono">Technology stack</span>
                    <h2 class="section-h2 font-grotesk">Built on the <span class="grad-text">best of Laravel</span></h2>
                </div>
                <div class="stack-grid">
                    <div v-for="(s, i) in stack" :key="s.name" v-motion="cardV(i)" class="stack-card">
                        <div class="stack-name font-grotesk">{{ s.name }}</div>
                        <div class="stack-role font-mono">{{ s.role }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CTA ── -->
        <section class="section cta-section">
            <div class="cta-glow" aria-hidden="true" />
            <div v-motion="fadeUp" class="cta-inner">
                <h2 class="cta-h2 font-grotesk">
                    Clone it. Extend it.<br /><span class="grad-text">Ship faster.</span>
                </h2>
                <p class="cta-sub font-sans">Whether you're evaluating this as a hiring manager or starting your next SaaS — it's all here.</p>
                <div class="cta-btns">
                    <a :href="githubUrl" target="_blank" rel="noopener" class="cta-btn px-8 py-4 rounded-xl text-base font-semibold inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                        View on GitHub
                    </a>
                    <Link href="/login" class="ghost-btn px-8 py-4 rounded-xl text-base font-medium">Live Demo →</Link>
                </div>
            </div>
        </section>

        <!-- ── Footer ── -->
        <footer class="footer">
            <div class="container footer-inner">
                <div class="flex items-center gap-2.5">
                    <div class="brand-mark w-6 h-6 rounded-md">
                        <svg class="w-3 h-3 text-white relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    </div>
                    <span class="text-slate-500 text-sm font-grotesk">MultiTenant<span class="text-blue-500">SSO</span> by Ron Mart Daniel Javier</span>
                </div>
                <div class="flex items-center gap-4">
                    <a :href="githubUrl" target="_blank" rel="noopener" class="text-slate-600 hover:text-slate-400 transition-colors text-sm font-mono">GitHub →</a>
                    <Link href="/login" class="text-slate-600 hover:text-slate-400 transition-colors text-sm font-mono">Demo →</Link>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&family=DM+Sans:wght@400;500&family=DM+Mono:wght@400;500&display=swap');

.font-grotesk { font-family: 'Space Grotesk', sans-serif; }
.font-sans    { font-family: 'DM Sans', sans-serif; }
.font-mono    { font-family: 'DM Mono', monospace; }

/* ── Root ── */
.page-root { min-height: 100vh; background: #030712; color: #f1f5f9; overflow-x: hidden; }

/* ── Aurora ── */
.aurora { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
.orb { position: absolute; border-radius: 50%; filter: blur(100px); will-change: transform; }
.orb-1 { width: 700px; height: 700px; top: -200px; left: -150px; background: radial-gradient(circle, rgba(59,130,246,.13) 0%, transparent 70%); animation: drift1 20s ease-in-out infinite alternate; }
.orb-2 { width: 600px; height: 600px; top: 35%; right: -200px; background: radial-gradient(circle, rgba(139,92,246,.10) 0%, transparent 70%); animation: drift2 25s ease-in-out infinite alternate; }
.orb-3 { width: 500px; height: 500px; bottom: 0; left: 30%; background: radial-gradient(circle, rgba(99,102,241,.08) 0%, transparent 70%); animation: drift3 18s ease-in-out infinite alternate; }
.noise { position: absolute; inset: 0; opacity: .025; background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E"); background-size: 200px; }
@keyframes drift1 { from { transform: translate(0,0) scale(1); } to { transform: translate(60px,80px) scale(1.1); } }
@keyframes drift2 { from { transform: translate(0,0) scale(1); } to { transform: translate(-50px,60px) scale(1.08); } }
@keyframes drift3 { from { transform: translate(0,0) scale(1); } to { transform: translate(40px,-50px) scale(1.05); } }

/* ── Navbar ── */
.nav { position: fixed; top: 0; left: 0; right: 0; z-index: 50; transition: all .4s ease; }
.nav--scrolled { background: rgba(3,7,18,.85); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,.06); box-shadow: 0 8px 32px rgba(0,0,0,.3); }
.nav-inner { max-width: 1152px; margin: 0 auto; padding: 0 24px; height: 64px; display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 1; }
.nav-links { display: flex; align-items: center; gap: 4px; }
.nav-link { padding: 8px 12px; font-size: 13px; color: rgba(148,163,184,.8); border-radius: 8px; transition: all .15s; font-family: 'DM Sans', sans-serif; }
.nav-link:hover { color: white; background: rgba(255,255,255,.05); }

/* ── Brand mark ── */
.brand { display: flex; align-items: center; gap: 10px; }
.brand-mark { width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg,#3b82f6,#6d28d9); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 16px rgba(59,130,246,.3); flex-shrink: 0; }
.brand-icon { width: 16px; height: 16px; color: white; }

/* ── Buttons ── */
.cta-btn { background: linear-gradient(135deg,#2563eb,#7c3aed); color: white; box-shadow: 0 0 28px rgba(99,102,241,.35), 0 4px 12px rgba(0,0,0,.3); transition: all .15s ease; cursor: pointer; }
.cta-btn:hover { background: linear-gradient(135deg,#3b82f6,#8b5cf6); box-shadow: 0 0 40px rgba(99,102,241,.5), 0 4px 16px rgba(0,0,0,.35); transform: translateY(-1px); }
.cta-btn:active { transform: scale(.97); }
.ghost-btn { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.09); color: rgba(226,232,240,.85); transition: all .15s; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.ghost-btn:hover { background: rgba(255,255,255,.08); color: white; border-color: rgba(255,255,255,.15); }

/* ── Gradient text ── */
.grad-text { background: linear-gradient(135deg,#60a5fa,#a78bfa 50%,#818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

/* ── Badge ── */
.badge-pill { display: inline-flex; align-items: center; gap: 8px; background: rgba(59,130,246,.08); border: 1px solid rgba(59,130,246,.2); color: #93c5fd; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: 9999px; font-family: 'DM Mono', monospace; }
.badge-dot { width: 6px; height: 6px; background: #60a5fa; border-radius: 50%; animation: pulse-dot 2s ease-in-out infinite; }
@keyframes pulse-dot { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.5; transform:scale(.75); } }

/* ── Tag ── */
.tag { display: inline-block; font-size: 11px; font-weight: 500; letter-spacing: .1em; text-transform: uppercase; color: #818cf8; background: rgba(99,102,241,.08); border: 1px solid rgba(99,102,241,.2); padding: 4px 12px; border-radius: 9999px; }

/* ── Hero ── */
.hero { position: relative; min-height: 100vh; display: flex; align-items: center; padding-top: 64px; z-index: 1; }
.hero-inner { max-width: 1152px; margin: 0 auto; padding: 80px 24px; width: 100%; display: grid; grid-template-columns: 1fr auto; gap: 48px; align-items: center; }
.hero-copy { max-width: 600px; }
.hero-h1 { font-size: clamp(2.25rem, 5vw, 3.75rem); font-weight: 800; line-height: 1.08; letter-spacing: -0.025em; color: white; margin: 28px 0 20px; }
.hero-sub { font-size: 1.125rem; color: rgba(148,163,184,.85); line-height: 1.7; max-width: 520px; }
.hero-ctas { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
.hero-stats { margin-top: 40px; padding-top: 32px; border-top: 1px solid rgba(255,255,255,.07); display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; }
.stat-item { text-align: center; }
.stat-val { font-size: 1.5rem; font-weight: 700; color: white; }
.stat-lbl { font-size: 11px; color: rgba(100,116,139,.9); margin-top: 2px; letter-spacing: .04em; }

/* ── Author card ── */
.author-card { width: 300px; flex-shrink: 0; }
.author-card-inner { background: rgba(255,255,255,.025); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,.08); border-radius: 20px; padding: 24px; box-shadow: 0 0 0 1px rgba(99,102,241,.1), 0 24px 48px rgba(0,0,0,.4), 0 0 40px rgba(59,130,246,.06); }
.author-top { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,.06); }
.author-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg,#2563eb,#7c3aed); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; color: white; flex-shrink: 0; box-shadow: 0 0 16px rgba(99,102,241,.3); }
.author-name { font-size: 14px; font-weight: 600; color: white; line-height: 1.2; }
.author-title { font-size: 11px; color: rgba(99,102,241,.9); margin-top: 3px; letter-spacing: .03em; }
.author-skills { display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; }
.skill-row { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: rgba(148,163,184,.85); }
.author-footer { display: flex; align-items: center; gap: 8px; font-size: 11px; color: rgba(100,116,139,.9); padding-top: 16px; border-top: 1px solid rgba(255,255,255,.06); letter-spacing: .03em; }
.live-dot { width: 6px; height: 6px; background: #10b981; border-radius: 50%; box-shadow: 0 0 6px rgba(16,185,129,.6); animation: pulse-dot 2s ease-in-out infinite; flex-shrink: 0; }

/* ── Sections ── */
.section { position: relative; padding: 96px 0; z-index: 1; }
.container { max-width: 1152px; margin: 0 auto; padding: 0 24px; }
.section-label { text-align: center; margin-bottom: 56px; }
.section-h2 { font-size: clamp(2rem, 4vw, 2.75rem); font-weight: 700; color: white; letter-spacing: -0.02em; margin-top: 12px; line-height: 1.15; }
.section-sub { color: rgba(148,163,184,.8); margin-top: 12px; max-width: 480px; margin-left: auto; margin-right: auto; line-height: 1.7; font-size: 1.0625rem; }

/* ── Goals ── */
.goals-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; }
.goal-card { background: rgba(255,255,255,.025); backdrop-filter: blur(12px); border: 1px solid; border-radius: 20px; padding: 28px; transition: all .25s ease; }
.goal-card:hover { background: rgba(255,255,255,.04); transform: translateY(-2px); }
.goal-icon-wrap { width: 44px; height: 44px; border: 1px solid; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; transition: transform .2s; }
.goal-card:hover .goal-icon-wrap { transform: scale(1.1); }
.goal-tag { font-size: 10px; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 8px; }
.goal-title { font-size: 1.0625rem; font-weight: 600; color: white; margin-bottom: 10px; }
.goal-body { font-size: 14px; color: rgba(148,163,184,.75); line-height: 1.65; }

/* ── Features ── */
.features-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; }
.feat-card { position: relative; background: rgba(255,255,255,.02); backdrop-filter: blur(12px); border: 1px solid; border-radius: 20px; padding: 24px; overflow: hidden; transition: all .25s ease; }
.feat-card:hover { background: rgba(255,255,255,.035); transform: translateY(-2px); }
.feat-glow { position: absolute; inset: 0; pointer-events: none; opacity: .7; }
.feat-tag { font-size: 10px; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 16px; position: relative; }
.feat-icon { width: 40px; height: 40px; background: rgba(255,255,255,.04); border: 1px solid; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; position: relative; transition: all .2s; }
.feat-card:hover .feat-icon { transform: scale(1.1); background: rgba(255,255,255,.07); }
.feat-title { font-size: 1rem; font-weight: 600; color: white; margin-bottom: 8px; position: relative; }
.feat-body { font-size: 13.5px; color: rgba(148,163,184,.75); line-height: 1.6; position: relative; }

/* ── AI section ── */
.ai-section { background: rgba(99,102,241,.03); }
.ai-glow { position: absolute; inset: 0; background: radial-gradient(ellipse at center, rgba(99,102,241,.07) 0%, transparent 70%); pointer-events: none; }
.ai-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: start; }
.ai-steps { display: flex; flex-direction: column; gap: 0; }
.ai-step { display: flex; gap: 20px; padding: 20px 0; border-bottom: 1px solid rgba(255,255,255,.05); }
.ai-step:last-child { border-bottom: none; }
.ai-step-num { font-size: 11px; color: rgba(99,102,241,.7); min-width: 28px; padding-top: 2px; letter-spacing: .05em; }
.ai-step-title { font-size: 15px; font-weight: 600; color: white; margin-bottom: 6px; }
.ai-step-body { font-size: 13.5px; color: rgba(148,163,184,.75); line-height: 1.6; }
.claude-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(99,102,241,.1); border: 1px solid rgba(99,102,241,.25); color: #a5b4fc; font-size: 12px; font-weight: 500; padding: 6px 12px; border-radius: 8px; font-family: 'DM Mono', monospace; }
.human-badge { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1); color: rgba(148,163,184,.8); font-size: 12px; padding: 6px 12px; border-radius: 8px; }

/* ── Install tabs ── */
.install-tabs { display: flex; gap: 6px; justify-content: center; margin-bottom: 16px; background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.07); border-radius: 12px; padding: 5px; width: fit-content; margin-left: auto; margin-right: auto; }
.install-tab { display: inline-flex; align-items: center; gap: 7px; padding: 8px 18px; border-radius: 8px; font-size: 12px; letter-spacing: .04em; color: rgba(148,163,184,.7); cursor: pointer; transition: all .15s ease; border: 1px solid transparent; }
.install-tab:hover { color: rgba(226,232,240,.85); background: rgba(255,255,255,.04); }
.install-tab--active { background: rgba(37,99,235,.15); border-color: rgba(59,130,246,.25); color: #93c5fd; }
.install-req { font-size: 11.5px; color: rgba(100,116,139,.8); text-align: center; margin-bottom: 20px; max-width: 520px; margin-left: auto; margin-right: auto; }

/* ── Terminal ── */
.terminal { background: rgba(15,23,42,.85); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,.08); border-radius: 16px; overflow: hidden; box-shadow: 0 0 0 1px rgba(99,102,241,.1), 0 24px 48px rgba(0,0,0,.4), 0 0 60px rgba(59,130,246,.06); max-width: 780px; margin: 0 auto; }
.terminal-bar { display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,.03); border-bottom: 1px solid rgba(255,255,255,.06); padding: 12px 16px; }
.dot { width: 10px; height: 10px; border-radius: 50%; }
.terminal-body { padding: 20px; }
.t-line { margin-bottom: 18px; }
.t-line:last-child { margin-bottom: 0; }
.terminal-cursor { display: inline-block; width: 8px; height: 15px; background: #60a5fa; border-radius: 1px; margin-left: 2px; vertical-align: text-bottom; animation: blink 1.1s step-end infinite; }
@keyframes blink { 0%,100% { opacity:1; } 50% { opacity:0; } }

/* ── Steps ── */
.steps-grid { display: flex; flex-direction: column; gap: 0; max-width: 720px; margin: 0 auto 48px; }
.step-card { display: flex; gap: 20px; padding: 24px 0; border-bottom: 1px solid rgba(255,255,255,.05); }
.step-card:last-child { border-bottom: none; }
.step-num { width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg,rgba(37,99,235,.2),rgba(124,58,237,.2)); border: 1px solid rgba(99,102,241,.25); display: flex; align-items: center; justify-content: center; font-size: 12px; color: #818cf8; flex-shrink: 0; margin-top: 2px; }
.step-title { font-size: 15px; font-weight: 600; color: white; margin-bottom: 6px; }
.step-body { font-size: 13.5px; color: rgba(148,163,184,.75); line-height: 1.65; }

/* ── Module box ── */
.module-box { background: rgba(255,255,255,.02); border: 1px solid rgba(255,255,255,.07); border-radius: 16px; padding: 24px 28px; max-width: 720px; margin: 0 auto; }
.module-box-label { font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: rgba(99,102,241,.8); margin-bottom: 16px; }
.module-tree { font-size: 12.5px; line-height: 1.9; color: rgba(148,163,184,.7); white-space: pre; overflow-x: auto; }

/* ── Stack ── */
.stack-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; }
.stack-card { background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07); border-radius: 14px; padding: 18px 20px; transition: all .2s; }
.stack-card:hover { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.12); }
.stack-name { font-size: 14px; font-weight: 600; color: white; margin-bottom: 4px; }
.stack-role { font-size: 11px; color: rgba(100,116,139,.9); letter-spacing: .03em; }

/* ── CTA section ── */
.cta-section { text-align: center; overflow: hidden; }
.cta-glow { position: absolute; bottom: -60px; left: 50%; transform: translateX(-50%); width: 600px; height: 400px; background: radial-gradient(ellipse, rgba(99,102,241,.12) 0%, transparent 70%); filter: blur(40px); pointer-events: none; }
.cta-inner { position: relative; max-width: 640px; margin: 0 auto; padding: 0 24px; }
.cta-h2 { font-size: clamp(2rem,4vw,3rem); font-weight: 800; color: white; letter-spacing: -0.025em; line-height: 1.1; margin-bottom: 16px; }
.cta-sub { color: rgba(148,163,184,.8); font-size: 1.0625rem; margin-bottom: 32px; line-height: 1.65; }
.cta-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

/* ── Footer ── */
.footer { border-top: 1px solid rgba(255,255,255,.05); padding: 28px 0; position: relative; z-index: 1; }
.footer-inner { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }

/* ── Responsive ── */
@media (max-width: 1023px) {
    .hero-inner { grid-template-columns: 1fr; }
    .ai-grid { grid-template-columns: 1fr; gap: 40px; }
    .goals-grid { grid-template-columns: 1fr; }
}
@media (max-width: 767px) {
    .features-grid { grid-template-columns: 1fr; }
    .hero-stats { grid-template-columns: repeat(2,1fr); }
    .stack-grid { grid-template-columns: repeat(2,1fr); }
    .nav-links .nav-link { display: none; }
}

/* ── Reduced motion ── */
@media (prefers-reduced-motion: reduce) {
    .orb-1,.orb-2,.orb-3 { animation: none; }
    .badge-dot,.live-dot,.terminal-cursor { animation: none; }
}
</style>
