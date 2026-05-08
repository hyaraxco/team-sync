import { ref, onMounted } from "vue";

const isDark = ref(false);

export function useDarkMode() {
    const toggle = () => {
        isDark.value = !isDark.value;
        updateDOM();
        localStorage.setItem("theme", isDark.value ? "dark" : "light");
    };

    const updateDOM = () => {
        if (isDark.value) {
            document.documentElement.classList.add("dark");
        } else {
            document.documentElement.classList.remove("dark");
        }
    };

    const init = () => {
        const stored = localStorage.getItem("theme");
        if (stored) {
            isDark.value = stored === "dark";
        } else {
            isDark.value =
                typeof window.matchMedia === "function"
                    ? window.matchMedia("(prefers-color-scheme: dark)").matches
                    : false;
        }
        updateDOM();
    };

    onMounted(() => {
        init();
        if (typeof window.matchMedia === "function") {
            window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (e) => {
                if (!localStorage.getItem("theme")) {
                    isDark.value = e.matches;
                    updateDOM();
                }
            });
        }
    });

    return { isDark, toggle };
}
