import { setActivePinia, createPinia } from 'pinia';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useSetupStore } from '@/stores/setup';
import { axiosInstance } from '@/plugins/axios';

vi.mock('@/plugins/axios', () => ({
    axiosInstance: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

describe('Setup Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useSetupStore();
        vi.clearAllMocks();
    });

    it('fetchSetupStatus populates setup state', async () => {
        const payload = {
            needs_setup: true,
            has_license: false,
            has_company: false,
            has_superadmin: false,
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        const result = await store.fetchSetupStatus();

        expect(axiosInstance.get).toHaveBeenCalledWith('/setup/status');
        expect(result).toEqual(payload);
        expect(store.needsSetup).toBe(true);
        expect(store.hasLicense).toBe(false);
        expect(store.statusLoading).toBe(false);
    });

    it('fetchDoctor populates doctor result', async () => {
        const payload = {
            healthy: true,
            checks: [
                { label: 'Database', status: 'pass', message: 'OK' },
            ],
        };
        axiosInstance.get.mockResolvedValueOnce({ data: { data: payload } });

        await store.fetchDoctor();

        expect(axiosInstance.get).toHaveBeenCalledWith('/setup/doctor');
        expect(store.doctorResult).toEqual(payload);
        expect(store.isDoctorHealthy).toBe(true);
        expect(store.doctorChecks).toHaveLength(1);
    });

    it('verifyLicense calls verify endpoint', async () => {
        const payload = { valid: true, company_name: 'PT Test' };
        axiosInstance.post.mockResolvedValueOnce({ data: { data: payload } });

        const result = await store.verifyLicense('test-key');

        expect(axiosInstance.post).toHaveBeenCalledWith('/licenses/verify', {
            license_key: 'test-key',
        });
        expect(result).toEqual(payload);
        expect(store.licenseVerifyResult).toEqual(payload);
    });

    it('activateLicense calls store endpoint and sets hasLicense', async () => {
        axiosInstance.post.mockResolvedValueOnce({
            data: { data: { id: 1, is_active: true } },
        });

        await store.activateLicense('test-key', 'PT Test', 'admin@test.com');

        expect(axiosInstance.post).toHaveBeenCalledWith('/licenses', {
            license_key: 'test-key',
            company_name: 'PT Test',
            contact_email: 'admin@test.com',
        });
        expect(store.hasLicense).toBe(true);
    });

    it('bootstrap creates superadmin and returns token', async () => {
        const payload = {
            user: { id: 1, name: 'Admin', email: 'admin@test.com' },
            token: 'abc123',
        };
        axiosInstance.post.mockResolvedValueOnce({ data: { data: payload } });

        const result = await store.bootstrap('Admin', 'admin@test.com', 'password', 'password');

        expect(axiosInstance.post).toHaveBeenCalledWith('/setup/bootstrap', {
            name: 'Admin',
            email: 'admin@test.com',
            password: 'password',
            password_confirmation: 'password',
        });
        expect(result).toEqual(payload);
        expect(store.hasSuperadmin).toBe(true);
        expect(store.needsSetup).toBe(false);
        expect(store.bootstrapLoading).toBe(false);
    });

    it('bootstrap sets error on failure', async () => {
        const mockError = {
            response: {
                status: 422,
                data: { errors: { email: ['Email already taken'] } },
            },
        };
        axiosInstance.post.mockRejectedValueOnce(mockError);

        await expect(store.bootstrap('Admin', 'admin@test.com', 'pw', 'pw')).rejects.toEqual(mockError);

        expect(store.error).toBeTruthy();
        expect(store.bootstrapLoading).toBe(false);
    });
});
