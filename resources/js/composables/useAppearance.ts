import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark';

export function updateTheme(value: Appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle('dark', value === 'dark');
}

// Função para determinar o tema baseado no horário
export function getTimeBasedTheme(): Appearance {
    const hour = new Date().getHours();
    // 6h às 18h = claro, 18h às 6h = escuro
    return hour >= 6 && hour < 18 ? 'light' : 'dark';
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to time-based...
    const savedAppearance = getStoredAppearance();
    const themeToUse = savedAppearance || getTimeBasedTheme();
    updateTheme(themeToUse);
}

const appearance = ref<Appearance>('light');

export function useAppearance() {
    onMounted(() => {
        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        } else {
            // Se não há preferência salva, usa o tema baseado no horário
            appearance.value = getTimeBasedTheme();
        }
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
    };
}
