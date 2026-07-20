/* prettier-ignore */
import {
createInertiaApp
} from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import { route as ziggyRoute } from 'ziggy-js';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        resolve: (name) => {
            const pages = import.meta.glob('./pages/**/*.tsx', {
                eager: true,
            });
            return pages[`./pages/${name}.tsx`];
        },
        // prettier-ignore
        setup: ({ App, props }) => {
            // The browser gets route() from the @routes Blade directive; in
            // SSR there is no window, so build it from the shared ziggy prop.
            const ziggy = page.props.ziggy;

            if (ziggy) {
                globalThis.route = (name, params, absolute) =>
                    ziggyRoute(name, params, absolute, {
                        ...ziggy,
                        location: new URL(ziggy.location),
                    });
            }

            return <App {...props} />;
        },
    }),
);
