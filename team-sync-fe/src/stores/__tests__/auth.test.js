import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useAuthStore } from '@/stores/auth';
import { axiosInstance } from '@/plugins/axios';
import Cookies from 'js-cookie';
import router from '@/router';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        defaults: {
            headers: {
                common: {},
            },
        },
    },
}));

vi.mock('js-cookie', () => ({
    default: {
        get: vi.fn(),
        set: vi.fn(),
        remove: vi.fn(),
    },
}));

vi.mock('@/router', () => ({
    default: {
        push: vi.fn(),
        replace: vi.fn(() => Promise.resolve()),
    },
}));

describe('Auth Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useAuthStore();
        vi.clearAllMocks();
        router.replace.mockResolvedValue(undefined);
    });

    it('login calls POST, stores token, sets success, and redirects', async () => {
        const credentials = {
            email: 'admin@example.com',
            password: 'secret',
            remember: true,
        };
        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Login successful',
                data: { token: 'jwt-token' },
            },
        });

        await store.login(credentials);

        expect(axiosInstance.post).toHaveBeenCalledWith('/login', {
            email: 'admin@example.com',
            password: 'secret',
        });
        expect(Cookies.set).toHaveBeenCalledWith('token', 'jwt-token', { expires: 30 });
        expect(store.success).toBe('Login successful');
        expect(router.push).toHaveBeenCalledWith({ name: 'admin.dashboard' });
        expect(store.loading).toBe(false);
    });

    it('logout clears local session and navigates to login', async () => {
        Cookies.get.mockReturnValueOnce('token-123');
        axiosInstance.post.mockResolvedValueOnce({ data: { message: 'Logout successful' } });
        store.user = { id: 1, name: 'Admin' };

        await store.logout();

        expect(Cookies.remove).toHaveBeenCalledWith('token');
        expect(router.replace).toHaveBeenCalledWith({ name: 'login' });
        expect(store.user).toBe(null);
        expect(store.loading).toBe(false);
        expect(axiosInstance.post).toHaveBeenCalledWith(
            '/logout',
            null,
            expect.objectContaining({
                timeout: 5000,
                headers: expect.objectContaining({
                    Authorization: 'Bearer token-123',
                }),
            }),
        );
    });

    it('fetchUser (checkAuth) returns user and updates state', async () => {
        const mockUser = { id: 9, name: 'Nina', email: 'nina@example.com' };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: mockUser } });

        const result = await store.checkAuth();

        expect(axiosInstance.get).toHaveBeenCalledWith('/me');
        expect(result).toEqual(mockUser);
        expect(store.user).toEqual(mockUser);
        expect(store.loading).toBe(false);
    });

    it('updateProfile sends form data, refreshes user, and sets success', async () => {
        const payload = { name: 'Updated User' };
        const updatedUser = { id: 9, name: 'Updated User' };

        axiosInstance.post.mockResolvedValueOnce({
            data: {
                message: 'Profil berhasil diperbarui',
                data: updatedUser,
            },
        });
        axiosInstance.get.mockResolvedValueOnce({ data: { data: updatedUser } });

        const result = await store.updateProfile(payload);

        expect(axiosInstance.post).toHaveBeenCalledWith('/me', expect.any(FormData));
        expect(axiosInstance.get).toHaveBeenCalledWith('/me');
        expect(result).toEqual(updatedUser);
        expect(store.user).toEqual(updatedUser);
        expect(store.success).toBe('Profil berhasil diperbarui');
        expect(store.loading).toBe(false);
    });

    it('sets error on login failure', async () => {
        const mockError = {
            response: {
                status: 400,
                data: { message: 'Invalid credentials' },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await store.login({ email: 'wrong@example.com', password: 'wrong' });

        expect(store.error).toBe('Invalid credentials');
        expect(store.loading).toBe(false);
    });
});
