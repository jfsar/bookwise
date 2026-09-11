import { useEffect, useState } from 'react';
import { MoonIcon, SunIcon } from 'lucide-react';

import { Button } from '@/Components/ui/button';

function getInitialTheme() {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        localStorage.getItem('theme') === 'dark' ||
        (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)
    );
}

export default function ThemeToggle() {
    const [dark, setDark] = useState(getInitialTheme);

    useEffect(() => {
        document.documentElement.classList.toggle('dark', dark);
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    }, [dark]);

    return (
        <Button
            variant="ghost"
            size="icon"
            onClick={() => setDark((current) => !current)}
            aria-label="Toggle dark mode"
        >
            {dark ? <SunIcon className="size-5" /> : <MoonIcon className="size-5" />}
        </Button>
    );
}