import { reactive } from "vue";

const toasts = reactive([]);

let toastId = 0;

export function useToast() {
    const addToast = ({ type = "success", title, message, duration = 4000 }) => {
        const id = ++toastId;
        toasts.push({ id, type, title, message, duration, visible: true });

        if (duration > 0) {
            setTimeout(() => removeToast(id), duration);
        }

        return id;
    };

    const removeToast = (id) => {
        const index = toasts.findIndex((t) => t.id === id);
        if (index !== -1) {
            toasts[index].visible = false;
            // Remove from array after exit animation
            setTimeout(() => {
                const idx = toasts.findIndex((t) => t.id === id);
                if (idx !== -1) toasts.splice(idx, 1);
            }, 300);
        }
    };

    const success = (title, message = "") => addToast({ type: "success", title, message });

    const error = (title, message = "") => addToast({ type: "error", title, message });

    const warning = (title, message = "") => addToast({ type: "warning", title, message });

    const info = (title, message = "") => addToast({ type: "info", title, message });

    return { toasts, addToast, removeToast, success, error, warning, info };
}
