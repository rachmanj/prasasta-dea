import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { App as AntdApp, ConfigProvider, theme as antdTheme } from 'antd';
import idID from 'antd/locale/id_ID';
import dayjs from 'dayjs';
import 'dayjs/locale/id';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { ThemeProvider, useTheme } from './theme';

dayjs.locale('id');

const appName = import.meta.env.VITE_APP_NAME || 'Prasasta ERP';

function Root({ children }: { children: React.ReactNode }) {
    const { mode } = useTheme();

    return (
        <ConfigProvider
            locale={idID}
            theme={{
                algorithm:
                    mode === 'dark'
                        ? antdTheme.darkAlgorithm
                        : antdTheme.defaultAlgorithm,
                token: {
                    colorPrimary: '#1677ff',
                    borderRadius: 6,
                },
            }}
        >
            <AntdApp>{children}</AntdApp>
        </ConfigProvider>
    );
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(
            <ThemeProvider>
                <Root>
                    <App {...props} />
                </Root>
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#1677ff',
    },
});
