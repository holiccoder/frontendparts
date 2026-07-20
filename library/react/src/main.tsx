import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
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
function App() {
    const path = window.location.pathname.replace(/\/+$/, '') || '/';

    if (path.startsWith('/preview/')) {
        const slug = path.slice('/preview/'.length);
        const entry = resolveComponent(slug);

        if (!entry) {
            return (
                <main className="mx-auto max-w-2xl px-6 py-16 font-mono text-sm text-neutral-800">
                    <h1 className="text-lg font-bold">Component not found</h1>
                    <p className="mt-4">No component registered for slug:</p>
                    <pre className="mt-2 rounded bg-neutral-100 p-3">{slug}</pre>
                    <p className="mt-4">
                        <a href="/" className="text-blue-600 underline">
                            ← Back to component index
                        </a>
                    </p>
                </main>
            );
        }

        const Component = entry.component;

        return <Component {...entry.data} />;
    }

    const components = listComponents();

    return (
        <main className="mx-auto max-w-2xl px-6 py-16">
            <h1 className="text-2xl font-bold tracking-tight text-neutral-900">FrontendParts Library — React</h1>
            <p className="mt-2 text-sm text-neutral-500">
                {components.length} component{components.length === 1 ? '' : 's'} discovered
            </p>
            <ul className="mt-8 space-y-2">
                {components.map((entry) => (
                    <li key={entry.slug}>
                        <a
                            href={`/preview/${entry.slug}`}
                            className="text-blue-600 underline decoration-blue-300 hover:decoration-blue-600"
                        >
                            {entry.slug}
                        </a>
                        <span className="ml-2 text-xs text-neutral-400">{entry.level}</span>
                    </li>
                ))}
            </ul>
        </main>
    );
}

createRoot(document.getElementById('root')!).render(
    <StrictMode>
        <App />
    </StrictMode>,
);
