import { createContext, useContext, useEffect, useState } from 'react';
import type { ReactNode } from 'react';

type ThemeMode = 'dark' | 'light';

interface ThemeContextValue {
    mode: ThemeMode;
    toggle: () => void;
    setMode: (m: ThemeMode) => void;
}

const ThemeContext = createContext<ThemeContextValue>({
    mode: 'dark',
    toggle: () => {},
    setMode: () => {},
});

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [mode, setMode] = useState<ThemeMode>(() => {
        const saved = localStorage.getItem('prasasta-theme');
        return saved === 'light' ? 'light' : 'dark';
    });

    useEffect(() => {
        localStorage.setItem('prasasta-theme', mode);
    }, [mode]);

    const toggle = () => setMode((m) => (m === 'dark' ? 'light' : 'dark'));

    return (
        <ThemeContext.Provider value={{ mode, toggle, setMode }}>
            {children}
        </ThemeContext.Provider>
    );
}

// eslint-disable-next-line react-refresh/only-export-components
export function useTheme() {
    return useContext(ThemeContext);
}
