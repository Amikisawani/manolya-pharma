import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import * as Sentry from '@sentry/vue';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import type { PageProps } from './types';

const appName = import.meta.env.VITE_APP_NAME || 'Manolya Pharma';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        const dsn = import.meta.env.VITE_SENTRY_DSN;

        if (dsn) {
            const page = props.initialPage.props as PageProps;
            const sentryMeta = page.sentry;

            Sentry.init({
                app,
                dsn,
                environment: sentryMeta?.environment ?? import.meta.env.MODE,
                release: sentryMeta?.release ?? undefined,
                tracesSampleRate: 0.1,
                sendDefaultPii: false,
            });

            const user = page.auth?.user;
            if (user?.id) {
                Sentry.setUser({
                    id: String(user.id),
                    email: user.email,
                });

                if (user.tenant_id) {
                    Sentry.setTag('tenant_id', String(user.tenant_id));
                }
            }
        }

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
