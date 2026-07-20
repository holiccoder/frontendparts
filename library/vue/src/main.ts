import { createApp, defineComponent, h } from 'vue';
import { listComponents, resolveComponent } from './lib/registry';
import './app.css';

/**
 * Standalone preview entry — no router dependency.
 *
 * Routes:
 *   /                  → index of all discovered components
 *   /preview/{slug}    → mounts the component with its `data.json`
 *                        (e.g. /preview/elements/section-title-01)
 */
const App = defineComponent({
    setup() {
        const path = window.location.pathname.replace(/\/+$/, '') || '/';

        if (path.startsWith('/preview/')) {
            const slug = path.slice('/preview/'.length);
            const entry = resolveComponent(slug);

            if (!entry) {
                return () =>
                    h('main', { class: 'mx-auto max-w-2xl px-6 py-16 font-mono text-sm text-neutral-800' }, [
                        h('h1', { class: 'text-lg font-bold' }, 'Component not found'),
                        h('p', { class: 'mt-4' }, 'No component registered for slug:'),
                        h('pre', { class: 'mt-2 rounded bg-neutral-100 p-3' }, slug),
                        h('p', { class: 'mt-4' }, [
                            h('a', { href: '/', class: 'text-blue-600 underline' }, '← Back to component index'),
                        ]),
                    ]);
            }

            return () => h(entry.component, entry.data);
        }

        const components = listComponents();

        return () =>
            h('main', { class: 'mx-auto max-w-2xl px-6 py-16' }, [
                h('h1', { class: 'text-2xl font-bold tracking-tight text-neutral-900' }, 'FrontendParts Library — Vue'),
                h(
                    'p',
                    { class: 'mt-2 text-sm text-neutral-500' },
                    `${components.length} component${components.length === 1 ? '' : 's'} discovered`,
                ),
                h(
                    'ul',
                    { class: 'mt-8 space-y-2' },
                    components.map((entry) =>
                        h('li', { key: entry.slug }, [
                            h(
                                'a',
                                {
                                    href: `/preview/${entry.slug}`,
                                    class: 'text-blue-600 underline decoration-blue-300 hover:decoration-blue-600',
                                },
                                entry.slug,
                            ),
                            h('span', { class: 'ml-2 text-xs text-neutral-400' }, entry.level),
                        ]),
                    ),
                ),
            ]);
    },
});

createApp(App).mount('#app');
